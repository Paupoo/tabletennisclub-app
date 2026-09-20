@php
    use App\Support\Breadcrumb;

    $trail = Breadcrumb::make()->home()->bar()->current('À encaisser')->toArray();
@endphp

<x-app-layout>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$trail" separator="o-slash" />
    </x-slot:breadcrumbs>

    {{-- Idem pour une carte de commande et ses actions. --}}
    <div class="max-w-3xl space-y-4">

        {{--
            « Nouvelle commande » est ici en permanence, et pas seulement dans l'état
            vide comme avant — c'est-à-dire exactement quand on n'en a pas besoin.
            Encaisser renvoie sur cette file, et la boucle du bar est « servir →
            encaisser → servir le suivant » : sans ce bouton, chaque cycle finissait
            en cul-de-sac et repartait par la barre latérale, trois gestes plus loin.
        --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight">À encaisser</h1>
                <p class="text-muted mt-1">
                    {{ $orders->count() }} ardoise{{ $orders->count() > 1 ? 's' : '' }} ouverte{{ $orders->count() > 1 ? 's' : '' }}
                    <span class="text-subtle">·</span>
                    <span class="font-bold tabular-nums">{{ euros($orders->sum('total_price')) }}</span>
                </p>
            </div>

            <a href="{{ route('bar.index') }}" class="btn btn-primary btn-sm tap-comfort gap-2">
                <x-icon name="o-plus" class="h-4 w-4" />
                Nouvelle commande
            </a>
        </div>

        @if ($orders->isEmpty())
            <x-card class="shadow-sm">
                <div class="flex flex-col items-center gap-4 py-8 text-center">
                    <x-icon name="o-clipboard-document-list" class="text-base-content/25 h-12 w-12" />
                    <div>
                        <p class="font-semibold">Aucune ardoise ouverte.</p>
                        <p class="text-muted mt-1 text-sm">Les commandes laissées à régler apparaîtront ici, par nom.</p>
                    </div>
                    <a href="{{ route('bar.index') }}" class="btn btn-primary tap-comfort gap-2">
                        <x-icon name="o-plus" class="h-4 w-4" />
                        Nouvelle commande
                    </a>
                </div>
            </x-card>
        @else
            @foreach ($orders as $order)
                {{--
                    Carte ordinaire du design system : bordure grise de 1 px sur
                    les quatre côtés. Le statut de paiement est déjà porté par sa
                    pastille, en toutes lettres ; un liseré coloré en plus ne
                    faisait que bariloler la pile de commandes.
                --}}
                <div class="card bg-base-100 border-base-300 border shadow-sm">
                    <div class="card-body gap-3 p-4">

                        <div class="flex flex-wrap items-center gap-2">
                            {{--
                                Le nom porte la ligne ; le numéro reste, en second, parce
                                que c'est lui la poignée de l'audit et des mouvements de
                                stock. `font-bold` et non `font-black` : DS-B réserve le
                                poids exceptionnel aux chiffres-clés, et un identifiant
                                n'en est pas un.
                            --}}
                            <span class="min-w-0 truncate text-base font-bold tracking-tight">
                                {{ $order->name ?? 'Commande #' . $order->id }}
                            </span>
                            @if ($order->name)
                                <span class="text-subtle shrink-0 text-xs tabular-nums">#{{ $order->id }}</span>
                            @endif

                            {{--
                                Pas de branche « Payé » : BarOrderController::index filtre
                                `where('is_paid', 0)`, donc une commande réglée n'arrive jamais
                                jusqu'ici. La branche existait et ne pouvait pas s'afficher — elle
                                affirmait au lecteur que cette file mélange payées et impayées.
                                Si le filtre change un jour, c'est ce badge qu'il faudra rouvrir.
                            --}}
                            <span class="badge badge-sm badge-error badge-soft gap-1 font-bold">
                                <x-icon name="o-x-mark" class="h-3 w-3" />
                                Non payé
                            </span>

                            <span class="badge badge-sm badge-ghost font-bold tabular-nums">
                                {{ euros($order->total_price) }}
                            </span>

                            <span class="text-subtle ms-auto flex items-center gap-1.5 text-xs tabular-nums">
                                <x-icon name="o-clock" class="h-3.5 w-3.5" />
                                {{ $order->created_at->format('d/m/Y H:i') }}
                                @if ($order->createdBy)
                                    · {{ $order->createdBy->first_name }} {{ $order->createdBy->last_name }}
                                @endif
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($order->items as $item)
                                <span class="bg-base-200 inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs">
                                    <b class="tabular-nums">{{ $item->quantity }}×</b>
                                    {{ $item->product->name }}
                                </span>
                            @endforeach
                        </div>

                        {{--
                            Grammaire des listes du back-office : une seule action nommée
                            en ligne, le reste derrière un bouton lui aussi nommé. Un ⋮ nu
                            n'est annoncé par aucun lecteur d'écran — voir le commentaire
                            de <x-admin.shared.row-menu>.

                            « Encaisser » est l'action urgente : c'est quelqu'un qui attend
                            au comptoir. « Modifier » et « Supprimer » se font sur une
                            commande posée, ils peuvent coûter un tap de plus.

                            Toutes les commandes de cette file sont impayées (voir le badge
                            ci-dessus), donc l'action principale est toujours offerte.
                        --}}
                        <div class="border-base-300 border-t pt-3">
                            <x-admin.shared.row-menu
                                label="Encaisser"
                                icon="o-banknotes"
                                :link="route('bar.payment.show', $order)">

                                <x-menu-item
                                    icon="o-pencil-square"
                                    title="Ajouter ou corriger des consommations"
                                    :link="route('bar.orders.modify', $order)" />

                                {{--
                                    Renommer est rare — on nomme à l'ouverture — donc
                                    c'est une entrée de menu, pas une action de ligne.
                                    Le formulaire vit dans un <li> parce qu'un
                                    x-menu-item ne sait rendre qu'un lien.
                                --}}
                                <li>
                                    <form method="POST" action="{{ route('bar.orders.rename', $order) }}"
                                        class="flex items-center gap-2">
                                        @csrf
                                        <label class="sr-only" for="rename-{{ $order->id }}">
                                            Renommer l'ardoise
                                        </label>
                                        <input id="rename-{{ $order->id }}" type="text" name="name"
                                            value="{{ $order->name }}" maxlength="64" required
                                            class="input input-bordered input-sm w-full">
                                        <button type="submit" class="btn btn-outline btn-sm tap-comfort shrink-0 px-3"
                                            aria-label="Renommer cette ardoise">
                                            <x-icon name="o-check" class="h-4 w-4" />
                                        </button>
                                    </form>
                                </li>

                                @can('bar.orders.manage')
                                    {{--
                                        La suppression est un POST : elle vit dans un <li>
                                        du <ul class="menu"> plutôt que dans un x-menu-item,
                                        qui ne sait rendre qu'un lien.
                                    --}}
                                    {{--
                                        Un événement plutôt qu'un état partagé : ce bouton vit
                                        dans <x-admin.shared.row-menu>, qui déclare son propre
                                        `open`. Une variable de page du même nom serait masquée
                                        ici, et le clic ouvrirait le menu au lieu de la boîte.
                                    --}}
                                    <li>
                                        <button type="button" data-delete-order
                                            class="text-error w-full justify-start gap-2 text-start"
                                            @click="$dispatch('confirm-order-delete', {
                                                label: {{ json_encode('#' . $order->id . ($order->name ? ' — ' . $order->name : '')) }},
                                                action: {{ json_encode(route('bar.orders.destroy', $order)) }}
                                            })">
                                            <x-icon name="o-trash" class="h-4 w-4" />
                                            Supprimer la commande
                                        </button>
                                    </li>
                                @endcan
                            </x-admin.shared.row-menu>
                        </div>

                    </div>
                </div>
            @endforeach
        @endif

        {{-- Page servie en GET par un contrôleur : pas de Livewire, donc pas de
             wire:model. `id` fait sauter à maryUI sa branche Livewire, sans quoi
             il génère un `entangle()` et la page tombe en 500. L'état s'appelle
             `open` parce que le composant émet `x-trap="open"` quoi qu'il arrive.
             Et `showModal()` plutôt que l'attribut open : le drawer du layout
             porte un transform, qui piège tout descendant `position: fixed`. --}}
        <div x-data="{ open: false, label: '', action: '' }"
            @confirm-order-delete.window="label = $event.detail.label; action = $event.detail.action; open = true">
            <x-app-modal id="bar-order-delete" x-ref="confirmBox"
                x-effect="open ? $refs.confirmBox.showModal() : $refs.confirmBox.close()"
                @close="open = false"
                title="Supprimer définitivement cette commande ?">
                <p x-text="label" class="font-semibold"></p>
                <p class="mt-2 text-sm opacity-70">
                    Les consommations qu'elle porte disparaissent avec elle, et la caisse n'en gardera pas trace.
                </p>

                <x-slot:actions>
                    <x-button label="Annuler" @click="open = false" />
                    <form method="POST" :action="action" data-confirm-form>
                        @csrf
                        @method('DELETE')
                        <x-button label="Supprimer" type="submit" class="btn-error" />
                    </form>
                </x-slot:actions>
            </x-app-modal>
        </div>

    </div>
</x-app-layout>
