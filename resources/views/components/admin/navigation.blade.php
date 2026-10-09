@php
    $unreadNotificationsCount = auth()->user()->unreadNotifications()->count();
    // Every counter below is a task of this reader's, counted once with the
    // dashboard's pills: see PendingTasks. One colour for all of them —
    // « something is waiting for you » — held by MenuCountersTest.
    $pendingTasks = app(\App\Services\ClubAdmin\Dashboard\PendingTasks::class);
    $badge = fn (string ...$keys): ?string => $pendingTasks->badge(auth()->user(), ...$keys);
@endphp

<x-menu activate-by-route class="mt-10">
    <x-menu-sub icon="o-user" title="{{ $user->first_name }}" open>
        {{-- L'avatar et l'email s'affichent mieux ici dans un menu-item spécial ou le titre du sub-menu --}}
        <x-slot:title>
            <div class="flex items-center gap-3">
                <div class="overflow-hidden truncate">
                    <div class="truncate font-bold">{{ $user->first_name }}</div>
                    {{-- The only string here that belongs to the member rather
                    than to the interface, so it is the only one allowed to
                    truncate: see SidebarLabelTest. --}}
                    <div data-user-email class="truncate text-xs text-muted">{{ $user->email }}</div>
                </div>
            </div>
        </x-slot:title>

        {{-- Four groups, from the most used to the rarest: who I am and when
             I play; my life at the club in the order it happens (affiliate,
             sign up, join a team, play); the money; the settings. Split by
             separators, not headings — MemberSpaceMenuOrderTest holds it. --}}
        <x-menu-item icon="o-user" link="{{ route('admin.user.profile', $user) }}"
            :title="__('My profile')" />
        <x-menu-item icon="o-calendar-days" link="{{ route('admin.user.calendar', $user) }}" :title="__('My Calendar')" />

        <li data-menu-group="separator-club"><x-menu-separator /></li>
        <x-menu-item icon="o-academic-cap" link="{{ route('admin.user.registration-management', $user) }}" :title="__('My season')" />
        <x-menu-item icon="o-star" link="{{ route('admin.user.event-subscription', $user) }}" :title="__('My registrations')" />
        <x-menu-item icon="o-users" link="{{ route('admin.user.teams', $user) }}" :title="__('My team(s)')" />
        @feature('interclubs')
        {{-- Team membership, not the competitive licence alone: see
             User::playsInterclub(). --}}
        @if($user->playsInterclub())
            <x-menu-item icon="o-calendar" link="{{ route('admin.interclubs.my-matches') }}" :title="__('My matches')" />
        @endif
        {{-- Interclub and official tournaments: a member who only plays
             tournaments has results too. --}}
        @if($user->playsInterclub() || $user->hasOfficialTournamentMatches())
            <x-menu-item icon="o-trophy" link="{{ route('admin.user.results', $user) }}" :title="__('My results')" />
        @endif
        @endfeature

        <li data-menu-group="separator-money"><x-menu-separator /></li>
        <x-menu-item icon="o-credit-card" link="{{ route('admin.user.payments', $user) }}" :title="__('My payments')"
            :badge="$badge('my_payments')" badge-classes="badge-warning" />
        @feature('expense_reports')
        @can('create', \App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport::class)
            <x-menu-item icon="o-receipt-percent" link="{{ route('admin.user.expense-reports', $user) }}" :title="__('My expense reports')" />
        @endcan
        @endfeature
        {{-- Follows an affiliation validated and paid: it sits with the money. --}}
        @feature('attestations')
        <x-menu-item icon="o-document-check" link="{{ route('admin.user.attestation', $user) }}" :title="__('Mutual attestation')" />
        @endfeature

        <li data-menu-group="separator-settings"><x-menu-separator /></li>
        <x-menu-item icon="o-cog-8-tooth" :link="route('admin.user.settings', $user)" :title="__('Settings')" />
        <li><x-menu-separator /></li>
        {{-- The proxy a guardian holds over the accounts of their wards: see
             App\Support\AccountProxy. Renders nothing for a member with none. --}}
        <livewire:actions.act-for />
        <livewire:actions.logout />
    </x-menu-sub>

    <li><x-menu-separator /></li>

    <x-menu-item
        icon="o-home"
        link="{{ route('dashboard') }}"
        :title="__('Dashboard')"
    />
    <x-menu-item
    icon="o-bell"
    link="{{ route('notifications.index') }}"
    :title="__('Notifications')"
    :badge="$unreadNotificationsCount > 0 ? (string) $unreadNotificationsCount : null"
    badge-classes="badge-warning"
    />

    <li data-menu-group="separator-people"><x-menu-separator /></li>

    {{-- Mirrors the gate in the directory and « Who does what » components:
         affiliated members, plus the committee members who do not play. --}}
    @if($user->is_active || auth()->user()->can('users.view'))
    <x-menu-item
        icon="o-users"
        link="{{ route('admin.user.directory', auth()->user()) }}"
        :title="__('Member directory')"
    />
    <x-menu-item
        icon="o-lifebuoy"
        link="{{ route('admin.user.who-does-what', auth()->user()) }}"
        :title="__('Who does what')"
    />
    @endif

    <x-menu-item
        icon="o-chat-bubble-left-ellipsis"
        link="{{ route('admin.user.feedback', auth()->user()) }}"
        :title="__('Your feedback')"
    />

    <li data-menu-group="separator-reference"><x-menu-separator /></li>

    <x-menu-item
        icon="o-book-open"
        link="{{ route('admin.user.reglement', auth()->user()) }}"
        :title="__('Rules & regulations')"
    />

    <x-menu-item
        icon="o-hand-raised"
        link="{{ route('admin.user.charter', auth()->user()) }}"
        :title="__('Club charter')"
    />

    <x-menu-item
        icon="o-document-text"
        link="{{ route('admin.user.assembly-minutes', auth()->user()) }}"
        :title="__('Assembly minutes')"
    />

    @feature('help_centre')
    <x-menu-item
        icon="o-question-mark-circle"
        link="{{ route('admin.help.index') }}"
        :title="__('Help')"
    />
    @endfeature


    <li><x-menu-separator /></li>

    @canany(['club.view', 'seasons.view', 'facilities.view'])
    <x-menu-sub icon="o-building-office" :title="__('Club Settings')">
        @can('club.view')
        <x-menu-item icon="o-identification" link="{{ route('admin.club-info') }}" :title="__('Informations')" />
        @endcan
        @can('seasons.view')
        <x-menu-item icon="o-calendar" link="{{ route('admin.seasons.index') }}" :title="__('Seasons')" />
        @endcan
        @can('facilities.view')
        <x-menu-item icon="o-building-office-2" link="{{ route('admin.rooms.index') }}" :title="__('Rooms')" />
        @endcan
        @can('facilities.view')
        <x-menu-item icon="o-key" link="{{ route('admin.key-rings.index') }}" :title="__('Key rings')" />
        @endcan
    </x-menu-sub>
    @endcanany

    @canany(['users.view', 'subscriptions.view', 'users.update', 'access.manage', 'communications.send', 'feedback.view'])
    @php
        // Three groups — the people, the season, the exchange with the members —
        // and a separator only between two groups this reader actually sees.
        $seesPeople = auth()->user()->can('users.view');
        $seesSeason = auth()->user()->can('subscriptions.view')
            || (\App\Domains\Shared\Enums\Feature::Attestations->enabled() && auth()->user()->can('attestations.view'));
        $seesExchange = auth()->user()->canAny(['communications.send', 'feedback.view']);
    @endphp
    <x-menu-sub icon="o-user-group">
        <x-slot:title><x-admin.menu-sub-title :title="__('Members Admin')" :badge="$badge('affiliations', 'feedback')" /></x-slot:title>
        @if ($seesPeople)
            <x-menu-item icon="o-users" link="{{ route('admin.users.index') }}" :title="__('Users')" />
            <x-menu-item icon="o-key" link="{{ route('admin.users.delegations') }}" :title="__('Delegations')" />
        @endif

        @if ($seesPeople && $seesSeason)
            <li data-menu-group="separator-members-season"><x-menu-separator /></li>
        @endif
        @can('subscriptions.view')
            <x-menu-item icon="o-list-bullet" link="{{ route('admin.users.registrations') }}" :title="__('Affiliations')"
                :badge="$badge('affiliations')" badge-classes="badge-warning" />
            <x-menu-item icon="o-clipboard-document-list" link="{{ route('admin.subscriptions.roster') }}" :title="__('Season roster')" />
        @endcan
        @feature('attestations')
        @can('attestations.view')
            <x-menu-item icon="o-document-check" link="{{ route('admin.attestations.index') }}" :title="__('Mutual attestations')" />
        @endcan
        @endfeature

        @if (($seesPeople || $seesSeason) && $seesExchange)
            <li data-menu-group="separator-members-exchange"><x-menu-separator /></li>
        @endif
        @can('communications.send')
            <x-menu-item icon="o-envelope" link="{{ route('admin.communications.index') }}" :title="__('Communications')" />
        @endcan
        @can('feedback.view')
            <x-menu-item icon="o-chat-bubble-left-ellipsis" link="{{ route('admin.feedback.index') }}" :title="__('Feedback and suggestions')"
                :badge="$badge('feedback')" badge-classes="badge-warning" />
        @endcan
    </x-menu-sub>
    @endcanany

    @feature('treasury', 'cash_register')
    @canany(['financial_report.view', 'payments.view', 'fines.view', 'transactions.view', 'cash_register.view'])
    <x-menu-sub icon="o-banknotes">
        <x-slot:title><x-admin.menu-sub-title :title="__('Treasury')" :badge="$badge('expense_reports_to_archive', 'transactions', 'expense_reports')" /></x-slot:title>
        {{-- Three groups, following the money from the whole to the detail:
             the report; the money that moves (bank, till) and what justifies
             it; what is owed — by the members, to the members, and the fines
             the club only follows. Split by separators, drawn only between
             two groups that show something: see TreasuryMenuOrderTest. --}}
        @php
            $treasuryOn = \App\Domains\Shared\Enums\Feature::Treasury->enabled();
            $seesReport = $treasuryOn && auth()->user()->can('financial_report.view');
            $seesTransactions = $treasuryOn && auth()->user()->can('transactions.view');
            $seesCash = \App\Domains\Shared\Enums\Feature::CashRegister->enabled() && auth()->user()->can('cash_register.view');
            $seesPayments = $treasuryOn && auth()->user()->can('payments.view');
            $seesExpenseReports = $seesPayments && \App\Domains\Shared\Enums\Feature::ExpenseReports->enabled();
            $seesFines = $treasuryOn && auth()->user()->can('fines.view');
            $seesAccounts = $seesTransactions || $seesCash;
            $seesDues = $seesPayments || $seesFines;
        @endphp

        @if ($seesReport)
            {{-- Archiving the paid expense reports is downloading a year's ZIP from the report. --}}
            <x-menu-item icon="o-presentation-chart-bar" link="{{ route('admin.treasury.report') }}" :title="__('Financial report')"
                :badge="$badge('expense_reports_to_archive')" badge-classes="badge-warning" />
        @endif

        @if ($seesReport && $seesAccounts)
            <li data-menu-group="separator-treasury-accounts"><x-menu-separator /></li>
        @endif
        @if ($seesTransactions)
            <x-menu-item icon="o-building-library" link="{{ route('admin.treasury.transactions') }}" :title="__('Bank Transactions')"
                :badge="$badge('transactions')" badge-classes="badge-warning" />
        @endif
        @if ($seesCash)
            <x-menu-item icon="o-currency-euro" link="{{ route('admin.treasury.cash') }}" :title="__('Cash Register')" />
        @endif
        @if ($seesTransactions)
            <x-menu-item icon="o-document-check" link="{{ route('admin.treasury.supporting-documents') }}" :title="__('Supporting documents')" />
        @endif

        @if (($seesReport || $seesAccounts) && $seesDues)
            <li data-menu-group="separator-treasury-dues"><x-menu-separator /></li>
        @endif
        @if ($seesPayments)
            <x-menu-item icon="o-credit-card" link="{{ route('admin.treasury.payments') }}" :title="__('Payments')" />
        @endif
        @if ($seesExpenseReports)
            <x-menu-item icon="o-receipt-percent" link="{{ route('admin.treasury.expense-reports') }}" :title="__('Expense reports')"
                :badge="$badge('expense_reports')" badge-classes="badge-warning" />
        @endif
        @if ($seesFines)
            <x-menu-item icon="o-scale" link="{{ route('admin.treasury.fines') }}" :title="__('Fines')" />
        @endif
    </x-menu-sub>
    @endcanany
    @endfeature

    {{--
        Le Bar. Ses six écrans vivaient dans une application à part, avec son
        propre en-tête de navigation ; ils sont ici au même rang que Trésorerie.

        Les libellés disent ce que fait l'écran plutôt que ce qu'il s'appelait :
        « Commande » et « Commandes » côte à côte dans une barre latérale ne se
        distinguent pas, et la seconde liste ne contient que les commandes
        impayées — c'est une file d'encaissement, pas un historique.

        Chaque entrée reprend le verrou de sa route (routes/bar.php) : sans ça,
        un barman voit trois liens qui mènent à un 403.
    --}}
    @feature('bar')
    {{-- Le sous-menu s'ouvre aussi à qui ne lit que les ventes (le comité) : il n'y
         voit alors que « Ventes », les entrées du comptoir restant sous bar.access. --}}
    @canany(['bar.access', 'bar.stats.view'])
    {{--
        `exact` sur les entrées dont le chemin en préfixe une autre : maryUI allume
        une entrée dès que l'URL courante COMMENCE par son lien, et les chemins du
        bar s'emboîtent (`/bar`, `/bar/orders`, `/bar/orders/history`). Trois
        entrées s'allumaient ensemble sur l'historique, et « Nouvelle commande »
        sur toutes les pages du bar — un menu qui désigne trois écrans à la fois
        n'en désigne aucun.

        Les sous-écrans gardent allumée la liste d'où l'on vient : encaisser ou
        modifier une commande, c'est encore être dans la file d'encaissement.
    --}}
    @php
        // The counter, then the stock: a separator only between two groups this
        // reader actually sees.
        $seesCounter = auth()->user()->canAny(['bar.access', 'bar.cash_sheet.send']);
        $seesStock = auth()->user()->canAny(['bar.products.manage', 'bar.categories.manage', 'bar.restocking.shop', 'bar.stats.view']);
    @endphp
    <x-menu-sub icon="o-shopping-bag">
        <x-slot:title><x-admin.menu-sub-title :title="__('Bar')" :badge="$badge('bar_tabs', 'bar_shopping')" /></x-slot:title>
        {{-- At the counter, in the order of an evening. --}}
        @can('bar.access')
        <x-menu-item
            icon="o-shopping-bag"
            link="{{ route('bar.index') }}"
            :title="__('New order')"
            exact
            :active="request()->routeIs('bar.cart.show')" />
        <x-menu-item
            icon="o-banknotes"
            link="{{ route('bar.orders.index') }}"
            :title="__('To cash in')"
            :badge="$badge('bar_tabs')"
            badge-classes="badge-warning"
            exact
            :active="request()->routeIs('bar.payment.*', 'bar.orders.modify')" />
        <x-menu-item icon="o-clock" link="{{ route('bar.orders.history') }}" :title="__('History')" />
        @endcan
        @can('bar.cash_sheet.send')
        <x-menu-item icon="o-document-chart-bar" link="{{ route('bar.cashSheet.index') }}" :title="__('Cash sheet')" />
        @endcan

        {{-- The stock, in the order of its life: the catalogue, what comes in,
             what is counted, what went out. --}}
        @if ($seesCounter && $seesStock)
            <li data-menu-group="separator-bar-stock"><x-menu-separator /></li>
        @endif
        @can('bar.products.manage')
        <x-menu-item icon="o-cube" link="{{ route('bar.products.index') }}" :title="__('Products')" />
        @endcan
        @can('bar.categories.manage')
        <x-menu-item icon="o-tag" link="{{ route('bar.categories.index') }}" :title="__('Categories')" />
        @endcan
        @can('bar.restocking.shop')
        <x-menu-item icon="o-shopping-cart" link="{{ route('bar.restocking.index') }}" :title="__('Shopping')"
            :badge="$badge('bar_shopping')" badge-classes="badge-warning" />
        @endcan
        @can('bar.stats.view')
        <x-menu-item icon="o-clipboard-document-check" link="{{ route('bar.inventories.index') }}" :title="__('Inventories')"
            :active="request()->routeIs('bar.inventories.*')" />
        <x-menu-item icon="o-chart-bar" link="{{ route('bar.stats.index') }}" :title="__('Stock outflows')" />
        @endcan
        {{--
            De quoi installer la salle avant le service : l'écran à caster derrière
            le comptoir, la page que les clients ouvrent en scannant, et la feuille
            à poser sur les tables.

            Trois liens et non un écran de plus : ces pages sont publiques, elles
            n'ont rien à administrer. `external` les ouvre dans un nouvel onglet —
            caster le back-office à la place de la carte laisserait le barman sans
            caisse, devant une salle qui attend.
        --}}
        @can('bar.access')
        <x-menu-separator :title="__('Menu')" />
        <x-menu-item icon="o-tv" link="{{ route('public.bar.screen') }}" :title="__('Cast the menu')" external />
        <x-menu-item icon="o-device-phone-mobile" link="{{ route('public.bar.menu') }}" :title="__('Menu on a phone')" external exact />
        <x-menu-item icon="o-printer" link="{{ route('public.bar.flyer') }}" :title="__('Print the QR sheets')" external />
        @endcan
    </x-menu-sub>
    @endcanany
    @endfeature

    <li><x-menu-separator /></li>

    @feature('trainings')
    {{-- Le Gate, pas la permission nue : encadrer un pack ou une séance ouvre
         l'espace coach au même titre que la délégation, sinon un entraîneur a
         l'accès sans avoir le lien pour y aller. --}}
    @canany(['trainings.view', 'access-coach-area'])
    <x-menu-sub icon="o-academic-cap">
        <x-slot:title><x-admin.menu-sub-title :title="__('Trainings')" :badge="$badge('sessions_to_record', 'camp_requests')" /></x-slot:title>
        @can('trainings.view')
        <x-menu-item icon="o-tag" link="{{ route('admin.trainings.index') }}" :title="__('Training Packs')"
            :badge="$badge('camp_requests')" badge-classes="badge-warning" />
        @endcan
        @feature('training_planning')
        @can('trainings.view')
        <x-menu-item icon="o-view-columns" link="{{ route('admin.planning.board') }}" :title="__('Planning board')" />
        @endcan
        @endfeature
        @can('access-coach-area')
        <x-menu-item icon="o-calendar-days" link="{{ route('coach.trainings') }}" :title="__('My sessions')"
            :badge="$badge('sessions_to_record')" badge-classes="badge-warning" />
        @endcan
    </x-menu-sub>
    @endcanany
    @endfeature

    @feature('interclubs')
    {{-- A captain is a relation (teams.captain_id), never a délégation: the
         access-selections / access-results Gates say so, and the menu has to
         ask the same question, or a captain reaches their own screens by URL
         only. The season configuration below stays permission-gated. --}}
    @if (Gate::any(['access-selections', 'access-results']) || $user->can('interclubs.view'))
    <x-menu-sub icon="o-calendar-days" link="#">
        <x-slot:title><x-admin.menu-sub-title :title="__('Interclubs')" :badge="$badge('lineups_to_send')" /></x-slot:title>
        @can('access-selections')
        <x-menu-item icon="o-user-group" link="{{ route('admin.interclubs.captain-selection') }}" :title="__('Selections')"
            :badge="$badge('lineups_to_send')" badge-classes="badge-warning" />
        @endcan
        @can('access-results')
        <x-menu-item icon="o-squares-2x2" link="{{ route('admin.interclubs.results') }}" :title="__('Results')" />
        @endcan
        {{-- Readable at the committee baseline; only the division setup is a
             tool with nothing to read, and stays with the délégation. --}}
        @can('interclubs.view')
        <x-menu-item icon="o-calendar-days" link="{{ route('admin.interclubs.interclubs') }}" :title="__('Planning')" />
        {{-- Two levels of indent leave 156px for a label. "Interclubs" already
        names the section this sits in, so the season goes without saying. --}}
        <x-menu-sub icon="o-cog-6-tooth" :title="__('Configuration')">
            <x-menu-item icon="o-identification" link="{{ route('admin.interclubs.teams') }}" :title="__('Our teams')" />
            @can('interclubs.manage')
            <x-menu-item icon="o-table-cells" link="{{ route('admin.interclubs.division-setup') }}" :title="__('Opponents')" />
            @endcan
            <x-menu-item icon="o-building-office-2" link="{{ route('admin.interclubs.clubs') }}" :title="__('Clubs')" />
        </x-menu-sub>
        @endcan
    </x-menu-sub>
    @endif
    @endfeature

    @feature('meetings', 'tournaments')
    @canany(['meetings.view', 'tournaments.view'])
    <x-menu-sub icon="o-star">
        <x-slot:title><x-admin.menu-sub-title :title="__('Events')" :badge="$badge('meetings_to_close', 'tournaments_to_close')" /></x-slot:title>
        @feature('meetings')
        @can('meetings.view')
        <x-menu-item icon="o-calendar-days" link="{{ route('admin.meetings.index') }}" :title="__('Meetings')"
            :badge="$badge('meetings_to_close')" badge-classes="badge-warning" />
        @endcan
        @endfeature
        @feature('tournaments')
        @can('tournaments.view')
        <x-menu-item icon="o-trophy" link="{{ route('admin.tournaments.index') }}" :title="__('Tournaments')"
            :badge="$badge('tournaments_to_close')" badge-classes="badge-warning" />
        @endcan
        @endfeature
    </x-menu-sub>
    @endcanany
    @endfeature

    @feature('website', 'contacts')
    @canany(['news_posts.view', 'contacts.view', 'contacts.manage', 'spams.manage', 'event_posts.manage'])
    <x-menu-sub icon="o-globe-alt">
        <x-slot:title><x-admin.menu-sub-title :title="__('Website')" :badge="$badge('draft_articles', 'contacts')" /></x-slot:title>
        @feature('website')
        @can('news_posts.view')
        <x-menu-item icon="o-newspaper" link="{{ route('admin.website.articles.index') }}" :title="__('Articles')"
            :badge="$badge('draft_articles')" badge-classes="badge-warning" />
        @endcan
        @endfeature
        @feature('contacts')
        @can('contacts.view')
        <x-menu-item icon="o-envelope-open" link="{{ route('admin.website.contacts.index') }}" :title="__('Contacts')"
            :badge="$badge('contacts')" badge-classes="badge-warning" />
        @endcan
        @can('contacts.manage')
            <x-menu-item icon="o-document-text" link="{{ route('admin.website.contacts.email-templates') }}" :title="__('Email templates')" />
        @endcan
        @can('spams.manage')
        <x-menu-item icon="o-shield-exclamation" link="{{ route('admin.website.spams.index') }}" :title="__('Spam')" />
        @endcan
        @endfeature
        @feature('website')
        @can('news_posts.view')
        <x-menu-item icon="o-calendar-days" link="{{ route('admin.website.events.index') }}" :title="__('Events')" />
        @endcan
        @endfeature
    </x-menu-sub>
    @endcanany
    @endfeature

    @feature('supervision')
    @if($user->canViewAuditLog())
    <li><x-menu-separator /></li>
    <x-menu-item
        icon="o-magnifying-glass"
        link="{{ route('admin.audit.index') }}"
        :title="__('Audit')"
    />
    @endif
    @endfeature

    @feature('supervision')
    @can('view-queue-monitoring')
    {{-- The full "Queue monitoring" was one pixel too wide, which cost it four
    characters and an ellipsis. The screen keeps the long title. --}}
    <x-menu-item
        icon="o-queue-list"
        link="{{ route('admin.queue.index') }}"
        :title="__('Job queue')"
        :badge="$badge('failed_jobs')"
        badge-classes="badge-warning"
    />
    @endcan
    @endfeature

</x-menu>