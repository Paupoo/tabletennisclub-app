@php
    use App\Support\Breadcrumb;

    $trail = Breadcrumb::make()->home()->bar()
        ->add('À encaisser', route('bar.orders.index'))
        ->current('Commande #' . $order->id)->toArray();
@endphp

<x-app-layout>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$trail" separator="o-slash" />
    </x-slot:breadcrumbs>

    {{-- max-w-2xl : un ticket se lit en colonne. Étalé sur les 1090 px du
         back-office, 800 px séparaient le nom du produit de son prix. --}}
    <div class="max-w-2xl space-y-4">

        <div>
            <a href="{{ route('bar.orders.index') }}" class="text-primary tap-min mb-2 inline-flex gap-1.5 text-sm font-semibold hover:underline">
                <x-icon name="o-arrow-left" class="h-4 w-4" />
                Retour aux commandes
            </a>
            <h1 class="text-2xl font-bold tracking-tight">
                Encaisser la commande <span class="tabular-nums">#{{ $order->id }}</span>
            </h1>
            <p class="text-muted mt-1">Choisissez le mode de paiement.</p>
        </div>

        {{-- Détail de la commande --}}
        <x-card class="shadow-sm">
            <h2 class="text-muted mb-3 text-xs font-bold uppercase tracking-widest">Articles</h2>

            <div class="divide-base-200 divide-y">
                @foreach ($order->items as $item)
                    <div class="flex items-center gap-3 py-2.5 first:pt-0">
                        <span class="text-muted w-8 shrink-0 text-sm font-bold tabular-nums">{{ $item->quantity }}×</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $item->product->name }}</p>
                            <p class="text-subtle text-xs tabular-nums">{{ euros($item->unit_price) }} l'unité</p>
                        </div>
                        <span class="shrink-0 text-sm font-bold tabular-nums">{{ euros($item->total_price) }}</span>
                    </div>
                @endforeach
            </div>

            <div class="border-base-content mt-4 flex items-center justify-between border-t-2 pt-4">
                <span class="text-muted text-xs font-bold uppercase tracking-widest">À encaisser</span>
                <span class="text-3xl font-black tabular-nums tracking-tight">{{ euros($order->total_price) }}</span>
            </div>
        </x-card>

        {{-- Modes de paiement --}}
        <x-card class="shadow-sm" x-data="{ offered: false }">
            <h2 class="text-muted mb-3 text-xs font-bold uppercase tracking-widest">Mode de paiement</h2>

            <div class="flex flex-wrap gap-2.5">
                <form method="POST" action="{{ route('bar.payment.pay', $order) }}" class="min-w-[8rem] flex-1">
                    @csrf
                    <input type="hidden" name="method" value="cash">
                    <button type="submit" class="btn btn-primary tap-comfort w-full gap-2">
                        <x-icon name="o-banknotes" class="h-4 w-4" />
                        Cash
                    </button>
                </form>

                {{--
                    Un lien, pas un POST : afficher le QR ne change rien côté
                    serveur, c'est une lecture. L'URL devient rechargeable, le
                    retour arrière fonctionne, et la page rendue est une réponse
                    GET ordinaire — celle qui reçoit bien les scripts de
                    l'application, ce qu'une réponse POST ne faisait pas ici.
                --}}
                <a href="{{ route('bar.payment.show', ['order' => $order->id, 'method' => 'qr']) }}"
                    class="btn btn-outline tap-comfort min-w-[8rem] flex-1 gap-2">
                    <x-icon name="o-qr-code" class="h-4 w-4" />
                    QR Code
                </a>

            </div>

            {{--
                « Offert » n'est pas un troisième mode de paiement : c'est
                l'inverse, une consommation qu'on renonce à encaisser. Il sort
                donc de la rangée, sous une ligne de séparation, à une échelle
                moindre — mais gardé bordé : un btn-ghost n'a aucune bordure au
                repos et se lit comme du texte, pas comme un bouton.
            --}}
            <div class="border-base-200 mt-3 border-t pt-3">
                <button type="button" class="btn btn-outline btn-sm text-muted tap-comfort gap-2"
                    @click="offered = ! offered" :aria-expanded="offered">
                    <x-icon name="o-gift" class="h-4 w-4" />
                    Offrir cette consommation
                </button>
            </div>

            {{-- Consommation offerte : la raison est obligatoire, elle part en feuille de caisse. --}}
            <div x-show="offered" x-cloak x-collapse>
                <form method="POST" action="{{ route('bar.payment.pay', $order) }}"
                    class="border-base-300 mt-3 flex flex-wrap items-end gap-2.5 rounded-lg border border-dashed p-3">
                    @csrf
                    <input type="hidden" name="method" value="offered">

                    <div class="min-w-[12rem] flex-1">
                        <label class="label" for="offered-reason">
                            <span class="label-text text-xs font-semibold">Raison</span>
                        </label>
                        <input id="offered-reason" type="text" name="reason" required
                            class="input input-bordered tap-comfort w-full"
                            placeholder="ex. arbitres du match">
                    </div>

                    <button type="submit" class="btn btn-secondary tap-comfort gap-2">
                        <x-icon name="o-check" class="h-4 w-4" />
                        Confirmer
                    </button>
                </form>
            </div>

        </x-card>

        {{--
            Le QR s'ouvre en modale. Rendu dans le flux, il poussait « Paiement
            reçu » sous la ligne de flottaison : il fallait faire défiler pendant
            que la personne attend, téléphone en main.

            Deux détails sans lesquels la boîte ne s'affiche pas ici :

            - `x-data="{ open: true }"` : maryUI émet x-trap="open" et
              x-bind:inert="!open" sur toute modale. Cet état vient normalement
              d'un wire:model ; cette page n'a pas de composant Livewire, donc
              c'est Alpine qui le fournit. Sans lui, l'expression est indéfinie.

            - `showModal()` plutôt que l'attribut open : la boîte est en
              position:fixed, mais le drawer du layout porte un transform, ce qui
              en fait le bloc conteneur de tout descendant fixe. Ouverte
              autrement, elle se posait 5 400 px plus bas et étirait la page à
              18 000 px. Le top layer échappe à ce transform, et rend au passage
              le voile, le piège de focus et la fermeture par Échap.

            Les deux boutons vivent chacun dans leur formulaire : `method="dialog"`
            ferme nativement, le POST enregistre le paiement.
        --}}
        @if ($method === 'qr' && $qrCode)
            <x-app-modal
                id="bar-qr-modal"
                title="Payer par QR code"
                :subtitle="'Commande #' . $order->id"
                :open="true"
                separator
                x-data="{ open: true }"
                x-init="$el.showModal()">

                <div class="flex flex-col items-center gap-4">
                    <img src="{{ $qrCode }}" alt="QR code de paiement"
                        class="bg-base-100 border-base-300 h-56 w-56 rounded-xl border p-3">
                    <p class="text-4xl font-black tabular-nums tracking-tight">{{ euros($order->total_price) }}</p>
                    <p class="text-muted text-center text-sm">Présentez ce code au client.</p>
                </div>

                <x-slot:actions>
                    <form method="dialog">
                        <button class="btn btn-ghost tap-comfort">Fermer</button>
                    </form>
                    <form method="POST" action="{{ route('bar.payment.pay', $order) }}">
                        @csrf
                        <input type="hidden" name="method" value="qr">
                        <button type="submit" class="btn btn-primary tap-comfort gap-2">
                            <x-icon name="o-check" class="h-4 w-4" />
                            Paiement reçu
                        </button>
                    </form>
                </x-slot:actions>
            </x-app-modal>
        @endif

    </div>
</x-app-layout>
