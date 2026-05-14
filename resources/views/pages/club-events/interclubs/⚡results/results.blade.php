<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator title="{{ __('Results') }}">
        <x-slot:middle class="justify-end!">
            <x-select
                :options="$seasons"
                option-label="name"
                option-value="id"
                wire:model.live="seasonId"
                placeholder="{{ __('Select a season') }}" />
        </x-slot:middle>
    </x-header>

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
                'MEN'      => ['label' => 'Hommes',   'bg' => 'bg-blue-50',  'border' => 'border-blue-200', 'text' => 'text-blue-700',  'dot' => 'bg-blue-500'],
                'WOMEN'    => ['label' => 'Dames',    'bg' => 'bg-pink-50',  'border' => 'border-pink-200', 'text' => 'text-pink-700',  'dot' => 'bg-pink-500'],
                'VETERANS' => ['label' => 'Vétérans', 'bg' => 'bg-amber-50', 'border' => 'border-amber-200','text' => 'text-amber-700', 'dot' => 'bg-amber-500'],
            ];
        @endphp

        <div class="space-y-10">
            @foreach ($teamsByCategory as $catName => $categoryTeams)
                @php $cat = $catMeta[$catName] ?? ['label' => $catName, 'bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-700', 'dot' => 'bg-gray-400']; @endphp

                {{-- ── Category header ─────────────────────────────────────── --}}
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-2 rounded-full {{ $cat['bg'] }} {{ $cat['border'] }} border px-4 py-1.5">
                        <span class="h-2 w-2 rounded-full {{ $cat['dot'] }}"></span>
                        <span class="text-sm font-bold {{ $cat['text'] }} uppercase tracking-wide">{{ $cat['label'] }}</span>
                        <span class="text-xs {{ $cat['text'] }} opacity-60">{{ $categoryTeams->count() }} équipe{{ $categoryTeams->count() > 1 ? 's' : '' }}</span>
                    </span>
                    <div class="flex-1 border-t {{ $cat['border'] }}"></div>
                </div>

                {{-- ── Teams in this category ───────────────────────────────── --}}
                <div class="space-y-6">
                    @foreach ($categoryTeams as $team)
                        @php
                            $teamStats = $stats[$team->id];
                            $division  = $team->league?->division;
                        @endphp

                        <x-card>
                            {{-- En-tête équipe --}}
                            <x-slot:title>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-bold text-gray-900">
                                        {{ __('Team') }} {{ $team->name }}
                                        @if ($division)
                                            <span class="ml-1 text-sm font-normal text-gray-500">— Div. {{ $division }}</span>
                                        @endif
                                    </span>

                                    {{-- Stats badges --}}
                                    @if ($teamStats['played'] > 0)
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">
                                            {{ $teamStats['wins'] }}V
                                        </span>
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">
                                            {{ $teamStats['losses'] }}D
                                        </span>
                                        @if ($teamStats['draws'] > 0)
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">
                                                {{ $teamStats['draws'] }}N
                                            </span>
                                        @endif
                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                            {{ $teamStats['win_rate'] }}%
                                        </span>
                                    @endif
                                </div>
                            </x-slot:title>

                            <x-slot:actions>
                                <div class="flex flex-wrap items-center gap-2">
                                    {{-- Position finale --}}
                                    <div class="flex items-center gap-2">
                                        <span class="hidden text-xs text-gray-500 sm:inline">{{ __('Final position') }} :</span>
                                        <x-input
                                            class="input-sm w-28 text-xs sm:w-36"
                                            placeholder="ex : 1ère place"
                                            value="{{ $team->final_position }}"
                                            wire:change="updateFinalPosition({{ $team->id }}, $event.target.value)" />
                                    </div>
                                    <x-button
                                        class="btn-sm btn-warning"
                                        icon="o-exclamation-triangle"
                                        label="{{ __('Forfait') }}"
                                        tooltip="{{ __('Déclarer le forfait général') }}"
                                        wire:click="openTeamForfeitModal({{ $team->id }})" />
                                    <x-button
                                        class="btn-primary btn-sm"
                                        icon="o-plus"
                                        label="{{ __('Ajouter') }}"
                                        tooltip="{{ __('Ajouter une rencontre') }}"
                                        wire:click="openAddModal({{ $team->id }})" />
                                </div>
                            </x-slot:actions>

                            {{-- Tableau des rencontres --}}
                            @if ($team->matchResults->isEmpty())
                                <p class="py-4 text-center text-sm italic text-gray-400">{{ __('No matches yet. Add the first one.') }}</p>
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
                                            @foreach ($team->matchResults as $mr)
                                                <tr wire:key="mr-{{ $mr->id }}"
                                                    @class(['text-gray-300' => $mr->is_bye, 'italic text-gray-400' => ! $mr->is_bye && $mr->result === null])>
                                                    <td class="hidden py-2 pr-4 text-xs text-gray-400 sm:table-cell">
                                                        {{ $mr->week_number ? 'S' . $mr->week_number : '—' }}
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
                                                                'rounded px-1.5 py-0.5 text-[10px] font-semibold',
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
                                                            <span class="rounded bg-gray-50 px-1.5 py-0.5 text-[10px] font-semibold text-gray-400">Bye</span>
                                                        @elseif ($mr->result === null)
                                                            <span class="text-xs italic text-gray-300">{{ __('Pending') }}</span>
                                                        @elseif ($mr->result === \App\Enums\InterclubResult::WIN)
                                                            <span class="rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-semibold text-green-700">{{ __('Win') }}</span>
                                                        @elseif ($mr->result === \App\Enums\InterclubResult::LOSS)
                                                            <span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">{{ __('Loss') }}</span>
                                                        @elseif ($mr->result === \App\Enums\InterclubResult::DRAW)
                                                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-600">{{ __('Draw') }}</span>
                                                        @elseif ($mr->result === \App\Enums\InterclubResult::FORFEIT_WIN)
                                                            <span class="rounded bg-green-50 px-1.5 py-0.5 text-[10px] font-semibold text-green-600">{{ __('Forfait adv.') }}</span>
                                                        @elseif ($mr->result === \App\Enums\InterclubResult::FORFEIT_LOSS)
                                                            <span class="rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold text-red-400">{{ __('Forfait') }}</span>
                                                        @elseif ($mr->result === \App\Enums\InterclubResult::WITHDRAWAL_OPPONENT)
                                                            <span class="rounded bg-orange-50 px-1.5 py-0.5 text-[10px] font-semibold text-orange-500">{{ __('F. Gén. Adv.') }}</span>
                                                        @elseif ($mr->result === \App\Enums\InterclubResult::WITHDRAWAL)
                                                            <span class="rounded bg-orange-100 px-1.5 py-0.5 text-[10px] font-semibold text-orange-600">{{ __('F. Général') }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-2 text-right">
                                                        <div class="flex justify-end gap-1">
                                                            <x-button class="btn-ghost btn-xs" icon="o-pencil"
                                                                wire:click="openEditModal({{ $mr->id }})" />
                                                            <x-button class="btn-ghost btn-xs text-error" icon="o-trash"
                                                                wire:click="confirmDelete({{ $mr->id }})" />
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </x-card>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── Modal Add / Edit ────────────────────────────────────────────────── --}}
    <x-modal wire:model="editModal" title="{{ $editingMatchResultId ? __('Edit match') : __('Add a match') }}">
        <div class="space-y-4">
            <x-select
                label="{{ __('Match type') }}"
                :options="$matchTypeOptions"
                option-label="label"
                option-value="value"
                wire:model.live="matchType" />

            @if (! in_array($matchType, ['bye', 'forfeit_general_us']))
                <x-input label="{{ __('Date') }}" type="date" wire:model="matchDate" />
                <x-toggle label="{{ __('Home match') }}" wire:model="isHome" />
                <x-input label="{{ __('Opponent') }}" placeholder="ex : Arc En Ciel F" wire:model="opponentName" />
            @endif

            @if ($matchType === 'normal')
                <x-input
                    label="{{ __('Score') }}"
                    placeholder="ex : 15-1"
                    hint="{{ __('Left = home score, right = away score. Result is calculated automatically.') }}"
                    wire:model="score" />
            @endif
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" wire:click="$set('editModal', false)" />
            <x-button class="btn-primary" label="{{ __('Save') }}" wire:click="save" wire:loading.attr="disabled" />
        </x-slot:actions>
    </x-modal>

    {{-- ── Modal Delete confirmation ───────────────────────────────────────── --}}
    <x-modal wire:model="deleteModal" title="{{ __('Delete match') }}">
        <p class="text-sm text-gray-600">{{ __('Are you sure you want to delete this match? This action cannot be undone.') }}</p>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" wire:click="$set('deleteModal', false)" />
            <x-button class="btn-error" label="{{ __('Delete') }}" wire:click="delete" wire:loading.attr="disabled" />
        </x-slot:actions>
    </x-modal>

    {{-- ── Modal Forfait Général équipe ───────────────────────────────────── --}}
    <x-modal wire:model="teamForfeitModal" title="{{ __('Declare general forfeit') }}">
        <p class="text-sm text-gray-600">
            {{ __('All unplayed matches for this team will be marked as general forfeit (Withdrawal). This action cannot be easily undone.') }}
        </p>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" wire:click="$set('teamForfeitModal', false)" />
            <x-button class="btn-warning" label="{{ __('Confirm') }}" wire:click="declareTeamForfeit" wire:loading.attr="disabled" />
        </x-slot:actions>
    </x-modal>
</div>
