<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header progress-indicator separator
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

            {{-- ── Effectif : à gauche sur grand écran, en dernier sur téléphone ── --}}
            <div class="order-last lg:order-none">
                <x-card :title="__('Roster')" icon="o-users" separator class="lg:sticky lg:top-4">
                    <div class="divide-y divide-base-200 -mx-2">
                        @foreach ($roster as $row)
                            @php
                                $mate = $row['user'];
                                $isYou = $mate->id === $user->id;
                            @endphp
                            <div @class([
                                'flex items-baseline gap-2 px-2 py-2.5 text-sm',
                                'bg-primary/5 rounded-lg' => $isYou,
                            ]) data-roster-player="{{ $mate->id }}">
                                <span @class(['w-7 shrink-0 font-mono tabular-nums', 'text-base-content/40' => $row['force_list'] === null, 'text-base-content/70' => $row['force_list'] !== null])>{{ $row['force_list'] === null ? '—' : '#' . $row['force_list'] }}</span>
                                <div class="min-w-0 flex-1">
                                    <span class="font-semibold">{{ $mate->last_name }}</span>
                                    <span>{{ $mate->first_name }}</span>
                                    @if ($isYou)
                                        <span class="text-xs text-muted">{{ __('(you)') }}</span>
                                    @endif
                                    @if ($team->captain_id === $mate->id)
                                        <x-badge :value="__('Captain')" class="badge-secondary badge-xs ml-1" />
                                    @endif
                                </div>
                                <span class="text-base-content/60 shrink-0 font-mono text-xs">{{ $mate->ranking->getLabel() }}</span>
                                <span class="text-base-content/70 w-10 shrink-0 text-right font-mono text-xs tabular-nums">{{ $row['won'] }}-{{ $row['lost'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            </div>

            {{-- ── Prochaines rencontres ────────────────────────── --}}
            <div class="space-y-8 lg:col-span-2">
                <x-card :title="__('Upcoming matches')" icon="o-calendar" separator>
                    <x-slot:menu>
                        <x-button :label="__('Manage my availability')" icon-right="o-arrow-right"
                            class="btn-ghost btn-sm" link="{{ route('admin.interclubs.my-matches') }}" />
                    </x-slot:menu>

                    @if ($upcomingMatches->isEmpty())
                        <x-empty-state icon="o-calendar" :heading="__('No upcoming matches for this team.')" />
                    @else
                        <div class="divide-y divide-base-200" x-data="{ expanded: false }">
                            @foreach ($upcomingMatches as $match)
                                <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-4 gap-y-2 py-3 sm:grid-cols-[auto_minmax(0,1fr)_auto]" wire:key="upcoming-{{ $match['id'] }}"
                                    x-show="expanded || {{ $loop->index < 2 ? 'true' : 'false' }}" x-cloak>
                                    <div class="w-12 shrink-0 rounded-lg border border-base-300 bg-base-200/40 py-1 text-center">
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
                                        <div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-base-content/50">
                                            @if ($match['week_number'])
                                                <span>{{ __('Week :week', ['week' => $match['week_number']]) }}</span>
                                                <span class="opacity-50">·</span>
                                            @endif
                                            <span class="whitespace-nowrap">{{ $match['start_date_time']->translatedFormat('D d/m · H:i') }}</span>
                                            <span class="opacity-50">·</span>
                                            <span class="truncate">{{ $match['address'] }}</span>
                                        </div>
                                        @php $counts = $match['availability_counts']; @endphp
                                        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                                            @if ($counts['available'] > 0)
                                                <span class="font-semibold text-success">{{ trans_choice(':count available|:count available', $counts['available']) }}</span>
                                            @endif
                                            @if ($counts['maybe'] > 0)
                                                <span class="font-semibold text-warning-content">{{ trans_choice(':count uncertain|:count uncertain', $counts['maybe']) }}</span>
                                            @endif
                                            @if ($counts['unavailable'] > 0)
                                                <span class="font-semibold text-error">{{ trans_choice(':count unavailable|:count unavailable', $counts['unavailable']) }}</span>
                                            @endif
                                            @if ($counts['no_response'] > 0)
                                                <span class="text-base-content/40">{{ trans_choice(':count without response|:count without response', $counts['no_response']) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-start-2 sm:col-start-auto">
                                        @if ($match['availability'])
                                            <x-badge :class="$match['availability']->color() . ' badge-sm font-bold'"
                                                :value="$match['availability']->label()" />
                                        @else
                                            <a href="{{ route('admin.interclubs.my-match', $match['id']) }}"
                                                class="btn btn-outline btn-xs">
                                                {{ __('Set availability') }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                            @if ($upcomingMatches->count() > 2)
                                <x-admin.club-events.interclubs.show-more :hidden="$upcomingMatches->count() - 2" />
                            @endif
                        </div>
                    @endif
                </x-card>

                <x-card :title="__('Results')" icon="o-trophy" separator>
                    @if ($pastMatches->isEmpty())
                        <x-empty-state icon="o-trophy" :heading="__('No match played yet.')" />
                    @else
                        <div class="divide-y divide-base-200 -mx-2" x-data="{ expanded: false }">
                            @foreach ($pastMatches as $match)
                                <a href="{{ route('admin.interclubs.my-match', $match['id']) }}" wire:navigate wire:key="result-{{ $match['id'] }}"
                                    class="hover:bg-base-200/60 flex items-center gap-3 rounded-lg px-2 py-3 transition-colors"
                                    data-result="{{ $match['id'] }}"
                                    x-show="expanded || {{ $loop->index < 5 ? 'true' : 'false' }}" x-cloak>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline gap-2">
                                            <div class="min-w-0 flex-1 text-sm">
                                                <span class="text-base-content/50 text-xs">
                                                    @if ($match['week_number'])
                                                        {{ __('Week :week', ['week' => $match['week_number']]) }} ·
                                                    @endif
                                                    {{ $match['start_date_time']->translatedFormat('D d/m') }}
                                                </span>
                                                <span class="font-semibold">vs {{ $match['opponent'] }}</span>
                                                @if ($match['is_home'])
                                                    <x-badge :value="__('Home')" class="badge-neutral badge-xs font-bold" />
                                                @else
                                                    <x-badge :value="__('Away')" class="badge-ghost badge-xs border border-base-300 font-bold" />
                                                @endif
                                            </div>
                                            @if ($match['result'] === null)
                                                <span class="text-base-content/50 shrink-0 text-xs italic">{{ __('Result pending') }}</span>
                                            @elseif ($match['result']->isPlayed() && $match['score'] !== null)
                                                <span @class([
                                                    'shrink-0 font-mono text-base font-bold tabular-nums',
                                                    'text-success' => $match['result']->isWin(),
                                                    'text-error' => $match['result']->isLoss(),
                                                ])>{{ $match['score'] }}</span>
                                            @else
                                                <span @class(['shrink-0 text-xs font-semibold', 'text-success' => $match['result']->isWin(), 'text-error' => $match['result']->isLoss()])>{{ $match['result']->label() }}</span>
                                            @endif
                                        </div>
                                        @if ($match['players']->isNotEmpty())
                                            <div class="text-base-content/70 mt-1 text-xs">
                                                @foreach ($match['players'] as $player)
                                                    <span @class(['whitespace-nowrap', 'text-base-content font-bold' => $player['id'] === $user->id])>{{ $player['name'] }} <span class="font-mono tabular-nums">{{ $player['won'] }}-{{ $player['lost'] }}</span></span>@if (! $loop->last)<span class="text-base-content/30"> · </span>@endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <x-icon name="o-chevron-right" class="text-base-content/30 h-4 w-4 shrink-0" />
                                </a>
                            @endforeach
                            @if ($pastMatches->count() > 5)
                                <x-admin.club-events.interclubs.show-more :hidden="$pastMatches->count() - 5" />
                            @endif
                        </div>
                    @endif
                </x-card>
            </div>
        </div>
    @endif
</div>
