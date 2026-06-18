<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header separator :subtitle="__('Manage team selections')" :title="__('Selections')">
        <x-slot:actions>
            <x-select
                class="select-sm border-none bg-base-200/50 font-bold"
                :options="$seasons_list"
                wire:model.live="selectedSeasonId" />
            @if ($teamsData->count() > 1)
                <button class="btn btn-ghost btn-circle btn-sm relative {{ count($filterChips) > 0 ? 'btn-active' : '' }} lg:hidden"
                    wire:click="$set('filterDrawer', true)">
                    <x-icon name="o-funnel" class="h-5 w-5" />
                    @if (count($filterChips) > 0)
                        <span class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-xs font-bold leading-none text-primary-content">{{ count($filterChips) }}</span>
                    @endif
                </button>
                <x-button
                    class="btn-ghost hidden {{ count($filterChips) > 0 ? 'btn-active' : '' }} lg:flex"
                    icon="o-funnel"
                    :label="__('Filters')"
                    wire:click="$set('filterDrawer', true)">
                    @if (count($filterChips) > 0)
                        <x-badge class="badge-sm badge-primary" value="{{ count($filterChips) }}" />
                    @endif
                </x-button>
            @endif
            
        </x-slot:actions>
    </x-header>

    @include('pages::club-events.interclubs.⚡captain-selection._prep-score-widget', [
        'weekSummary' => $weekSummary,
        'matchDayMap' => $matchDayMap,
        'zoomedTeamId' => $zoomedTeamId,
        'isAdminOrCommittee' => $isAdminOrCommittee,
    ])

    @if ($teamsData->isEmpty())
        <x-empty-state icon="o-user-group" :title="__('No team assigned')"
            :description="__('You are not captain of any team this season.')" />
    @else

        {{-- ── ALERT BANNER ───────────────────────────────────────────── --}}
        @if ($alertMatches->isNotEmpty())
            <div class="mb-6 rounded-xl border border-error/30 bg-error/5 p-4">
                <div class="mb-3 flex items-center gap-2">
                    <x-icon name="o-exclamation-triangle" class="h-4 w-4 text-error" />
                    <span class="text-sm font-bold text-error">
                        {{ __(':n match(es) needing attention in the next 14 days', ['n' => $alertMatches->count()]) }}
                    </span>
                </div>
                <div class="flex flex-wrap gap-3">
                    @foreach ($alertMatches as $am)
                        <button
                            wire:click="openSelection({{ $am['id'] }})"
                            @class([
                                'flex items-center gap-3 rounded-lg border px-3 py-2 text-left text-xs transition-all hover:shadow-sm',
                                'border-error/30 bg-base-100 hover:border-error/60' => true,
                            ])>
                            <div class="flex flex-col">
                                <span class="font-bold">{{ $am['team_name'] }} vs {{ $am['opponent'] }}</span>
                                <span class="opacity-50">{{ $am['date'] }} · S{{ $matchDayMap[$am['wk']] ?? $am['wk'] }}</span>
                            </div>
                            <div class="flex items-center gap-1 text-error">
                                <x-icon name="o-user-group" class="h-3.5 w-3.5" />
                                <span class="font-black">{{ $am['available_count'] }}/{{ $am['max_players'] }}</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <x-admin.shared.filter-chips :chips="$filterChips" />

        {{-- ── TEAM CONTENT ────────────────────────────────────────────── --}}
        @foreach ($teamsData as $td)
            @if ($selectedTeamId === $td['id'])
            <div class="space-y-1">
                {{-- Team info bar --}}
                <div class="mb-4 flex flex-wrap items-center gap-3 text-xs text-base-content/40">
                    <x-badge class="badge-outline badge-sm" value="{{ $td['division'] }}" />
                    @if ($td['captain_name'])
                        <span>{{ __('Captain') }}: <span class="font-bold text-base-content/70">{{ $td['captain_name'] }}</span></span>
                    @endif
                </div>

                {{-- Match list --}}
                @if (empty($td['matches']))
                    <x-empty-state icon="o-calendar" :title="__('No matches scheduled.')" />
                @else
                    <div class="divide-y divide-base-200 overflow-hidden rounded-xl border border-base-200">
                        @foreach ($td['matches'] as $ic)
                            @php
                                $isPast   = $ic['is_past'];
                                $maxP     = $ic['max_players'];
                                $avCount  = $ic['available_count'];

                                $statusBarColor = match ($ic['status']) {
                                    'confirmed'  => 'bg-success',
                                    'actionable' => 'bg-warning',
                                    'urgent'     => 'bg-error',
                                    'past'       => 'bg-base-200',
                                    default      => 'bg-base-300',
                                };
                            @endphp
                            <div @class([
                                'px-4 py-3 bg-base-100 transition-colors',
                                'opacity-60 bg-base-50' => $isPast,
                                'bg-error/3' => $ic['status'] === 'urgent',
                            ])>
                                <div class="flex flex-wrap items-center gap-3 sm:flex-nowrap">

                                    {{-- Status bar --}}
                                    <div class="{{ $statusBarColor }} h-8 w-1 shrink-0 rounded-full"></div>

                                    {{-- Week + date --}}
                                    <div class="w-10 shrink-0 text-center">
                                        <div class="text-[7px] font-black uppercase opacity-40">S</div>
                                        <div class="text-sm font-black leading-none">{{ $matchDayMap[$ic['wk']] ?? $ic['wk'] }}</div>
                                        <div class="tabular-nums text-[9px] opacity-40">{{ substr($ic['date'], 0, 5) }}</div>
                                    </div>

                                    {{-- Match info --}}
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1">
                                            @if ($ic['is_home'])
                                                <x-badge class="badge-neutral badge-xs font-bold" value="{{ __('Home') }}" />
                                            @else
                                                <x-badge class="badge-ghost badge-xs border border-base-300 font-bold" value="{{ __('Away') }}" />
                                            @endif
                                            <span class="text-sm font-bold">{{ $ic['opponent'] }}</span>
                                        </div>
                                        <div class="mt-0.5 text-[10px] text-base-content/40">{{ $ic['time'] }}</div>
                                        @if ($isPast && ! empty($ic['selected_player_names']))
                                            <div class="mt-0.5 text-[10px] italic text-base-content/35">
                                                {{ implode(', ', $ic['selected_player_names']) }}
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Availability indicator + selection badge --}}
                                    @if (! $isPast)
                                        <div class="flex shrink-0 flex-col items-end gap-1">
                                            <span class="text-[11px] text-base-content/50">{{ $avCount }}/{{ $maxP }} {{ __('avail.') }}</span>
                                            @if ($ic['status'] === 'confirmed')
                                                <x-badge class="badge-success badge-xs font-bold" value="{{ __('Sent') }}" />
                                            @elseif ($ic['selected_count'] > 0)
                                                <x-badge class="badge-ghost badge-xs border border-base-300 font-bold" value="{{ $ic['selected_count'] }}/{{ $maxP }} {{ __('sel.') }}" />
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Actions --}}
                                    @if (! $isPast)
                                        <div class="flex shrink-0 items-center gap-1.5">
                                            <x-button
                                                class="btn-primary btn-sm"
                                                icon="o-pencil-square"
                                                :label="__('Select')"
                                                wire:click="openSelection({{ $ic['id'] }})" />
                                            <x-button
                                                class="btn-ghost btn-xs text-base-content/40"
                                                icon="o-envelope"
                                                :tooltip="__('Request availability')"
                                                wire:click="requestAvailability({{ $ic['id'] }})" />
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            @endif
        @endforeach
    @endif

    {{-- ── FILTER DRAWER ÉQUIPES ──────────────────────────────────────── --}}
    {{-- Never wrap x-drawer in @if — x-teleport moves DOM to body, @if breaks Livewire morph --}}
    <x-admin.shared.filter-drawer :title="__('Teams')">
        <x-slot:filters>
            @if (! empty($teams_for_filter))
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                        {{ __('Team') }}
                    </p>
                    <x-radio wire:model.live="selectedTeamId" :options="$teams_for_filter" />
                </div>
            @endif
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- ── DRAWER SÉLECTION ───────────────────────────────────────────── --}}
    <x-drawer class="w-11/12 lg:w-2/5" right separator
        :subtitle="$drawerInterclub ? ('vs ' . collect($teamsData)->flatMap(fn ($t) => $t['matches'])->firstWhere('id', $drawerInterclub->id)['opponent'] ?? '') . ' — ' . $drawerInterclub->start_date_time->format('d/m/Y') : ''"
        :title="__('Selection') . ($drawerInterclub ? ' S' . ($matchDayMap[$drawerInterclub->week_number] ?? $drawerInterclub->week_number) : '')"
        wire:model="drawerSelection" with-close-button>
        <div class="space-y-6">

            {{-- Progress --}}
            <div>
                <div class="mb-2 flex justify-between text-[10px] font-black uppercase">
                    <span>{{ __('Selected') }}</span>
                    <span @class([
                        'font-black',
                        'text-success' => count($selectedPlayerIds) == $maxPlayers,
                        'text-warning' => count($selectedPlayerIds) > 0 && count($selectedPlayerIds) < $maxPlayers,
                        'text-base-content/40' => count($selectedPlayerIds) === 0,
                    ])>{{ count($selectedPlayerIds) }} / {{ $maxPlayers }}</span>
                </div>
                <progress @class([
                    'progress w-full h-2 transition-all duration-500',
                    'progress-success' => count($selectedPlayerIds) == $maxPlayers,
                    'progress-warning' => count($selectedPlayerIds) > 0 && count($selectedPlayerIds) < $maxPlayers,
                    'progress-primary' => count($selectedPlayerIds) === 0,
                ]) max="{{ $maxPlayers }}" value="{{ count($selectedPlayerIds) }}"></progress>
            </div>

            {{-- Roster --}}
            <div>
                <div class="mb-3 text-[10px] font-black uppercase tracking-widest opacity-40">{{ __('Team roster') }}</div>
                <div class="space-y-1.5">
                    @foreach ($roster as $player)
                        @php
                            $isSelected  = in_array($player['id'], $selectedPlayerIds);
                            $avail       = $player['availability'];
                            $isUnavail   = $avail === \App\Domains\Shared\Enums\InterclubAvailability::UNAVAILABLE;
                            $isBlocked   = $player['is_blocked'] ?? false;
                            $blockedTeam = $player['blocked_team'] ?? null;
                        @endphp
                        <div
                            @if (! $isBlocked) wire:click="togglePlayer({{ $player['id'] }})" @endif
                            @class([
                                'flex items-center gap-3 rounded-xl border p-3 transition-all',
                                'cursor-pointer' => ! $isBlocked,
                                'cursor-not-allowed' => $isBlocked,
                                'border-primary bg-primary/5 ring-1 ring-primary/40' => $isSelected && ! $isBlocked,
                                'border-base-200 bg-base-50 opacity-60' => $isBlocked,
                                'border-base-200 hover:border-primary/40 bg-base-100' => ! $isSelected && ! $isBlocked,
                            ])>

                            {{-- Rank chip --}}
                            <div @class([
                                'w-10 shrink-0 rounded-lg py-1.5 text-center text-sm font-black tabular-nums',
                                'bg-primary text-primary-content' => $isSelected,
                                'bg-error/20 text-error' => $isUnavail && ! $isSelected,
                                'bg-base-200 text-base-content/70' => ! $isSelected && ! $isUnavail,
                            ])>{{ $player['rank'] }}</div>

                            {{-- Name + availability + note --}}
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-black">{{ $player['name'] }}</div>
                                <div class="mt-0.5 flex items-center gap-1">
                                    @if ($isBlocked)
                                        <x-icon name="o-no-symbol" class="h-3 w-3 text-error" />
                                        <span class="text-[9px] font-bold text-error">
                                            {{ __('Already in lineup – W:n', ['n' => $drawerInterclub?->week_number]) }}
                                            @if ($canSearchSubstitute && $blockedTeam)
                                                · {{ __('Team') }}&nbsp;{{ $blockedTeam }}
                                            @endif
                                        </span>
                                    @elseif ($avail)
                                        <span class="{{ $avail->color() }} badge badge-xs">{{ $avail->label() }}</span>
                                    @else
                                        <span class="text-[9px] opacity-40">{{ __('No response') }}</span>
                                    @endif
                                </div>
                                @if (! empty($player['availability_note']))
                                    <div class="mt-0.5 text-[9px] italic opacity-40">"{{ $player['availability_note'] }}"</div>
                                @endif
                            </div>

                            {{-- Stats: joués | sél. --}}
                            <div class="flex shrink-0 overflow-hidden rounded-lg border border-base-200 text-center">
                                <div class="flex flex-col items-center px-3 py-1.5">
                                    <span class="text-sm font-black tabular-nums leading-none">{{ $player['matches_played'] }}</span>
                                    <span class="mt-0.5 text-[7px] font-black uppercase opacity-30">{{ __('played') }}</span>
                                </div>
                                <div class="self-stretch w-px bg-base-200"></div>
                                <div class="flex flex-col items-center px-3 py-1.5">
                                    <span class="text-sm font-black tabular-nums leading-none">{{ $player['matches_selected'] }}</span>
                                    <span class="mt-0.5 text-[7px] font-black uppercase opacity-30">{{ __('sel.') }}</span>
                                </div>
                            </div>

                            {{-- Checkbox / lock --}}
                            @if ($isBlocked)
                                <x-icon name="o-lock-closed" class="h-4 w-4 shrink-0 text-error/50" />
                            @else
                                <div @class([
                                    'flex h-5 w-5 shrink-0 items-center justify-center rounded border',
                                    'bg-primary border-primary text-primary-content' => $isSelected,
                                    'border-base-300 bg-white' => ! $isSelected,
                                ])>
                                    @if ($isSelected)
                                        <x-icon class="h-3 w-3" name="o-check" />
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Search substitute (admin / selector only) --}}
            @if ($canSearchSubstitute)
                <div class="border-t border-dashed border-base-300 pt-4">
                    <div class="mb-3 text-[10px] font-black uppercase tracking-widest opacity-40">
                        {{ __('Search a substitute') }}
                    </div>
                    <x-input class="input-sm rounded-lg border-none bg-base-200/50" icon="o-magnifying-glass"
                        :placeholder="__('Player name...')" wire:model.live.debounce.300ms="search" />
                    @if (strlen($search) >= 2)
                        <div class="animate-in fade-in slide-in-from-top-2 mt-4 space-y-2">
                            @forelse($searchResults as $res)
                                @php $isSelected = in_array($res['id'], $selectedPlayerIds); @endphp
                                <div @class([
                                    'flex cursor-pointer items-center justify-between rounded-lg border border-dashed p-2 transition-all',
                                    'border-primary bg-primary/5' => $isSelected,
                                    'border-base-300 hover:border-primary' => ! $isSelected,
                                ]) wire:click="togglePlayer({{ $res['id'] }})">
                                    <div class="flex items-center gap-2">
                                        <x-icon class="h-4 w-4 opacity-40" name="o-user-plus" />
                                        <div class="flex flex-col">
                                            <span class="text-[11px] font-bold">{{ $res['name'] }}</span>
                                            <span class="text-[9px] uppercase opacity-50">{{ $res['rank'] }}</span>
                                        </div>
                                    </div>
                                    @if ($isSelected)
                                        <x-icon class="h-5 w-5 text-primary" name="o-check-circle" />
                                    @endif
                                </div>
                            @empty
                                <div class="p-4 text-center text-xs opacity-40">{{ __('No player found.') }}</div>
                            @endforelse
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-button @click="$wire.drawerSelection = false" class="btn-ghost" :label="__('Cancel')" />
            <x-button
                :disabled="count($selectedPlayerIds) === 0"
                class="btn-primary"
                icon="o-check"
                :label="__('Save selection')"
                wire:click="saveSelection" />
        </x-slot:actions>
    </x-drawer>

    {{-- ── MODAL LINEUP / MESSAGE ─────────────────────────────────────── --}}
    <x-modal separator :title="$isUpdateMode ? __('Update the team') : __('Notify the team')" wire:model="modalMessage">
        <div class="space-y-4">
            @if ($isUpdateMode)
                {{-- Diff summary: only added/removed players are notified --}}
                @if (! empty($pendingRemovedNames))
                    <div class="bg-error/5 border border-error/20 rounded-xl p-3">
                        <div class="mb-2 text-[10px] font-black uppercase tracking-widest text-error/70">
                            {{ __('Removed — will always be notified') }}
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($pendingRemovedNames as $name)
                                <x-badge class="badge-error badge-soft badge-sm font-bold" :value="$name" />
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($pendingAddedNames))
                    <div class="bg-base-200/50 rounded-xl p-3">
                        <div class="mb-2 text-[10px] font-black uppercase tracking-widest opacity-40">
                            {{ __('Added') }}
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($pendingAddedNames as $name)
                                <x-badge class="badge-primary badge-soft badge-sm font-bold" :value="$name" />
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! $modalIsComplete)
                    <div class="rounded-xl border border-warning/30 bg-warning/5 p-3 text-xs text-warning">
                        <div class="flex items-center gap-1.5 font-semibold">
                            <x-icon name="o-exclamation-triangle" class="h-3.5 w-3.5" />
                            {{ __('Selection incomplete: only the removed players will be notified for now.') }}
                        </div>
                    </div>
                @endif

                <x-textarea
                    class="border-none bg-base-200/50 focus:ring-primary"
                    :label="__('Meetup info (optional)')"
                    :placeholder="__('E.g. Meet at 18:45 at the club entrance, bring your club shirt...')"
                    rows="4"
                    wire:model="captainMeetupInfo" />
            @else
                {{-- Selected lineup summary --}}
                @php
                    $selCount = count($selectedPlayerIds);
                    $maxP = $maxPlayers;
                @endphp

                <div class="bg-base-200/50 rounded-xl p-3">
                    <div class="mb-2 text-[10px] font-black uppercase tracking-widest opacity-40">
                        {{ __('Selected lineup (:n/:max)', ['n' => $selCount, 'max' => $maxP]) }}
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($roster->filter(fn ($p) => in_array($p['id'], $selectedPlayerIds)) as $p)
                            <x-badge class="badge-primary badge-soft badge-sm font-bold" :value="$p['name']" />
                        @endforeach
                    </div>
                </div>

                <x-textarea
                    class="border-none bg-base-200/50 focus:ring-primary"
                    :label="__('Meetup info (optional)')"
                    :placeholder="__('E.g. Meet at 18:45 at the club entrance, bring your club shirt...')"
                    rows="4"
                    wire:model="captainMeetupInfo" />

                <div class="rounded-xl border border-info/20 bg-info/5 p-3 text-xs text-info">
                    <div class="flex items-center gap-1.5 font-semibold">
                        <x-icon name="o-information-circle" class="h-3.5 w-3.5" />
                        {{ __('All team members will be notified. Selected players receive a calendar invite (ICS).') }}
                    </div>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-button class="btn-ghost" :label="__('Skip')" wire:click="skipSending" />
            <x-button class="btn-primary" icon="o-paper-airplane" :label="__('Send to team')"
                wire:click="sendLineupToTeam" />
        </x-slot:actions>
    </x-modal>
</div>
