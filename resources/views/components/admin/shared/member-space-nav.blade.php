{{--
    Bandeau « Mon espace » : identité réelle du membre + sous-navigation
    persistante entre les pages personnelles, avec compteur d'actions en
    attente (disponibilités interclubs à donner).

    Usage : <x-admin.shared.member-space-nav :user="$user" />
--}}
@props([
    'user',
])

@php
    $currentSeason = \App\Domains\Competitions\Interclub\Models\Season::current();

    $affiliationStatus = $currentSeason
        ? $user->subscriptions()
            ->where('season_id', $currentSeason->id)
            ->whereIn('status', ['pending', 'confirmed', 'paid'])
            ->value('status')
        : null;

    $teamIds = $user->teams()->pluck('teams.id');

    $pendingAvailabilities = $teamIds->isEmpty() ? 0 : \App\Domains\Competitions\Interclub\Models\Interclub::query()
        ->where('start_date_time', '>=', now())
        ->where(fn ($query) => $query
            ->whereIn('visited_team_id', $teamIds)
            ->orWhereIn('visiting_team_id', $teamIds))
        ->whereDoesntHave('users', fn ($query) => $query
            ->where('users.id', $user->id)
            ->whereNotNull('interclub_user.availability'))
        ->count();

    $tabs = collect([
        [
            'label' => __('My profile'),
            'route' => route('admin.user.profile', $user),
            'active' => request()->routeIs('admin.user.profile'),
            'badge' => null,
            'visible' => true,
        ],
        [
            'label' => __('My matches'),
            'route' => route('admin.interclubs.my-matches'),
            'active' => request()->routeIs('admin.interclubs.my-matches'),
            'badge' => $pendingAvailabilities > 0 ? (string) $pendingAvailabilities : null,
            'visible' => $user->is_competitor,
        ],
        [
            'label' => __('My team(s)'),
            'route' => route('admin.user.teams', $user),
            'active' => request()->routeIs('admin.user.teams'),
            'badge' => null,
            'visible' => true,
        ],
        [
            'label' => __('My registrations'),
            'route' => route('admin.user.event-subscription', $user),
            'active' => request()->routeIs('admin.user.event-subscription'),
            'badge' => null,
            'visible' => true,
        ],
        [
            'label' => __('My Calendar'),
            'route' => route('admin.user.calendar', $user),
            'active' => request()->routeIs('admin.user.calendar'),
            'badge' => null,
            'visible' => true,
        ],
        [
            'label' => __('Affiliation & Trainings'),
            'route' => route('admin.user.registration-management', $user),
            'active' => request()->routeIs('admin.user.registration-management'),
            'badge' => null,
            'visible' => true,
        ],
        [
            'label' => __('Settings'),
            'route' => route('admin.user.settings', $user),
            'active' => request()->routeIs('admin.user.settings'),
            'badge' => null,
            'visible' => true,
        ],
    ])->filter(fn (array $tab) => $tab['visible']);
@endphp

<div class="mb-6 rounded-xl border border-base-300 bg-base-100">
    {{-- Identité --}}
    <div class="flex items-center gap-3 px-4 pt-4 pb-3">
        <x-avatar :image="$user->photo ?? '/images/empty-user.jpg'" class="!w-10 !rounded-full" />
        <div class="min-w-0 flex-1">
            <div class="truncate font-bold">{{ $user->first_name }} {{ $user->last_name }}</div>
            <div class="truncate text-xs text-base-content/60">
                {{ collect([
                    $user->ranking,
                    $user->is_competitor ? __('Competitor') : __('Recreative'),
                ])->filter()->implode(' · ') }}
            </div>
        </div>
        @if (in_array($affiliationStatus, ['confirmed', 'paid'], true))
            <x-badge :value="__('Affiliated · season :season', ['season' => $currentSeason->name])"
                class="badge-success badge-sm font-bold" />
        @elseif ($affiliationStatus === 'pending')
            <x-admin.shared.status-badge status="pending" />
        @else
            <a href="{{ route('admin.user.registration-management', $user) }}"
                class="btn btn-outline btn-xs">
                {{ __('Not affiliated this season') }}
            </a>
        @endif
    </div>

    {{-- Onglets --}}
    <nav class="flex gap-1 overflow-x-auto border-t border-base-200 px-2" aria-label="{{ __('My space') }}">
        @foreach ($tabs as $tab)
            <a href="{{ $tab['route'] }}"
                @class([
                    'flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm font-semibold transition-colors',
                    'border-primary text-primary' => $tab['active'],
                    'border-transparent text-base-content/60 hover:text-base-content' => ! $tab['active'],
                ])>
                {{ $tab['label'] }}
                @if ($tab['badge'])
                    <span class="badge badge-error badge-xs font-bold text-white">{{ $tab['badge'] }}</span>
                @endif
            </a>
        @endforeach
    </nav>
</div>
