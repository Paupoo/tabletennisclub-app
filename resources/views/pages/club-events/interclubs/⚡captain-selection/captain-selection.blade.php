<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header separator :subtitle="__('Manage team selections')" :title="__('Sélections')">
        <x-slot:actions>
            <x-select
                class="select-sm border-none bg-base-200/50 font-bold"
                :options="$seasons_list"
                wire:model.live="selectedSeasonId" />
            <x-button class="btn-ghost btn-sm" icon="o-arrow-right" :label="__('Mes matchs')"
                :link="route('admin.interclubs.my-matches')" />
        </x-slot:actions>
    </x-header>

    {{-- Admin preparation score --}}
    @if ($isAdminOrCommittee && $weekSummary && $weekSummary['total'] > 0)
        <div class="mb-6 flex flex-wrap items-center gap-6 rounded-xl border border-base-200 bg-base-50 px-5 py-4">
            <div class="flex items-center gap-3">
                <div class="radial-progress text-[11px] font-black text-primary"
                    style="--value:{{ $weekSummary['preparation_score'] }}; --size:2.8rem; --thickness:3px;">
                    {{ $weekSummary['preparation_score'] }}%
                </div>
                <div>
                    <div class="text-[10px] font-black uppercase tracking-widest opacity-40">{{ __('Préparation') }}</div>
                    <div class="text-xs font-bold">{{ $weekSummary['ok'] }}/{{ $weekSummary['total'] }} {{ __('semaines prêtes') }}</div>
                </div>
            </div>
            <div class="flex flex-wrap items-end gap-1.5">
                @foreach ($weekSummary['weeks'] as $wk)
                    @php
                        $dot = match ($wk['status']) {
                            'ok'      => 'bg-success',
                            'warning' => 'bg-warning',
                            'nok'     => 'bg-error animate-pulse',
                            default   => 'bg-base-300',
                        };
                    @endphp
                    <div class="flex flex-col items-center gap-0.5">
                        <div class="{{ $dot }} h-2 w-2 rounded-full"></div>
                        <span class="text-[8px] font-black opacity-30">{{ $matchDayMap[$wk['wk']] ?? $wk['wk'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

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

        {{-- ── TEAM TABS ──────────────────────────────────────────────── --}}
        @if ($teamsData->count() > 1)
            <div class="mb-6 flex flex-wrap gap-2 border-b border-base-200 pb-0">
                @foreach ($teamsData as $td)
                    <button
                        wire:click="$set('selectedTeamId', {{ $td['id'] }})"
                        @class([
                            'relative flex items-center gap-2 rounded-t-xl border border-b-0 px-4 py-2 text-sm font-bold transition-all',
                            'border-base-200 bg-base-100 text-base-content -mb-px' => $selectedTeamId === $td['id'],
                            'border-transparent text-base-content/40 hover:text-base-content/70' => $selectedTeamId !== $td['id'],
                        ])>
                        {{ $td['name'] }}
                        @if ($td['has_alert'])
                            <span class="h-2 w-2 animate-pulse rounded-full bg-error"></span>
                        @endif
                    </button>
                @endforeach
            </div>
        @endif

        {{-- ── TEAM CONTENT ────────────────────────────────────────────── --}}
        @foreach ($teamsData as $td)
            @if ($selectedTeamId !== $td['id'])
                @continue
            @endif

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
                                $isUrgent = $ic['alert'];
                                $selCount = $ic['selected_count'];
                                $maxP     = $ic['max_players'];
                                $avCount  = $ic['available_count'];
                                $mbCount  = $ic['maybe_count'];

                                $statusBarColor = match ($ic['status']) {
                                    'confirmed', 'ready' => 'bg-success',
                                    'pending'            => 'bg-warning',
                                    'past'               => 'bg-base-200',
                                    default              => 'bg-base-300',
                                };

                                if ($avCount >= $maxP) {
                                    $availDot   = 'bg-success';
                                    $availText  = 'text-success';
                                    $availLabel = $avCount . ' dispo';
                                    $availPulse = false;
                                } elseif ($avCount + $mbCount >= $maxP) {
                                    $availDot   = 'bg-warning';
                                    $availText  = 'text-warning';
                                    $availLabel = $avCount . '+' . $mbCount . ' peut-être';
                                    $availPulse = false;
                                } else {
                                    $availDot   = 'bg-error';
                                    $availText  = 'text-error';
                                    $availLabel = $avCount . '/' . $maxP . ' dispo';
                                    $availPulse = $isUrgent;
                                }
                            @endphp
                            <div @class([
                                'px-4 py-3 bg-base-100 transition-colors',
                                'opacity-60 bg-base-50' => $isPast,
                                'bg-error/3' => $isUrgent && ! $isPast,
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
                                                <x-badge class="badge-neutral badge-xs font-bold" value="{{ __('Dom.') }}" />
                                            @else
                                                <x-badge class="badge-ghost badge-xs border border-base-300 font-bold" value="{{ __('Ext.') }}" />
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
                                            <div class="flex items-center gap-1.5 {{ $availText }}">
                                                <span @class([
                                                    'h-2 w-2 rounded-full',
                                                    $availDot,
                                                    'animate-pulse' => $availPulse,
                                                ])></span>
                                                <span class="text-[11px] font-bold">{{ $availLabel }}</span>
                                            </div>
                                            @if ($ic['status'] === 'confirmed')
                                                <x-badge class="badge-success badge-xs font-bold" value="{{ __('Envoyé') }}" />
                                            @elseif ($selCount >= $maxP)
                                                <x-badge class="badge-success badge-soft badge-xs font-bold" value="{{ __('Prêt') }} · {{ $selCount }}/{{ $maxP }}" />
                                            @elseif ($selCount > 0)
                                                <x-badge class="badge-warning badge-soft badge-xs font-bold" value="{{ $selCount }}/{{ $maxP }} {{ __('sél.') }}" />
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Actions --}}
                                    @if (! $isPast)
                                        <div class="flex shrink-0 items-center gap-1.5">
                                            <x-button
                                                class="btn-primary btn-sm"
                                                icon="o-pencil-square"
                                                :label="__('Sélectionner')"
                                                wire:click="openSelection({{ $ic['id'] }})" />
                                            <x-button
                                                class="btn-ghost btn-xs text-base-content/40"
                                                icon="o-envelope"
                                                :tooltip="__('Demander les dispos')"
                                                wire:click="requestAvailability({{ $ic['id'] }})" />
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    @endif

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
                            $isSelected = in_array($player['id'], $selectedPlayerIds);
                            $avail      = $player['availability'];
                            $isUnavail  = $avail === \App\Domains\Shared\Enums\InterclubAvailability::UNAVAILABLE;
                        @endphp
                        <div
                            wire:click="togglePlayer({{ $player['id'] }})"
                            @class([
                                'flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition-all',
                                'border-primary bg-primary/5 ring-1 ring-primary/40' => $isSelected,
                                'border-base-200 hover:border-primary/40 bg-base-100' => ! $isSelected,
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
                                    @if ($avail)
                                        <span class="{{ $avail->color() }} badge badge-xs">{{ $avail->label() }}</span>
                                    @else
                                        <span class="text-[9px] opacity-40">{{ __('Pas de réponse') }}</span>
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
                                    <span class="mt-0.5 text-[7px] font-black uppercase opacity-30">{{ __('joués') }}</span>
                                </div>
                                <div class="self-stretch w-px bg-base-200"></div>
                                <div class="flex flex-col items-center px-3 py-1.5">
                                    <span class="text-sm font-black tabular-nums leading-none">{{ $player['matches_selected'] }}</span>
                                    <span class="mt-0.5 text-[7px] font-black uppercase opacity-30">{{ __('sél.') }}</span>
                                </div>
                            </div>

                            {{-- Checkbox --}}
                            <div @class([
                                'flex h-5 w-5 shrink-0 items-center justify-center rounded border',
                                'bg-primary border-primary text-primary-content' => $isSelected,
                                'border-base-300 bg-white' => ! $isSelected,
                            ])>
                                @if ($isSelected)
                                    <x-icon class="h-3 w-3" name="o-check" />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Search substitute --}}
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
    <x-modal separator :title="__('Notify the team')" wire:model="modalMessage">
        <div class="space-y-4">

            {{-- Selected lineup summary --}}
            @php
                $drawerIc = $drawerInterclub;
                $currentIcId = $selectedInterclubId;
                $currentTd = $teamsData->firstWhere('id', $selectedTeamId);
                $currentMatch = $currentTd ? collect($currentTd['matches'])->firstWhere('id', $currentIcId) : null;
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
        </div>

        <x-slot:actions>
            <x-button class="btn-ghost" :label="__('Skip')" wire:click="skipSending" />
            <x-button class="btn-primary" icon="o-paper-airplane" :label="__('Send to team')"
                wire:click="sendLineupToTeam" />
        </x-slot:actions>
    </x-modal>
</div>
