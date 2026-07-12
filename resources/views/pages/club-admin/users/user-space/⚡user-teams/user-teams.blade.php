<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-admin.shared.member-space-nav :user="$user" />

    <x-header separator
        :title="$team?->fullName() ?? __('My team(s)')"
        :subtitle="$team ? collect([$categoryLabel, $team->league?->division, $team->season?->name])->filter()->implode(' · ') : null">
        <x-slot:actions>
            @if (count($teamOptions) > 1)
                <x-select :options="$teamOptions" wire:model.live="selectedTeamId" class="select-sm" />
            @endif
        </x-slot:actions>
    </x-header>

    @if (! $team)
        <x-empty-state icon="o-user-group" :heading="__('No team yet')"
            :message="__('You are not part of any team this season.')" />
    @else
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">

            {{-- ── Effectif ─────────────────────────────────────── --}}
            <div>
                <x-card :title="__('Roster')" icon="o-users" separator>
                    <div class="divide-y divide-base-200 -mx-2">
                        @foreach ($team->users->sortBy('last_name') as $mate)
                            @php $isYou = $mate->id === $user->id; @endphp
                            <div @class([
                                'flex items-center gap-3 px-2 py-2.5',
                                'bg-primary/5 rounded-lg' => $isYou,
                            ])>
                                <x-avatar :image="$mate->photo ?? '/images/empty-user.jpg'" class="!w-8 !rounded-full" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate text-sm font-semibold">
                                            {{ $mate->first_name }} {{ $mate->last_name }}
                                        </span>
                                        @if ($isYou)
                                            <span class="text-xs opacity-50">{{ __('(you)') }}</span>
                                        @endif
                                    </div>
                                    <div class="text-xs opacity-50">{{ $mate->ranking }}</div>
                                </div>
                                @if ($team->captain_id === $mate->id)
                                    <x-badge :value="__('Captain')" class="badge-secondary badge-sm" />
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-card>
            </div>

            {{-- ── Prochaines rencontres ────────────────────────── --}}
            <div class="lg:col-span-2">
                <x-card :title="__('Upcoming matches')" icon="o-calendar" separator>
                    <x-slot:menu>
                        <x-button :label="__('Manage my availability')" icon-right="o-arrow-right"
                            class="btn-ghost btn-sm" link="{{ route('admin.interclubs.my-matches') }}" />
                    </x-slot:menu>

                    @if ($upcomingMatches->isEmpty())
                        <div class="flex flex-col items-center gap-3 py-10 text-center">
                            <x-icon name="o-calendar" class="h-10 w-10 opacity-20" />
                            <p class="text-sm text-base-content/50">{{ __('No upcoming matches for this team.') }}</p>
                        </div>
                    @else
                        <div class="divide-y divide-base-200">
                            @foreach ($upcomingMatches as $match)
                                <div class="flex items-center gap-4 py-3">
                                    <div class="w-12 shrink-0 rounded-lg border border-base-200 bg-base-200/40 py-1 text-center">
                                        <div class="text-base font-bold leading-tight">
                                            {{ $match['start_date_time']->format('d') }}
                                        </div>
                                        <div class="text-xs uppercase opacity-60">
                                            {{ $match['start_date_time']->translatedFormat('M') }}
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-semibold">vs {{ $match['opponent'] }}</span>
                                            @if ($match['is_home'])
                                                <x-badge :value="__('Home')" class="badge-neutral badge-xs font-bold" />
                                            @else
                                                <x-badge :value="__('Away')" class="badge-ghost badge-xs border border-base-300 font-bold" />
                                            @endif
                                            @if ($match['is_selected'])
                                                <x-admin.shared.status-badge status="selected" />
                                            @endif
                                        </div>
                                        <div class="mt-0.5 flex items-center gap-2 text-xs text-base-content/50">
                                            @if ($match['week_number'])
                                                <span>{{ __('Week :week', ['week' => $match['week_number']]) }}</span>
                                                <span class="opacity-50">·</span>
                                            @endif
                                            <span>{{ $match['start_date_time']->translatedFormat('D d/m · H:i') }}</span>
                                            <span class="opacity-50">·</span>
                                            <span class="truncate">{{ $match['address'] }}</span>
                                        </div>
                                    </div>
                                    <div class="shrink-0">
                                        @if ($match['availability'])
                                            <x-badge :class="$match['availability']->color() . ' badge-sm font-bold'"
                                                :value="$match['availability']->label()" />
                                        @else
                                            <a href="{{ route('admin.interclubs.my-matches') }}"
                                                class="btn btn-outline btn-xs">
                                                {{ __('Set availability') }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-4 border-t border-base-200 pt-3 text-xs text-base-content/50">
                        {{ __('Division standings will appear here once opponent results are recorded.') }}
                    </div>
                </x-card>
            </div>
        </div>
    @endif
</div>
