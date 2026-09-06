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

        <div>
            <h1 class="text-2xl font-bold tracking-tight">À encaisser</h1>
            <p class="text-muted mt-1">Les commandes ouvertes, à encaisser ou à modifier.</p>
        </div>

        @if ($orders->isEmpty())
            <x-card class="shadow-sm">
                <div class="flex flex-col items-center gap-4 py-8 text-center">
                    <x-icon name="o-clipboard-document-list" class="text-base-content/25 h-12 w-12" />
                    <div>
                        <p class="font-semibold">Aucune commande pour l'instant.</p>
                        <p class="text-muted mt-1 text-sm">Les commandes validées apparaîtront ici.</p>
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
                            <span class="text-base font-black tabular-nums tracking-tight">#{{ $order->id }}</span>

                            @if ($order->is_paid)
                                <span class="badge badge-sm badge-success badge-soft gap-1 font-bold">
                                    <x-icon name="o-check" class="h-3 w-3" />
                                    Payé
                                </span>
                            @else
                                <span class="badge badge-sm badge-error badge-soft gap-1 font-bold">
                                    <x-icon name="o-x-mark" class="h-3 w-3" />
                                    Non payé
                                </span>
                            @endif

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

                            Le libellé disparaît sur une commande déjà réglée ; le composant
                            n'affiche alors que le menu (`filled($label)`).
                        --}}
                        <div class="border-base-200 border-t pt-3">
                            <x-admin.shared.row-menu
                                :label="$order->is_paid ? null : 'Encaisser'"
                                icon="o-banknotes"
                                :link="$order->is_paid ? null : route('bar.payment.show', $order)">

                                <x-menu-item
                                    icon="o-pencil-square"
                                    title="Modifier la commande"
                                    :link="route('bar.orders.modify', $order)" />

                                @can('bar.orders.manage')
                                    @unless ($order->is_paid)
                                        {{--
                                            La suppression est un POST : elle vit dans un <li>
                                            du <ul class="menu"> plutôt que dans un x-menu-item,
                                            qui ne sait rendre qu'un lien.
                                        --}}
                                        <li>
                                            <form method="POST" action="{{ route('bar.orders.destroy', $order) }}"
                                                onsubmit="return confirm('Supprimer définitivement la commande #{{ $order->id }} ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-error w-full justify-start gap-2 text-start">
                                                    <x-icon name="o-trash" class="h-4 w-4" />
                                                    Supprimer la commande
                                                </button>
                                            </form>
                                        </li>
                                    @endunless
                                @endcan
                            </x-admin.shared.row-menu>
                        </div>

                    </div>
                </div>
            @endforeach
        @endif

    </div>
</x-app-layout>
