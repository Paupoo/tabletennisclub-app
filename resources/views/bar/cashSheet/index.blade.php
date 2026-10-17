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

        {{-- Messages --}}
        @if (session('success'))
            <div class="alert alert-success" role="status">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning" role="alert">
                {{ session('warning') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-error" role="alert">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Choisir ou parcourir les journées avec de l'activité --}}
        <x-card class="shadow-sm">
            <div class="space-y-4">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                    <div>
                        <h2 class="font-bold">Journées avec de l'activité</h2>
                        <p class="text-subtle mt-1 text-sm">
                            Choisissez une journée récente ou passez directement à la journée active précédente ou suivante.
                        </p>
                    </div>

                    <div class="hidden gap-2 md:flex">
                        @if ($previousActiveDay)
                            <a
                                href="{{ route('bar.cashSheet.index', ['date' => $previousActiveDay]) }}"
                                class="btn btn-outline btn-sm tap-comfort gap-1.5"
                                aria-label="Journée active précédente"
                            >
                                <x-icon name="o-arrow-left" class="h-4 w-4" />
                                Précédente
                            </a>
                        @else
                            <span class="btn btn-outline btn-sm tap-comfort gap-1.5 opacity-50" aria-disabled="true">
                                <x-icon name="o-arrow-left" class="h-4 w-4" />
                                Précédente
                            </span>
                        @endif

                        @if ($nextActiveDay)
                            <a
                                href="{{ route('bar.cashSheet.index', ['date' => $nextActiveDay]) }}"
                                class="btn btn-outline btn-sm tap-comfort gap-1.5"
                                aria-label="Journée active suivante"
                            >
                                Suivante
                                <x-icon name="o-arrow-right" class="h-4 w-4" />
                            </a>
                        @else
                            <span class="btn btn-outline btn-sm tap-comfort gap-1.5 opacity-50" aria-disabled="true">
                                Suivante
                                <x-icon name="o-arrow-right" class="h-4 w-4" />
                            </span>
                        @endif
                    </div>
                </div>

                <form method="GET" action="{{ route('bar.cashSheet.index') }}" class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-end">
                    <div class="min-w-0 sm:w-64">
                        <label class="label" for="cashsheet-date">
                            <span class="label-text text-xs font-semibold">Ou choisir une date</span>
                        </label>
                        <input
                            id="cashsheet-date"
                            type="date"
                            name="date"
                            required
                            max="{{ now()->toDateString() }}"
                            value="{{ $date }}"
                            class="input input-bordered tap-comfort w-full"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary tap-comfort gap-2">
                        <x-icon name="o-calendar-days" class="h-4 w-4" />
                        Afficher cette date
                    </button>
                </form>

                @if ($activityDays === [])
                    <p class="rounded-lg border border-base-300 bg-base-200/50 p-4 text-sm text-subtle">
                        Aucune journée avec activité pour le moment.
                    </p>
                @else
                    <div class="grid grid-cols-2 gap-2 md:hidden" role="group" aria-label="Navigation du carrousel des journées actives">
                        @if ($previousActiveDay)
                            <a
                                href="{{ route('bar.cashSheet.index', ['date' => $previousActiveDay]) }}"
                                class="btn btn-outline btn-sm tap-comfort w-full gap-1.5"
                                aria-label="Journée active précédente"
                                data-cashsheet-carousel-previous
                            >
                                <x-icon name="o-arrow-left" class="h-4 w-4" />
                                Précédente
                            </a>
                        @else
                            <span class="btn btn-outline btn-sm tap-comfort w-full gap-1.5 opacity-50" aria-disabled="true">
                                <x-icon name="o-arrow-left" class="h-4 w-4" />
                                Précédente
                            </span>
                        @endif

                        @if ($nextActiveDay)
                            <a
                                href="{{ route('bar.cashSheet.index', ['date' => $nextActiveDay]) }}"
                                class="btn btn-outline btn-sm tap-comfort w-full gap-1.5"
                                aria-label="Journée active suivante"
                                data-cashsheet-carousel-next
                            >
                                Suivante
                                <x-icon name="o-arrow-right" class="h-4 w-4" />
                            </a>
                        @else
                            <span class="btn btn-outline btn-sm tap-comfort w-full gap-1.5 opacity-50" aria-disabled="true">
                                Suivante
                                <x-icon name="o-arrow-right" class="h-4 w-4" />
                            </span>
                        @endif
                    </div>

                    <div
                        id="cashsheet-active-days"
                        class="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-2.5 xl:grid-cols-4"
                        role="list"
                        aria-label="Journées récentes avec activité"
                        aria-roledescription="carrousel"
                    >
                        @foreach ($activityDays as $activityDay)
                            <a
                                href="{{ route('bar.cashSheet.index', ['date' => $activityDay['date']]) }}"
                                role="listitem"
                                @class([
                                    'w-full rounded-lg border p-3 transition-colors hover:border-primary focus-visible:outline-2 focus-visible:outline-primary md:block md:w-auto',
                                    'block' => $activityDay['date'] === $date,
                                    'hidden' => $activityDay['date'] !== $date,
                                    'border-primary bg-primary/5 ring-1 ring-primary' => $activityDay['date'] === $date,
                                    'border-base-300 bg-base-100' => $activityDay['date'] !== $date,
                                ])
                                @if ($activityDay['date'] === $date) aria-current="date" @endif
                            >
                                <span class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-bold">
                                        {{ \Illuminate\Support\Carbon::parse($activityDay['date'])->translatedFormat('l j F') }}
                                    </span>
                                    @if ($activityDay['date'] === now()->toDateString())
                                        <span class="badge badge-warning badge-sm">Aujourd'hui</span>
                                    @endif
                                </span>

                                <span class="mt-2 block text-xs text-subtle">
                                    @if ($activityDay['orders_total'] > 0)
                                        {{ $activityDay['orders_total'] }} commande(s) · {{ $activityDay['items_total'] }} articles
                                    @else
                                        Aucune commande créée ce jour-là
                                    @endif
                                </span>

                                <span class="mt-1 block text-sm font-bold text-primary">
                                    {{ euros($activityDay['received_total_cents']) }} encaissés
                                </span>

                                <span class="mt-2 flex flex-wrap gap-1.5">
                                    @if ($activityDay['orders_total'] > 0)
                                        <span class="badge badge-info badge-sm">Ventes</span>
                                    @endif
                                    @if ($activityDay['payments_total'] > 0)
                                        <span class="badge badge-success badge-sm">Paiements</span>
                                    @endif
                                </span>
                            </a>
                        @endforeach
                    </div>

                    @unless (collect($activityDays)->contains('date', $date))
                        <p class="rounded-lg border border-base-300 bg-base-200/50 p-4 text-sm text-subtle md:hidden" role="status">
                            Aucune activité enregistrée pour cette date.
                        </p>
                    @endunless

                    <p class="text-subtle text-xs md:hidden">
                        La journée sélectionnée est affichée. Utilisez les flèches pour parcourir les journées actives.
                    </p>

                    <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-subtle" aria-label="Légende">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="badge badge-info badge-sm">Ventes</span>
                            Commandes créées ce jour de caisse
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="badge badge-success badge-sm">Paiements</span>
                            Règlements reçus ce jour de caisse
                        </span>
                    </div>
                @endif
            </div>
        </x-card>

        {{-- Chiffres de la journée --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-admin.shared.stat-card
                label="Commandes"
                :value="$summary['orders_total']"
                :hint="$summary['orders_paid'] . ' payées · ' . $summary['orders_unpaid'] . ' non payées'"
                icon="o-archive-box"
                color="primary"
            />

            <x-admin.shared.stat-card
                label="Articles vendus"
                :value="$summary['items_total']"
                icon="o-cube"
            />

            <x-admin.shared.stat-card
                label="Total vendu"
                :value="euros($summary['sold_total_cents'])"
                icon="o-clipboard-document-list"
                color="primary"
            />

            <x-admin.shared.stat-card
                label="Total encaissé"
                :value="euros($summary['received_total_cents'])"
                icon="o-banknotes"
                color="success"
            />
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
                <span class="text-warning font-bold tabular-nums">
                    {{ euros($summary['unpaid_total_cents']) }}
                </span>
            </div>
        </x-card>

        {{-- Envoi et téléchargement --}}
        <x-card class="shadow-sm">
            <h2 class="text-muted mb-3 text-xs font-bold uppercase tracking-widest">Partager la feuille de caisse</h2>

            <form method="POST" action="{{ route('bar.cashSheet.send') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">

                <div>
                    <label class="label" for="cashsheet-to">
                        <span class="label-text text-xs font-semibold">Adresse e-mail</span>
                    </label>
                    <input
                        id="cashsheet-to"
                        type="email"
                        name="to"
                        required
                        value="{{ old('to', $defaultTo) }}"
                        placeholder="cttottigniesblocry@gmail.com"
                        class="input input-bordered tap-comfort w-full"
                    >
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

            <div class="divider my-3 text-xs">OU</div>

            <a
                href="{{ route('bar.cashSheet.download', ['date' => $date]) }}"
                class="btn btn-outline tap-comfort w-full gap-2"
            >
                <x-icon name="o-arrow-down-tray" class="h-4 w-4" />
                Télécharger en CSV
            </a>

            <p class="text-subtle mt-3 text-xs">
                Envoyez la feuille de caisse par e-mail ou téléchargez-la en CSV pour l'archiver ou l'envoyer manuellement.
            </p>
        </x-card>
    </div>
</x-app-layout>
