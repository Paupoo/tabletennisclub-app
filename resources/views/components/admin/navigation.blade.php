@php $unreadNotificationsCount = auth()->user()->unreadNotifications()->count(); @endphp

<x-menu activate-by-route class="mt-10">
    <x-menu-sub icon="o-user" title="{{ $user->first_name }}">
        {{-- L'avatar et l'email s'affichent mieux ici dans un menu-item spécial ou le titre du sub-menu --}}
        <x-slot:title>
            <div class="flex items-center gap-3">
                <div class="overflow-hidden truncate">
                    <div class="truncate font-bold">{{ $user->first_name }}</div>
                    <div class="truncate text-[10px] opacity-50">{{ $user->email }}</div>
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
        <x-menu-item icon="o-academic-cap" link="{{ route('admin.user.registration-management', $user) }}" :title="__('Affiliation & Trainings')" />
        <x-menu-item icon="o-cog-8-tooth" :link="route('admin.user.settings', $user)" :title="__('Settings')" />
        <x-menu-separator />
        <livewire:actions.logout />
    </x-menu-sub>

    <x-menu-separator />

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

    <x-menu-item
        icon="o-users"
        link="{{ route('admin.user.directory', auth()->user()) }}"
        :title="__('Member directory')"
    />

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


    <x-menu-separator />
    @if(Auth()->user()->is_admin || Auth()->user()->is_committee_member )
    <x-menu-sub icon="o-building-office" :title="__('Club Settings')">
        <x-menu-item icon="o-identification" link="{{ route('admin.club-info') }}" :title="__('Informations')" />
        <x-menu-item icon="o-calendar" link="{{ route('admin.seasons.index') }}" :title="__('Seasons')" />
        <x-menu-item icon="o-building-office-2" link="{{ route('admin.rooms.index') }}" :title="__('Rooms')" />
    </x-menu-sub>
                    
    <x-menu-sub icon="o-user-group" :title="__('Members Admin')">
        @can('users.view')
            <x-menu-item icon="o-users" link="{{ route('admin.users.index') }}" :title="__('Users')" />
        @endcan
        @can('subscriptions.view')
            <x-menu-item icon="o-list-bullet" link="{{ route('admin.users.registrations') }}" :title="__('Registrations')" />
        @endcan
        @can('users.update')
            <x-menu-item icon="o-key" link="{{ route('admin.users.delegations') }}" :title="__('Delegations')" />
        @endcan
        @can('subscriptions.view')
            <x-menu-item icon="o-clipboard-document-list" link="{{ route('admin.subscriptions.roster') }}" :title="__('Season roster')" />
        @endcan
        @feature('training_planning')
        <x-menu-item icon="o-view-columns" link="{{ route('admin.planning.board') }}" :title="__('Planning board')" />
        @endfeature
    </x-menu-sub>

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
    <x-menu-sub icon="o-cog-6-tooth" link="#" :title="__('Club')">
    </x-menu-sub>

    <x-menu-separator />
    @endif

    @feature('trainings')
    <x-menu-sub icon="o-academic-cap" :title="__('Trainings')">
        @if($user->is_committee_member || $user->is_admin)
        <x-menu-item icon="o-tag" link="{{ route('admin.trainings.index') }}" :title="__('Training Packs')" />
        @endif
        @if($user->is_coach)
        <x-menu-item icon="o-calendar-days" link="{{ route('coach.trainings') }}" :title="__('My sessions')" />
        @endif
    </x-menu-sub>
    @endfeature

    @feature('interclubs')
    <x-menu-sub icon="o-calendar-days" link="#" :title="__('Interclubs')">
        
        @can('selections.manage')
        <x-menu-item icon="o-user-group" link="{{ route('admin.interclubs.captain-selection') }}" :title="__('Selections')" />
        @endcan
        @can('results.manage')
        <x-menu-item icon="o-squares-2x2" link="{{ route('admin.interclubs.results') }}" :title="__('Results')" />
        @endcan
        @can('interclubs.manage')
        <x-menu-item icon="o-calendar-days" link="{{ route('admin.interclubs.interclubs') }}" :title="__('Planning')" />
        <x-menu-sub icon="o-cog-6-tooth" :title="__('Configuration saison')">
            <x-menu-item icon="o-identification" link="{{ route('admin.interclubs.teams') }}" :title="__('Nos équipes')" />
            <x-menu-item icon="o-table-cells" link="{{ route('admin.interclubs.division-setup') }}" :title="__('Adversaires')" />
            <x-menu-item icon="o-building-office-2" link="{{ route('admin.interclubs.clubs') }}" :title="__('Clubs')" />
        </x-menu-sub>
        @endcan
    </x-menu-sub>
    @endfeature

    @if($user->is_committee_member || $user->is_admin)
    @feature('meetings', 'tournaments')
    <x-menu-sub icon="o-star" :title="__('Events')">
        @feature('meetings')
        <x-menu-item icon="o-calendar-days" link="{{ route('admin.meetings.index') }}" :title="__('Meetings')" />
        @endfeature
        @feature('tournaments')
        <x-menu-item icon="o-trophy" link="{{ route('admin.tournaments.index') }}" :title="__('Tournaments')" />
        @endfeature
    </x-menu-sub>

    <x-menu-separator />
    @endfeature
    @endif

    @if($user->is_admin || $user->is_committee_member)
    @feature('website', 'contacts')
    <x-menu-sub icon="o-globe-alt" :title="__('Website')">
        @feature('website')
        <x-menu-item icon="o-newspaper" link="{{ route('admin.website.articles.index') }}" :title="__('Articles')" />
        @endfeature
        @feature('contacts')
        <x-menu-item icon="o-envelope-open" link="{{ route('admin.website.contacts.index') }}" :title="__('Contacts')" />
        @can('manage-contacts')
            <x-menu-item icon="o-document-text" link="{{ route('admin.website.contacts.email-templates') }}" :title="__('Email templates')" />
        @endcan
        <x-menu-item icon="o-shield-exclamation" link="{{ route('admin.website.spams.index') }}" :title="__('Spam')" />
        @endfeature
        @feature('website')
        <x-menu-item icon="o-calendar-days" link="{{ route('admin.website.events.index') }}" :title="__('Events')" />
        @endfeature
    </x-menu-sub>
    @endfeature
    @endif

    @feature('supervision')
    @if($user->canViewAuditLog())
    <x-menu-separator />
    <x-menu-item
        icon="o-magnifying-glass"
        link="{{ route('admin.audit.index') }}"
        :title="__('Audit')"
    />
    @endif
    @endfeature

    @feature('supervision')
    @can('view-queue-monitoring')
    <x-menu-item
        icon="o-queue-list"
        link="{{ route('admin.queue.index') }}"
        :title="__('Queue monitoring')"
    />
    @endcan
    @endfeature

</x-menu>