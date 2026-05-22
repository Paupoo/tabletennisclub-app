<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header separator subtitle="{{ $selectedTeam?->league?->division ?? __('Select a team') }}"
        :title="__('Sélections')">
        <x-slot:actions>
            <x-button class="btn-ghost btn-sm" icon="o-arrow-right" label="{{ __('Mes matchs') }}"
                :link="route('admin.interclubs.my-matches')" />
        </x-slot:actions>
    </x-header>

    {{-- Bandeau résumé hebdomadaire — admin / comité uniquement --}}
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
                            'ok' => 'bg-success',
                            'warning' => 'bg-warning',
                            'nok' => 'bg-error animate-pulse',
                            default => 'bg-base-300',
                        };
                    @endphp
                    <div class="flex flex-col items-center gap-0.5">
                        <div class="{{ $dot }} h-2 w-2 rounded-full"></div>
                        <span class="text-[8px] font-black opacity-30">{{ $wk['wk'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($teams_list->isEmpty())
        <x-empty-state icon="o-user-group" title="{{ __('No team assigned') }}"
            description="{{ __('You are not captain of any team this season.') }}" />
    @else
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">
            {{-- COLONNE GAUCHE --}}
            <div class="space-y-6">
                <x-admin.shared.side-card shadow title="{{ __('Select a team') }}">
                    <x-select
                        label="{{ __('Season') }}"
                        :options="$seasons_list"
                        wire:model.live="selectedSeasonId"
                        class="select-sm border-none bg-base-200/50 font-bold" />
                    <x-choices label="{{ __('Team') }}" :options="$teams_list" wire:model.live="selectedTeamId" single />
                </x-admin.shared.side-card>

                @if ($selectedTeam)
                    <x-admin.shared.side-card icon="o-users" separator shadow title="{{ __('Roster') }}">
                        <div class="space-y-1">
                            @foreach ($roster as $player)
                                <x-list-item :item="[]" class="!p-1" no-hover no-separator>
                                    <x-slot:avatar>
                                        <x-avatar
                                            class="!w-7 bg-base-200 text-[10px] font-black"
                                            placeholder="{{ mb_substr($player['name'], 0, 1) }}" />
                                    </x-slot:avatar>
                                    <x-slot:value>
                                        <span class="text-xs font-bold">{{ $player['name'] }}</span>
                                    </x-slot:value>
                                    <x-slot:sub-value class="text-[9px]">
                                        {{ $player['rank'] }}
                                        @if (isset($player['availability']) && $player['availability'])
                                            · <span
                                                class="{{ $player['availability']->color() }} badge badge-xs font-medium">{{ $player['availability']->label() }}</span>
                                        @endif
                                    </x-slot:sub-value>
                                </x-list-item>
                            @endforeach
                        </div>
                    </x-admin.shared.side-card>
                @endif
            </div>

            {{-- COLONNE DROITE --}}
            <div class="space-y-3 lg:col-span-3">
                <div class="mb-6 flex gap-2">
                    <x-badge class="badge-neutral" value="{{ __('All weeks') }}" />
                    @php
                        $pendingCount = $interclubs->where('status', 'pending')->count() + $interclubs->where('status', 'future')->count();
                        $readyCount = $interclubs->whereIn('status', ['ready', 'confirmed'])->count();
                    @endphp
                    @if ($pendingCount > 0)
                        <x-badge class="badge-warning font-bold"
                            value="{{ __('Pending (:n)', ['n' => $pendingCount]) }}" />
                    @endif
                    @if ($readyCount > 0)
                        <x-badge class="badge-success" value="{{ __('Ready (:n)', ['n' => $readyCount]) }}" />
                    @endif
                </div>

                @if ($interclubs->isEmpty())
                    <x-empty-state icon="o-calendar" title="{{ __('No upcoming matches') }}"
                        description="{{ __('No interclub matches scheduled for this team.') }}" />
                @else
                    <div class="divide-base-200 border-base-200 divide-y overflow-hidden rounded-xl border">
                        @foreach ($interclubs as $ic)
                            <div class="group">
                                <x-admin.club-events.interclubs.week-card
                                    :date="$ic['date'] . ' ' . $ic['time']"
                                    :opponent="$ic['opp']"
                                    :selection-count="$ic['selection_count']"
                                    :status="$ic['status']"
                                    :week="$ic['wk']" />

                                @if (in_array($ic['status'], ['pending', 'future', 'ready']))
                                    <div
                                        class="border-base-200 flex items-center gap-2 border-t bg-base-50 px-4 py-2">
                                        <x-button
                                            class="btn-ghost btn-xs text-base-content/50 hover:text-primary"
                                            icon="o-pencil-square"
                                            label="{{ __('Select players') }}"
                                            wire:click="openSelection({{ $ic['id'] }})" />
                                        <x-button
                                            class="btn-ghost btn-xs text-base-content/50 hover:text-info"
                                            icon="o-envelope"
                                            label="{{ __('Request availability') }}"
                                            wire:click="requestAvailability({{ $ic['id'] }})" />
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- DRAWER SÉLECTION --}}
    <x-drawer class="w-11/12 lg:w-1/3" right separator
        subtitle="{{ $drawerInterclub?->start_date_time?->format('d/m/Y') ?? '' }}"
        title="{{ __('Selection') }} {{ $drawerInterclub ? 'WK' . $drawerInterclub->week_number : '' }}"
        wire:model="drawerSelection" with-close-button>
        <div class="space-y-6">
            <div>
                <div class="mb-2 flex justify-between text-[10px] font-black uppercase">
                    <span>{{ __('Selected') }}</span>
                    <span @class(['text-success font-black' => count($selectedPlayerIds) == $maxPlayers])>
                        {{ count($selectedPlayerIds) }} / {{ $maxPlayers }}
                    </span>
                </div>
                <progress @class([
                    'progress w-full h-2 transition-all duration-500',
                    'progress-primary' => count($selectedPlayerIds) < $maxPlayers,
                    'progress-success' => count($selectedPlayerIds) == $maxPlayers,
                ]) max="{{ $maxPlayers }}" value="{{ count($selectedPlayerIds) }}"></progress>
            </div>

            <div>
                <div class="mb-3 text-[10px] font-black uppercase tracking-widest opacity-40">
                    {{ __('Team roster') }}
                </div>
                <div class="space-y-2">
                    @foreach ($roster as $player)
                        @php $isSelected = in_array($player['id'], $selectedPlayerIds); @endphp
                        <div @class([
                            'p-3 rounded-xl border cursor-pointer transition-all flex items-center justify-between group',
                            'border-primary bg-primary/5 ring-1 ring-primary' => $isSelected,
                            'opacity-40 grayscale pointer-events-none' =>
                                isset($player['availability']) &&
                                $player['availability'] === \App\Enums\InterclubAvailability::UNAVAILABLE,
                            'border-base-200 hover:border-primary/50 bg-base-100' => !$isSelected &&
                                (!isset($player['availability']) || $player['availability'] !== \App\Enums\InterclubAvailability::UNAVAILABLE),
                        ]) wire:click="togglePlayer({{ $player['id'] }})">
                            <div class="flex items-center gap-3">
                                <x-avatar @class([
                                    '!w-9 !rounded-lg font-black',
                                    $isSelected ? 'bg-primary text-primary-content' : 'bg-base-200',
                                ]) placeholder="{{ mb_substr($player['name'], 0, 1) }}" />
                                <div>
                                    <div class="text-xs font-black">{{ $player['name'] }}</div>
                                    <div class="text-[9px] font-bold uppercase opacity-50">
                                        {{ $player['rank'] }}
                                        @if (isset($player['availability']) && $player['availability'])
                                            ·
                                            <span
                                                class="{{ $player['availability']->color() }}">{{ $player['availability']->label() }}</span>
                                        @else
                                            · {{ __('No response') }}
                                        @endif
                                    </div>
                                    @if (!empty($player['availability_note']))
                                        <div class="text-[9px] italic opacity-40">"{{ $player['availability_note'] }}"</div>
                                    @endif
                                </div>
                            </div>
                            <div @class([
                                'w-5 h-5 rounded border flex items-center justify-center',
                                'bg-primary border-primary text-primary-content' => $isSelected,
                                'border-base-300 bg-white' => !$isSelected,
                            ])>
                                @if ($isSelected)
                                    <x-icon class="h-3 w-3" name="o-check" />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="border-base-300 border-t border-dashed pt-4">
                <div class="mb-3 text-[10px] font-black uppercase tracking-widest opacity-40">
                    {{ __('Search a substitute') }}
                </div>
                <x-input class="input-sm bg-base-200/50 rounded-lg border-none" icon="o-magnifying-glass"
                    placeholder="{{ __('Player name...') }}" wire:model.live.debounce.300ms="search" />
                @if (strlen($search) >= 2)
                    <div class="animate-in fade-in slide-in-from-top-2 mt-4 space-y-2">
                        @forelse($searchResults as $res)
                            @php $isSelected = in_array($res['id'], $selectedPlayerIds); @endphp
                            <div @class([
                                'p-2 rounded-lg border border-dashed flex items-center justify-between cursor-pointer transition-all',
                                'border-primary bg-primary/5' => $isSelected,
                                'border-base-300 hover:border-primary' => !$isSelected,
                            ]) wire:click="togglePlayer({{ $res['id'] }})">
                                <div class="flex items-center gap-2">
                                    <x-icon class="h-4 w-4 opacity-40" name="o-user-plus" />
                                    <div class="flex flex-col">
                                        <span class="text-[11px] font-bold">{{ $res['name'] }}</span>
                                        <span class="text-[9px] uppercase opacity-50">{{ $res['rank'] }}</span>
                                    </div>
                                </div>
                                @if ($isSelected)
                                    <x-icon class="text-primary h-5 w-5" name="o-check-circle" />
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
            <x-button @click="$wire.drawerSelection = false" class="btn-ghost" label="{{ __('Cancel') }}" />
            <x-button :disabled="count($selectedPlayerIds) === 0" class="btn-primary" icon="o-check"
                label="{{ __('Confirm') }}" wire:click="saveSelection" />
        </x-slot:actions>
    </x-drawer>

    {{-- MODAL MESSAGE --}}
    <x-modal separator title="{{ __('Last step') }}" wire:model="modalMessage">
        <div class="space-y-4">
            <div class="bg-primary/5 border-primary/10 flex items-center gap-3 rounded-xl border p-3">
                <x-icon class="text-primary h-5 w-5" name="o-information-circle" />
                <p class="text-xs font-medium">{{ __('Your selection is ready. Add a message for your players.') }}
                </p>
            </div>

            <x-textarea class="bg-base-200/50 focus:ring-primary border-none"
                label="{{ __('Message to players') }}"
                placeholder="{{ __('E.g. Departure at 18:45 from the club...') }}" rows="4"
                wire:model="captainMessage" />
        </div>

        <x-slot:actions>
            <x-button class="btn-ghost" label="{{ __('Skip') }}" wire:click="confirmAndSend" />
            <x-button class="btn-primary" icon="o-paper-airplane" label="{{ __('Send') }}"
                wire:click="confirmAndSend" />
        </x-slot:actions>
    </x-modal>
</div>
