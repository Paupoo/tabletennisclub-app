@php
    use App\Support\Breadcrumb;

    $trail = Breadcrumb::make()->home()->bar()->current('Feuille de caisse')->toArray();
@endphp

<x-app-layout>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$trail" separator="o-slash" />
    </x-slot:breadcrumbs>

    <div class="space-y-4">

        <div>
            <h1 class="text-2xl font-bold tracking-tight">Feuille de caisse</h1>
            <p class="text-muted mt-1">Le récapitulatif d'une soirée, à transmettre au trésorier.</p>
        </div>

        {{-- Choix de la date --}}
        <x-card class="shadow-sm">
            <form method="GET" action="{{ route('bar.cashSheet.index') }}" class="flex flex-wrap items-end gap-2.5">
                <div class="min-w-[10rem] flex-1 sm:max-w-[16rem]">
                    <label class="label" for="cashsheet-date">
                        <span class="label-text text-xs font-semibold">Date</span>
                    </label>
                    <input id="cashsheet-date" type="date" name="date" required
                        value="{{ $date ?? now()->toDateString() }}"
                        class="input input-bordered tap-comfort w-full">
                </div>

                <button type="submit" class="btn btn-primary tap-comfort gap-2">
                    <x-icon name="o-calendar-days" class="h-4 w-4" />
                    Afficher
                </button>
            </form>
        </x-card>

        {{-- Chiffres de la journée --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-admin.shared.stat-card
                label="Commandes"
                :value="$summary['orders_total']"
                :hint="$summary['orders_paid'] . ' payées · ' . $summary['orders_unpaid'] . ' non payées'"
                icon="o-archive-box" color="primary" />

            <x-admin.shared.stat-card
                label="Articles vendus"
                :value="$summary['items_total']"
                icon="o-cube" />

            <x-admin.shared.stat-card
                label="Total vendu"
                :value="euros($summary['sold_total_cents'])"
                icon="o-clipboard-document-list" color="primary" />

            <x-admin.shared.stat-card
                label="Total encaissé"
                :value="euros($summary['received_total_cents'])"
                icon="o-banknotes" color="success" />
        </div>

        {{-- Sous-totaux --}}
        <x-card class="shadow-sm">
            <h2 class="text-muted mb-3 text-xs font-bold uppercase tracking-widest">Sous-totaux par méthode</h2>

            @php
                $methods = [
                    ['key' => 'cash', 'label' => 'Cash', 'icon' => 'o-banknotes'],
                    ['key' => 'qr', 'label' => 'QR Code', 'icon' => 'o-qr-code'],
                    ['key' => 'offered', 'label' => 'Offert', 'icon' => 'o-gift'],
                    ['key' => 'other', 'label' => 'Autre', 'icon' => 'o-credit-card'],
                ];
            @endphp

            <div class="divide-base-200 divide-y">
                @foreach ($methods as $method)
                    <div class="flex items-center justify-between py-2.5 first:pt-0">
                        <span class="flex items-center gap-2.5 text-sm">
                            <x-icon :name="$method['icon']" class="text-base-content/50 h-4 w-4" />
                            {{ $method['label'] }}
                        </span>
                        <span class="font-bold tabular-nums">
                            {{ euros($summary['by_method_cents'][$method['key']] ?? 0) }}
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="border-base-300 mt-1 flex items-center justify-between border-t pt-3">
                <span class="text-warning flex items-center gap-2.5 text-sm font-semibold">
                    <x-icon name="o-exclamation-triangle" class="h-4 w-4" />
                    Impayé
                </span>
                <span class="text-warning font-bold tabular-nums">{{ euros($summary['unpaid_total_cents']) }}</span>
            </div>
        </x-card>

        {{-- Envoi --}}
        <x-card class="shadow-sm">
            <h2 class="text-muted mb-3 text-xs font-bold uppercase tracking-widest">Envoyer au trésorier</h2>

            <form method="POST" action="{{ route('bar.cashSheet.send') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">

                <div>
                    <label class="label" for="cashsheet-to">
                        <span class="label-text text-xs font-semibold">Adresse e-mail</span>
                    </label>
                    <input id="cashsheet-to" type="email" name="to" required
                        value="{{ old('to', $defaultTo) }}"
                        placeholder="cttottigniesblocry@gmail.com"
                        class="input input-bordered tap-comfort w-full">
                </div>

                <label class="tap-comfort cursor-pointer justify-start gap-2.5 text-sm font-semibold">
                    <input type="checkbox" name="save_default" value="1" class="toggle toggle-primary">
                    Enregistrer comme adresse par défaut
                </label>

                <button type="submit" class="btn btn-primary tap-comfort w-full gap-2">
                    <x-icon name="o-paper-airplane" class="h-4 w-4" />
                    Envoyer la feuille de caisse
                </button>
            </form>

            <p class="text-subtle mt-3 text-xs">
                L'envoi dépend de la configuration mail du serveur. À défaut, exportez en CSV et envoyez manuellement.
            </p>
        </x-card>

    </div>
</x-app-layout>
