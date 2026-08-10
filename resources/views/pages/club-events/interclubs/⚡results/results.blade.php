<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Results')">
        <x-slot:actions>
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" :show-search="false" :show-more="false" />
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
            </div>
        </x-slot:actions>
    </x-header>

    {{-- ── Active filter chips ──────────────────────────────────────────────── --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

    {{-- ── Filter drawer ────────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">
                    {{ __('Season') }}
                </p>
                <x-select
                    :options="$seasons"
                    option-label="name"
                    option-value="id"
                    wire:model.live="seasonId"
                    :placeholder="__('Select a season')"
                    class="w-full" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    @if (! $seasonId)
        <x-card class="mt-4">
            <p class="text-center text-sm text-gray-500">{{ __('Select a season to manage results.') }}</p>
        </x-card>
    @elseif ($teamsByCategory->isEmpty())
        <x-card class="mt-4">
            <p class="text-center text-sm text-gray-500">{{ __('No teams found for this season.') }}</p>
        </x-card>
    @else
        @php
            $catMeta = [
                'MEN'      => ['label' => 'Hommes',   'color' => 'blue'],
                'WOMEN'    => ['label' => 'Dames',    'color' => 'pink'],
                'VETERANS' => ['label' => 'Vétérans', 'color' => 'amber'],
            ];
        @endphp

        <div class="space-y-10">
            @foreach ($teamsByCategory as $catName => $categoryTeams)
                @php $cat = $catMeta[$catName] ?? ['label' => $catName, 'color' => 'gray']; @endphp

                <x-section-accordion
                    :label="$cat['label']"
                    :count="$categoryTeams->count() . ' équipe' . ($categoryTeams->count() > 1 ? 's' : '')"
                    :color="$cat['color']">
                    <div class="space-y-4">
                            @foreach ($categoryTeams as $team)
                                @php
                                    $teamStats = $stats[$team->id];
                                    $division  = $team->league?->division;
                                @endphp

                                {{-- Replié comme l'écran frère : le bilan (V/D/N, ratio, position
                                     finale) vit dans l'en-tête de la carte, pas ici — replier ne
                                     cache donc que le détail des rencontres. --}}
                                <div x-data="{ open: false }">
                                    <x-card>
                                        {{-- En-tête équipe --}}
                                        <x-slot:title>
                                            <div class="flex w-full items-center gap-2">
                                                <div class="flex flex-1 flex-wrap items-center gap-2">
                                                    <span class="font-bold text-gray-900">
                                                        {{ __('Team') }} {{ $team->name }}
                                                        @if ($division)
                                                            <span class="ml-1 text-sm font-normal text-gray-500">— Div. {{ $division }}</span>
                                                        @endif
                                                    </span>

                                                    {{-- Stats badges --}}
                                                    @if ($teamStats['played'] > 0)
                                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">{{ $teamStats['wins'] }}V</span>
                                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">{{ $teamStats['losses'] }}D</span>
                                                        @if ($teamStats['draws'] > 0)
                                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">{{ $teamStats['draws'] }}N</span>
                                                        @endif
                                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">{{ $teamStats['win_rate'] }}%</span>
                                                    @endif
                                                </div>
                                                <button type="button" @click="open = !open" class="rounded p-1 hover:bg-base-200 transition-colors"
                                                    :aria-expanded="open ? 'true' : 'false'"
                                                    aria-label="{{ __('Show or hide the team') }}">
                                                    <x-icon name="o-chevron-down" class="h-4 w-4 opacity-40 transition-transform duration-200" ::class="open ? '' : '-rotate-90'" />
                                                </button>
                                            </div>
                                        </x-slot:title>

                                        <x-slot:actions>
                                            <div class="flex flex-wrap items-center gap-2">
                                                {{-- Position finale --}}
                                                <div class="flex items-center gap-2">
                                                    <span class="hidden text-xs text-gray-500 sm:inline">{{ __('Final position') }} :</span>
                                                    <x-input
                                                        class="input-sm w-28 text-xs sm:w-36"
                                                        :placeholder="__('e.g. 1st place')"
                                                        value="{{ $team->final_position }}"
                                                        wire:change="updateFinalPosition({{ $team->id }}, $event.target.value)" />
                                                </div>
                                                <x-button
                                                    class="btn-sm btn-warning"
                                                    icon="o-exclamation-triangle"
                                                    :label="__('Forfait')"
                                                    :tooltip="__('Declare a general forfeit')"
                                                    wire:click="openTeamForfeitModal({{ $team->id }})" />
                                            </div>
                                        </x-slot:actions>

                                        {{-- Tableau des rencontres --}}
                                        <div x-show="open" x-collapse>
                                            @if ($team->interclubResults->isEmpty())
                                                <p class="py-4 text-center text-sm italic text-gray-400">{{ __('No matches scheduled for this team.') }}</p>
                                            @else
                                                <div class="overflow-x-auto">
                                                    <table class="w-full text-sm">
                                                        <thead>
                                                            <tr class="border-b border-gray-100 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                                                                <th class="hidden pb-2 pr-4 sm:table-cell">{{ __('Week') }}</th>
                                                                <th class="pb-2 pr-4">{{ __('Date') }}</th>
                                                                <th class="pb-2 pr-4">{{ __('Opponent') }}</th>
                                                                <th class="hidden pb-2 pr-4 sm:table-cell">{{ __('Venue') }}</th>
                                                                <th class="pb-2 pr-4">{{ __('Score') }}</th>
                                                                <th class="pb-2 pr-4">{{ __('Result') }}</th>
                                                                <th class="pb-2"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-50">
                                                            @foreach ($team->interclubResults->sortBy('match_date') as $mr)
                                                                <tr wire:key="mr-{{ $mr->id }}"
                                                                    @class(['text-gray-300' => $mr->is_bye, 'italic text-gray-400' => ! $mr->is_bye && $mr->result === null])>
                                                                    <td class="hidden py-2 pr-4 text-xs text-gray-400 sm:table-cell">
                                                                        {{ $mr->week_number ? 'S' . ($matchDayMap[$mr->week_number] ?? $mr->week_number) : '—' }}
                                                                    </td>
                                                                    <td class="py-2 pr-4 text-gray-700">
                                                                        @if ($mr->is_bye)
                                                                            <span class="italic text-gray-300">Bye</span>
                                                                        @elseif ($mr->match_date)
                                                                            <span class="hidden sm:inline">{{ $mr->match_date->format('d/m/Y') }}</span>
                                                                            <span class="sm:hidden">{{ $mr->match_date->format('d/m') }}</span>
                                                                        @else
                                                                            —
                                                                        @endif
                                                                    </td>
                                                                    <td class="py-2 pr-4 font-medium text-gray-800">
                                                                        {{ $mr->opponent_name ?? '—' }}
                                                                    </td>
                                                                    <td class="hidden py-2 pr-4 sm:table-cell">
                                                                        @if (! $mr->is_bye && $mr->opponent_name)
                                                                            <span @class([
                                                                                'rounded px-1.5 py-0.5 text-xs font-semibold',
                                                                                'bg-blue-50 text-blue-700'  => $mr->is_home,
                                                                                'bg-gray-100 text-gray-600' => ! $mr->is_home,
                                                                            ])>
                                                                                {{ $mr->is_home ? __('Home') : __('Away') }}
                                                                            </span>
                                                                        @endif
                                                                    </td>
                                                                    <td class="py-2 pr-4 font-mono text-xs text-gray-700">
                                                                        {{ $mr->score ?? '—' }}
                                                                    </td>
                                                                    <td class="py-2 pr-4">
                                                                        @if ($mr->is_bye)
                                                                            <span class="rounded bg-gray-50 px-1.5 py-0.5 text-xs font-semibold text-gray-400">Bye</span>
                                                                        @elseif ($mr->result === null)
                                                                            <span class="text-xs italic text-gray-300">{{ __('Pending') }}</span>
                                                                        @elseif ($mr->result === \App\Domains\Shared\Enums\InterclubResultEnum::WIN)
                                                                            <span class="rounded bg-green-100 px-1.5 py-0.5 text-xs font-semibold text-green-800">{{ __('Win') }}</span>
                                                                        @elseif ($mr->result === \App\Domains\Shared\Enums\InterclubResultEnum::LOSS)
                                                                            <span class="rounded bg-red-100 px-1.5 py-0.5 text-xs font-semibold text-red-700">{{ __('Loss') }}</span>
                                                                        @elseif ($mr->result === \App\Domains\Shared\Enums\InterclubResultEnum::DRAW)
                                                                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-semibold text-gray-600">{{ __('Draw') }}</span>
                                                                        @elseif ($mr->result === \App\Domains\Shared\Enums\InterclubResultEnum::FORFEIT_WIN)
                                                                            <span class="rounded bg-green-50 px-1.5 py-0.5 text-xs font-semibold text-green-800">{{ __('Forfait adv.') }}</span>
                                                                        @elseif ($mr->result === \App\Domains\Shared\Enums\InterclubResultEnum::FORFEIT_LOSS)
                                                                            <span class="rounded bg-red-50 px-1.5 py-0.5 text-xs font-semibold text-red-400">{{ __('Forfait') }}</span>
                                                                        @elseif ($mr->result === \App\Domains\Shared\Enums\InterclubResultEnum::WITHDRAWAL_OPPONENT)
                                                                            <span class="rounded bg-orange-50 px-1.5 py-0.5 text-xs font-semibold text-orange-700">{{ __('Opp. gen. forfeit') }}</span>
                                                                        @elseif ($mr->result === \App\Domains\Shared\Enums\InterclubResultEnum::WITHDRAWAL)
                                                                            <span class="rounded bg-orange-100 px-1.5 py-0.5 text-xs font-semibold text-orange-700">{{ __('Gen. forfeit') }}</span>
                                                                        @endif
                                                                    </td>
                                                                    <td class="py-2 text-right">
                                                                        <div class="flex justify-end gap-1">
                                                                            <x-button class="btn-ghost btn-xs" icon="o-pencil"
                                                                                :aria-label="__('Edit the result')"
                                                                                wire:click="openEditModal({{ $mr->id }})" />
                                                                            <x-button class="btn-ghost btn-xs text-error" icon="o-trash"
                                                                                :aria-label="__('Delete the result')"
                                                                                wire:click="confirmDelete({{ $mr->id }})" />
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif
                                        </div>
                                    </x-card>
                                </div>
                            @endforeach
                        </div>
                </x-section-accordion>
            @endforeach
        </div>
    @endif

    {{-- ── Modal Edit result ──────────────────────────────────────────────── --}}
    <x-app-modal wire:model="editModal" :title="__('Edit match')" :open="$editModal">
        <div class="space-y-5">
            {{-- Context (read-only) --}}
            <div class="flex flex-wrap items-center gap-3 rounded-lg bg-base-200 px-4 py-3 text-sm">
                @if ($matchDate)
                    <span class="text-base-content/60">{{ \Carbon\Carbon::parse($matchDate)->format('d/m/Y') }}</span>
                    <span class="text-base-content/20">|</span>
                @endif
                @if ($opponentName)
                    <span class="font-medium">{{ $opponentName }}</span>
                @endif
                <span @class([
                    'rounded px-2 py-0.5 text-xs font-semibold',
                    'bg-blue-100 text-blue-700' => $isHome,
                    'bg-base-300 text-base-content/60' => ! $isHome,
                ])>
                    {{ $isHome ? __('Home') : __('Away') }}
                </span>
            </div>

            {{-- Situation (first so it's always visible) --}}
            <x-select
                :label="__('Situation')"
                :options="$matchTypeOptions"
                option-label="label"
                option-value="value"
                wire:model.live="matchType" />

            {{-- Score inputs (only for normal played matches) --}}
            @if ($matchType === 'normal')
                @php $maxPts = $this->maxPoints(); @endphp
                <div class="flex flex-col gap-2">
                    <div class="flex items-end gap-3">
                        <x-input
                            class="text-center"
                            :label="__('Our score')"
                            type="number"
                            min="0"
                            max="{{ $maxPts }}"
                            wire:model="scoreUs" />
                        <span class="mb-2 text-2xl font-light text-base-content/30">—</span>
                        <x-input
                            class="text-center"
                            :label="__('Opponent score')"
                            type="number"
                            min="0"
                            max="{{ $maxPts }}"
                            wire:model="scoreThem" />
                    </div>
                    <p class="text-xs text-base-content/40">{{ __('Total: :max points per match', ['max' => $maxPts]) }}</p>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('editModal', false)" />
            <x-button class="btn-primary" :label="__('Save')" wire:click="save" wire:loading.attr="disabled" />
        </x-slot:actions>
    </x-app-modal>

    <x-confirm-modal model="deleteModal" :title="__('Delete match?')" :subtitle="__('Warning!')"
        :confirmLabel="__('Delete')" confirmAction="delete" :open="$deleteModal">
        <p>{{ __('Are you sure you want to delete this match? This action cannot be undone.') }}</p>
    </x-confirm-modal>

    <x-confirm-modal model="teamForfeitModal" :title="__('Declare general forfeit?')"
        :confirmLabel="__('Confirm')" confirmClass="btn-warning" confirmAction="declareTeamForfeit" :open="$teamForfeitModal">
        <p>{{ __('All unplayed matches for this team will be marked as general forfeit (Withdrawal). This action cannot be easily undone.') }}</p>
    </x-confirm-modal>
</div>
