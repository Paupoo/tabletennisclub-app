@php $unreadNotificationsCount = auth()->user()->unreadNotifications()->count(); @endphp

<x-menu activate-by-route class="mt-10">
    <x-menu-sub icon="o-user" title="{{ $user->first_name }}">
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

        <x-menu-item icon="o-user" link="{{ route('admin.user.profile', $user) }}"
            :title="__('My profile')" />
        @feature('interclubs')
        {{-- Team membership, not the competitive licence alone: see
             User::playsInterclub(). --}}
        @if($user->playsInterclub())
            <x-menu-item icon="o-calendar" link="{{ route('admin.interclubs.my-matches') }}" :title="__('My matches')" />
            <x-menu-item icon="o-trophy" link="{{ route('admin.user.interclub-record', $user) }}" :title="__('My interclub record')" />
        @endif
        @endfeature
        <x-menu-item icon="o-users" link="{{ route('admin.user.teams', $user) }}" :title="__('My team(s)')" />
        <x-menu-item icon="o-star" link="{{ route('admin.user.event-subscription', $user) }}" :title="__('My registrations')" />
        <x-menu-item icon="o-credit-card" link="{{ route('admin.user.payments', $user) }}" :title="__('My payments')" />
        @feature('expense_reports')
        @can('create', \App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport::class)
            <x-menu-item icon="o-receipt-percent" link="{{ route('admin.user.expense-reports', $user) }}" :title="__('My expense reports')" />
        @endcan
        @endfeature
        <x-menu-item icon="o-calendar-days" link="{{ route('admin.user.calendar', $user) }}" :title="__('My Calendar')" />
        <x-menu-item icon="o-academic-cap" link="{{ route('admin.user.registration-management', $user) }}" :title="__('My season')" />
        @feature('attestations')
        <x-menu-item icon="o-document-check" link="{{ route('admin.user.attestation', $user) }}" :title="__('Mutual attestation')" />
        @endfeature
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
    badge-classes="badge-error"
    />

    {{-- Mirrors the gate in the directory component: affiliated members, plus
         the committee members who do not play. --}}
    @if($user->is_active || auth()->user()->can('users.view'))
    <x-menu-item
        icon="o-users"
        link="{{ route('admin.user.directory', auth()->user()) }}"
        :title="__('Member directory')"
    />
    @endif

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

    @feature('help_centre')
    <x-menu-item
        icon="o-question-mark-circle"
        link="{{ route('admin.help.index') }}"
        :title="__('Help')"
    />
    @endfeature


    <li><x-menu-separator /></li>

    @canany(['club.update', 'seasons.view', 'facilities.view'])
    <x-menu-sub icon="o-building-office" :title="__('Club Settings')">
        @can('club.update')
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

    @canany(['users.view', 'subscriptions.view', 'users.update', 'access.manage', 'trainings.view'])
    <x-menu-sub icon="o-user-group" :title="__('Members Admin')">
        @can('users.view')
            <x-menu-item icon="o-users" link="{{ route('admin.users.index') }}" :title="__('Users')" />
        @endcan
        @can('subscriptions.view')
            <x-menu-item icon="o-list-bullet" link="{{ route('admin.users.registrations') }}" :title="__('Affiliations')" />
        @endcan
        @feature('attestations')
        @can('attestations.view')
            <x-menu-item icon="o-document-check" link="{{ route('admin.attestations.index') }}" :title="__('Mutual attestations')" />
        @endcan
        @endfeature
        @can('users.view')
            <x-menu-item icon="o-key" link="{{ route('admin.users.delegations') }}" :title="__('Delegations')" />
        @endcan
        @can('subscriptions.view')
            <x-menu-item icon="o-clipboard-document-list" link="{{ route('admin.subscriptions.roster') }}" :title="__('Season roster')" />
        @endcan
        @feature('training_planning')
        @can('trainings.view')
        <x-menu-item icon="o-view-columns" link="{{ route('admin.planning.board') }}" :title="__('Planning board')" />
        @endcan
        @endfeature
    </x-menu-sub>
    @endcanany

    @feature('treasury', 'cash_register')
    @canany(['payments.view', 'fines.view', 'transactions.view', 'cash_register.view'])
    <x-menu-sub icon="o-banknotes" :title="__('Treasury')">
        @feature('treasury')
        @can('payments.view')
            <x-menu-item icon="o-credit-card" link="{{ route('admin.treasury.payments') }}" :title="__('Payments')" />
        @endcan
        @feature('expense_reports')
        @can('payments.view')
            @php
                $expenseReportsToDecide = auth()->user()->can('expense_reports.process')
                    ? \App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport::where('status', 'submitted')->where('user_id', '!=', auth()->id())->count()
                    : 0;
            @endphp
            <x-menu-item icon="o-receipt-percent" link="{{ route('admin.treasury.expense-reports') }}" :title="__('Expense reports')"
                :badge="$expenseReportsToDecide > 0 ? (string) $expenseReportsToDecide : null" badge-classes="badge-warning" />
        @endcan
        @endfeature
        @can('fines.view')
            <x-menu-item icon="o-scale" link="{{ route('admin.treasury.fines') }}" :title="__('Fines')" />
        @endcan
        @can('transactions.view')
            <x-menu-item icon="o-building-library" link="{{ route('admin.treasury.transactions') }}" :title="__('Bank Transactions')" />
        @endcan
        @endfeature
        @feature('cash_register')
        @can('cash_register.view')
            <x-menu-item icon="o-currency-euro" link="{{ route('admin.treasury.cash') }}" :title="__('Cash Register')" />
        @endcan
        @endfeature
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
    @can('bar.access')
    @php
        // Panier de session : un simple array_sum, aucune requête. Le cast en
        // array est une assurance, pas une coquetterie — ce menu est rendu sur
        // toutes les pages du back-office, et une session malformée y ferait
        // un 500 global au lieu d'une page du Bar en erreur.
        $barCartCount = array_sum(array_map(intval(...), (array) session('cart', [])));
    @endphp
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
    <x-menu-sub icon="o-shopping-bag" :title="__('Bar')">
        <x-menu-item
            icon="o-shopping-bag"
            link="{{ route('bar.index') }}"
            :title="__('New order')"
            :badge="$barCartCount > 0 ? (string) $barCartCount : null"
            badge-classes="badge-primary"
            exact
            :active="request()->routeIs('bar.cart.show')" />
        <x-menu-item
            icon="o-banknotes"
            link="{{ route('bar.orders.index') }}"
            :title="__('To cash in')"
            exact
            :active="request()->routeIs('bar.payment.*', 'bar.orders.modify')" />
        <x-menu-item icon="o-clock" link="{{ route('bar.orders.history') }}" :title="__('History')" />
        @can('bar.products.manage')
        <x-menu-item icon="o-cube" link="{{ route('bar.products.index') }}" :title="__('Products')" />
        @endcan
        @can('bar.categories.manage')
        <x-menu-item icon="o-tag" link="{{ route('bar.categories.index') }}" :title="__('Categories')" />
        @endcan
        @can('bar.cash_sheet.send')
        <x-menu-item icon="o-document-chart-bar" link="{{ route('bar.cashSheet.index') }}" :title="__('Cash sheet')" />
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
        <x-menu-separator :title="__('Menu')" />
        <x-menu-item icon="o-tv" link="{{ route('public.bar.screen') }}" :title="__('Cast the menu')" external />
        <x-menu-item icon="o-device-phone-mobile" link="{{ route('public.bar.menu') }}" :title="__('Menu on a phone')" external exact />
        <x-menu-item icon="o-printer" link="{{ route('public.bar.flyer') }}" :title="__('Print the QR sheets')" external />
    </x-menu-sub>
    @endcan
    @endfeature

    <li><x-menu-separator /></li>

    @feature('trainings')
    {{-- Le Gate, pas la permission nue : encadrer un pack ou une séance ouvre
         l'espace coach au même titre que la délégation, sinon un entraîneur a
         l'accès sans avoir le lien pour y aller. --}}
    @canany(['trainings.view', 'access-coach-area'])
    <x-menu-sub icon="o-academic-cap" :title="__('Trainings')">
        @can('trainings.view')
        <x-menu-item icon="o-tag" link="{{ route('admin.trainings.index') }}" :title="__('Training Packs')" />
        @endcan
        @can('access-coach-area')
        <x-menu-item icon="o-calendar-days" link="{{ route('coach.trainings') }}" :title="__('My sessions')" />
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
    <x-menu-sub icon="o-calendar-days" link="#" :title="__('Interclubs')">
        @can('access-selections')
        <x-menu-item icon="o-user-group" link="{{ route('admin.interclubs.captain-selection') }}" :title="__('Selections')" />
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
    <x-menu-sub icon="o-star" :title="__('Events')">
        @feature('meetings')
        @can('meetings.view')
        <x-menu-item icon="o-calendar-days" link="{{ route('admin.meetings.index') }}" :title="__('Meetings')" />
        @endcan
        @endfeature
        @feature('tournaments')
        @can('tournaments.view')
        <x-menu-item icon="o-trophy" link="{{ route('admin.tournaments.index') }}" :title="__('Tournaments')" />
        @endcan
        @endfeature
    </x-menu-sub>
    @endcanany
    @endfeature

    @feature('website', 'contacts')
    @canany(['news_posts.view', 'contacts.view', 'contacts.manage', 'spams.manage', 'event_posts.manage'])
    <x-menu-sub icon="o-globe-alt" :title="__('Website')">
        @feature('website')
        @can('news_posts.view')
        <x-menu-item icon="o-newspaper" link="{{ route('admin.website.articles.index') }}" :title="__('Articles')" />
        @endcan
        @endfeature
        @feature('contacts')
        @can('contacts.view')
        <x-menu-item icon="o-envelope-open" link="{{ route('admin.website.contacts.index') }}" :title="__('Contacts')" />
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
    />
    @endcan
    @endfeature

</x-menu>