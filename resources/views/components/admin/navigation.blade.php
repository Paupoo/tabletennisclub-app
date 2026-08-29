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
        @if($user->is_competitor)
            <x-menu-item icon="o-calendar" link="{{ route('admin.interclubs.my-matches') }}" :title="__('My matches')" />
        @endif
        @endfeature
        <x-menu-item icon="o-users" link="{{ route('admin.user.teams', $user) }}" :title="__('My team(s)')" />
        <x-menu-item icon="o-star" link="{{ route('admin.user.event-subscription', $user) }}" :title="__('My registrations')" />
        <x-menu-item icon="o-credit-card" link="{{ route('admin.user.payments', $user) }}" :title="__('My payments')" />
        <x-menu-item icon="o-calendar-days" link="{{ route('admin.user.calendar', $user) }}" :title="__('My Calendar')" />
        <x-menu-item icon="o-academic-cap" link="{{ route('admin.user.registration-management', $user) }}" :title="__('My season')" />
        <x-menu-item icon="o-cog-8-tooth" :link="route('admin.user.settings', $user)" :title="__('Settings')" />
        <li><x-menu-separator /></li>
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

    @feature('help_centre')
    <x-menu-item
        icon="o-question-mark-circle"
        link="{{ route('admin.help.index') }}"
        :title="__('Help')"
    />
    @endfeature


    <li><x-menu-separator /></li>

    @canany(['club.update', 'seasons.view', 'rooms.manage'])
    <x-menu-sub icon="o-building-office" :title="__('Club Settings')">
        @can('club.update')
        <x-menu-item icon="o-identification" link="{{ route('admin.club-info') }}" :title="__('Informations')" />
        @endcan
        @can('seasons.view')
        <x-menu-item icon="o-calendar" link="{{ route('admin.seasons.index') }}" :title="__('Seasons')" />
        @endcan
        @can('rooms.manage')
        <x-menu-item icon="o-building-office-2" link="{{ route('admin.rooms.index') }}" :title="__('Rooms')" />
        @endcan
    </x-menu-sub>
    @endcanany

    @canany(['users.view', 'subscriptions.view', 'users.update', 'access.manage', 'training_plans.manage'])
    <x-menu-sub icon="o-user-group" :title="__('Members Admin')">
        @can('users.view')
            <x-menu-item icon="o-users" link="{{ route('admin.users.index') }}" :title="__('Users')" />
        @endcan
        @can('subscriptions.view')
            <x-menu-item icon="o-list-bullet" link="{{ route('admin.users.registrations') }}" :title="__('Affiliations')" />
        @endcan
        @canany(['users.update', 'access.manage'])
            <x-menu-item icon="o-key" link="{{ route('admin.users.delegations') }}" :title="__('Delegations')" />
        @endcanany
        @can('subscriptions.view')
            <x-menu-item icon="o-clipboard-document-list" link="{{ route('admin.subscriptions.roster') }}" :title="__('Season roster')" />
        @endcan
        @feature('training_planning')
        @can('training_plans.manage')
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

    <li><x-menu-separator /></li>

    @feature('trainings')
    @canany(['trainings.manage', 'coach_area.access'])
    <x-menu-sub icon="o-academic-cap" :title="__('Trainings')">
        @can('trainings.manage')
        <x-menu-item icon="o-tag" link="{{ route('admin.trainings.index') }}" :title="__('Training Packs')" />
        @endcan
        @can('coach_area.access')
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
    @if (Gate::any(['access-selections', 'access-results']) || $user->can('interclubs.manage'))
    <x-menu-sub icon="o-calendar-days" link="#" :title="__('Interclubs')">
        @can('access-selections')
        <x-menu-item icon="o-user-group" link="{{ route('admin.interclubs.captain-selection') }}" :title="__('Selections')" />
        @endcan
        @can('access-results')
        <x-menu-item icon="o-squares-2x2" link="{{ route('admin.interclubs.results') }}" :title="__('Results')" />
        @endcan
        @can('interclubs.manage')
        <x-menu-item icon="o-calendar-days" link="{{ route('admin.interclubs.interclubs') }}" :title="__('Planning')" />
        {{-- Two levels of indent leave 156px for a label. "Interclubs" already
        names the section this sits in, so the season goes without saying. --}}
        <x-menu-sub icon="o-cog-6-tooth" :title="__('Configuration')">
            <x-menu-item icon="o-identification" link="{{ route('admin.interclubs.teams') }}" :title="__('Our teams')" />
            <x-menu-item icon="o-table-cells" link="{{ route('admin.interclubs.division-setup') }}" :title="__('Opponents')" />
            <x-menu-item icon="o-building-office-2" link="{{ route('admin.interclubs.clubs') }}" :title="__('Clubs')" />
        </x-menu-sub>
        @endcan
    </x-menu-sub>
    @endif
    @endfeature

    @feature('meetings', 'tournaments')
    @canany(['meetings.view', 'tournaments.manage'])
    <x-menu-sub icon="o-star" :title="__('Events')">
        @feature('meetings')
        @can('meetings.view')
        <x-menu-item icon="o-calendar-days" link="{{ route('admin.meetings.index') }}" :title="__('Meetings')" />
        @endcan
        @endfeature
        @feature('tournaments')
        @can('tournaments.manage')
        <x-menu-item icon="o-trophy" link="{{ route('admin.tournaments.index') }}" :title="__('Tournaments')" />
        @endcan
        @endfeature
    </x-menu-sub>
    @endcanany
    @endfeature

    @feature('website', 'contacts')
    @canany(['news_posts.manage', 'contacts.view', 'contacts.manage', 'spams.manage', 'event_posts.manage'])
    <x-menu-sub icon="o-globe-alt" :title="__('Website')">
        @feature('website')
        @can('news_posts.manage')
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
        @can('event_posts.manage')
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