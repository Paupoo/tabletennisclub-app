<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header title="{{ __('Club Overview') }}"
        subtitle="{{ __('Season') }} {{ $current_season?->name ?? '—' }}"
        separator>
        <x-slot:actions>
            <x-button label="{{ __('Captain Dashboard') }}" icon="o-user" class="btn-sm btn-ghost"
                :link="route('admin.interclubs.captain-selection')" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">

        {{-- COLONNE GAUCHE : NAVIGATION & SANTÉ --}}
        <div class="space-y-4">
            <x-admin.shared.side-card title="{{ __('Navigation') }}" shadow class="mt-16">
                <div class="space-y-4">
                    <x-select
                        label="{{ __('Season') }}"
                        wire:model.live="selectedSeasonId"
                        :options="$seasons_list"
                        class="select-sm border-none bg-base-200/50 font-bold" />

                    <div>
                        <label
                            class="mb-4 block text-[10px] font-black italic uppercase tracking-widest opacity-40">{{ __('Competition week') }}</label>
                        <div class="flex items-center gap-2">
                            <x-button icon="o-chevron-left" class="btn-sm btn-ghost bg-base-200"
                                wire:click="prevWeek" />
                            <x-select wire:model.live="selectedWeek" :options="$weeks_options"
                                class="select-sm flex-1 border-none bg-base-200/50 font-bold" />
                            <x-button icon="o-chevron-right" class="btn-sm btn-ghost bg-base-200"
                                wire:click="nextWeek" />
                        </div>
                    </div>

                    <x-choices label="{{ __('Focus on a team') }}" wire:model.live="selectedTeam" :options="$teams_list"
                        single searchable class="choices-sm" />

                    <div class="space-y-2 border-t border-base-100 pt-2">
                        <x-checkbox label="{{ __('Show issues only') }}" wire:model.live="filterAlerts" tight />
                    </div>
                </div>
            </x-admin.shared.side-card>

            <x-admin.shared.side-card class="border border-base-200 bg-base-100 shadow-sm">
                <div
                    class="mb-4 text-center text-[10px] font-black italic uppercase tracking-widest text-base-content opacity-50">
                    {{ __(':n weeks', ['n' => $total_weeks]) }}
                </div>
                <div class="grid grid-cols-5 gap-2 justify-items-center">
                    @foreach ($weeks_monitor as $wm)
                        <div class="tooltip" data-tip="{{ __('Week :n') . ': ' . $wm['status'] }}">
                            <button wire:click="$set('selectedWeek', {{ $wm['wk'] }})"
                                @class([
                                    'w-7 h-7 rounded-md flex items-center justify-center text-[9px] font-black border transition-all hover:scale-110',
                                    'bg-success border-success text-white shadow-sm' => $wm['status'] === 'ok',
                                    'bg-warning border-warning text-black' => $wm['status'] === 'warning',
                                    'bg-error border-error text-white shadow-md' => $wm['status'] === 'nok',
                                    'bg-base-200 border-base-300 text-base-content/40' => $wm['status'] === 'pending',
                                    'ring-2 ring-primary ring-offset-2 ring-offset-base-100' => $wm['wk'] === $selectedWeek,
                                ])>
                                {{ $wm['wk'] }}
                            </button>
                        </div>
                    @endforeach
                </div>
                <div class="mt-6 flex items-center justify-between border-t border-base-200 px-1 pt-4">
                    <span
                        class="text-[9px] font-black uppercase tracking-tighter opacity-50">{{ __('Preparation score') }}</span>
                    <span class="text-xl font-black text-success">
                        {{ $preparation_score }}<span
                            class="text-xs text-base-content/50 opacity-50">/{{ $total_weeks }}</span>
                    </span>
                </div>
            </x-admin.shared.side-card>
        </div>

        {{-- COLONNE DROITE : LISTE --}}
        <div class="lg:col-span-3">

            <x-card shadow class="overflow-hidden border-none bg-base-100 p-0" wire:loading.class="opacity-50">
                <div class="mb-6 flex flex-wrap gap-4 px-1">
                    <div class="flex items-center gap-1.5">
                        <div class="h-2 w-2 rounded-full bg-success"></div>
                        <span class="text-[10px] font-bold uppercase opacity-60">{{ __('Complete') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="h-2 w-2 rounded-full bg-warning"></div>
                        <span class="text-[10px] font-bold uppercase opacity-60">{{ __('Incomplete') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="h-2 w-2 animate-pulse rounded-full bg-error"></div>
                        <span class="text-[10px] font-bold uppercase opacity-60">{{ __('No selection') }}</span>
                    </div>
                </div>

                @if ($categories->isEmpty())
                    <x-empty-state icon="o-calendar"
                        title="{{ __('No matches this week') }}"
                        description="{{ __('No interclub scheduled for week :n.', ['n' => $selectedWeek ?? '—']) }}" />
                @else
                    @foreach ($categories as $name => $teams)
                        <div
                            class="flex items-center justify-between border-y border-base-300/20 bg-base-200/50 px-4 py-2">
                            <span
                                class="text-[10px] font-black uppercase tracking-widest text-base-content/60">{{ $name }}</span>
                            <span class="text-[9px] font-bold opacity-40">{{ count($teams) }}
                                {{ __('teams') }}</span>
                        </div>
                        <x-table :headers="$headers" :rows="$teams" :no-headers="!$loop->first" class="table-compact">
                            @scope('cell_name', $team)
                                <div class="flex items-center gap-3 py-1">
                                    <div @class([
                                        'w-1.5 h-6 rounded-full',
                                        'bg-success' => $team['status'] === 'validated',
                                        'bg-warning' => $team['status'] === 'pending',
                                        'bg-error animate-pulse' => $team['status'] === 'alert',
                                    ])></div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-bold leading-none">{{ $team['name'] }}</span>
                                        </div>
                                        <span
                                            class="text-[9px] font-black uppercase tracking-tighter opacity-40">{{ $team['div'] }}</span>
                                    </div>
                                </div>
                            @endscope

                            @scope('cell_captain', $team)
                                <div class="flex items-center gap-2">
                                    <x-avatar placeholder="{{ mb_substr($team['captain'], 0, 1) }}"
                                        class="h-6 w-6 bg-neutral text-[10px] font-black text-white" />
                                    <span class="text-xs font-medium">{{ $team['captain'] }}</span>
                                </div>
                            @endscope

                            @scope('cell_players', $team)
                                <div class="flex justify-center gap-1">
                                    @for ($i = 1; $i <= $team['max_players']; $i++)
                                        <div @class([
                                            'w-2 h-4 rounded-sm transition-all',
                                            'bg-primary' => $i <= $team['players'],
                                            'bg-base-300' => $i > $team['players'],
                                        ])></div>
                                    @endfor
                                </div>
                            @endscope

                            @scope('cell_status', $team)
                                @if ($team['status'] === 'validated')
                                    <div class="flex items-center gap-1 text-success">
                                        <x-icon name="o-check-circle" class="h-4 w-4" />
                                        <span class="text-[10px] font-black uppercase">{{ __('Complete') }}</span>
                                    </div>
                                @elseif($team['status'] === 'pending')
                                    <div class="flex items-center gap-1 text-warning">
                                        <x-icon name="o-clock" class="h-4 w-4" />
                                        <span class="text-[10px] font-black uppercase">{{ __('Incomplete') }}</span>
                                    </div>
                                @else
                                    <div class="flex animate-pulse items-center gap-1 text-error">
                                        <x-icon name="o-exclamation-triangle" class="h-4 w-4" />
                                        <span class="text-[10px] font-black uppercase">{{ __('Missing') }}</span>
                                    </div>
                                @endif
                            @endscope

                            @scope('cell_action', $team)
                                @if ($team['id'])
                                    <x-button icon="o-pencil-square"
                                        class="btn-ghost btn-xs text-base-content/40 hover:text-primary"
                                        wire:click="openSelection({{ $team['id'] }})" />
                                @endif
                            @endscope
                        </x-table>
                    @endforeach
                @endif
            </x-card>
        </div>
    </div>

    {{-- DRAWER SÉLECTION --}}
    <x-drawer wire:model="drawerSelection"
        title="{{ __('Selection') }} {{ $drawerInterclub ? 'WK' . $drawerInterclub->week_number : '' }}"
        subtitle="{{ $drawerInterclub?->start_date_time?->format('d/m/Y') ?? '' }}"
        right separator with-close-button class="w-11/12 lg:w-1/3">
        <div class="space-y-6">
            <div>
                <div class="mb-2 flex justify-between text-[10px] font-black uppercase">
                    <span>{{ __('Selected') }}</span>
                    <span @class(['text-success font-black' => count($selectedPlayerIds) == $drawerMaxPlayers])>
                        {{ count($selectedPlayerIds) }} / {{ $drawerMaxPlayers }}
                    </span>
                </div>
                <progress @class([
                    'progress w-full h-2 transition-all duration-500',
                    'progress-primary' => count($selectedPlayerIds) < $drawerMaxPlayers,
                    'progress-success' => count($selectedPlayerIds) == $drawerMaxPlayers,
                ]) value="{{ count($selectedPlayerIds) }}" max="{{ $drawerMaxPlayers }}"></progress>
            </div>

            <div>
                <div class="mb-3 text-[10px] font-black uppercase tracking-widest opacity-40">
                    {{ __('Team roster') }}
                </div>
                <div class="space-y-2">
                    @foreach ($drawerRoster as $player)
                        @php $isSelected = in_array($player['id'], $selectedPlayerIds); @endphp
                        <div wire:click="togglePlayer({{ $player['id'] }})" @class([
                            'p-3 rounded-xl border cursor-pointer transition-all flex items-center justify-between group',
                            'border-primary bg-primary/5 ring-1 ring-primary' => $isSelected,
                            'border-base-200 hover:border-primary/50 bg-base-100' => !$isSelected,
                        ])>
                            <div class="flex items-center gap-3">
                                <x-avatar placeholder="{{ mb_substr($player['name'], 0, 1) }}" @class([
                                    '!w-9 !rounded-lg font-black',
                                    $isSelected ? 'bg-primary text-primary-content' : 'bg-base-200',
                                ]) />
                                <div>
                                    <div class="text-xs font-black">{{ $player['name'] }}</div>
                                    <div class="text-[9px] font-bold uppercase opacity-50">{{ $player['rank'] }}</div>
                                </div>
                            </div>
                            <div @class([
                                'w-5 h-5 rounded border flex items-center justify-center',
                                'bg-primary border-primary text-primary-content' => $isSelected,
                                'border-base-300 bg-white' => !$isSelected,
                            ])>
                                @if ($isSelected)
                                    <x-icon name="o-check" class="h-3 w-3" />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="border-t border-dashed border-base-300 pt-4">
                <div class="mb-3 text-[10px] font-black uppercase tracking-widest opacity-40">
                    {{ __('Search a substitute') }}
                </div>
                <x-input placeholder="{{ __('Player name...') }}" icon="o-magnifying-glass"
                    wire:model.live.debounce.300ms="search"
                    class="input-sm rounded-lg border-none bg-base-200/50" />
                @if (strlen($search) >= 2)
                    <div class="mt-4 animate-in fade-in slide-in-from-top-2 space-y-2">
                        @forelse($searchResults as $res)
                            @php $isSelected = in_array($res['id'], $selectedPlayerIds); @endphp
                            <div wire:click="togglePlayer({{ $res['id'] }})" @class([
                                'p-2 rounded-lg border border-dashed flex items-center justify-between cursor-pointer transition-all',
                                'border-primary bg-primary/5' => $isSelected,
                                'border-base-300 hover:border-primary' => !$isSelected,
                            ])>
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-user-plus" class="h-4 w-4 opacity-40" />
                                    <div class="flex flex-col">
                                        <span class="text-[11px] font-bold">{{ $res['name'] }}</span>
                                        <span class="text-[9px] uppercase opacity-50">{{ $res['rank'] }}</span>
                                    </div>
                                </div>
                                @if ($isSelected)
                                    <x-icon name="o-check-circle" class="h-5 w-5 text-primary" />
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
            <x-button label="{{ __('Cancel') }}" @click="$wire.drawerSelection = false" class="btn-ghost" />
            <x-button label="{{ __('Confirm') }}" wire:click="saveSelection" class="btn-primary" icon="o-check"
                :disabled="count($selectedPlayerIds) === 0" />
        </x-slot:actions>
    </x-drawer>

    {{-- MODAL MESSAGE --}}
    <x-modal wire:model="modalMessage" title="{{ __('Last step') }}" separator>
        <div class="space-y-4">
            <div class="flex items-center gap-3 rounded-xl border border-primary/10 bg-primary/5 p-3">
                <x-icon name="o-information-circle" class="h-5 w-5 text-primary" />
                <p class="text-xs font-medium">
                    {{ __('Your selection is ready. Add a message for your players.') }}</p>
            </div>

            <x-textarea label="{{ __('Message to players') }}" wire:model="captainMessage"
                placeholder="{{ __('E.g. Departure at 18:45 from the club...') }}" rows="4"
                class="border-none bg-base-200/50 focus:ring-primary" />
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Skip') }}" wire:click="confirmAndSend" class="btn-ghost" />
            <x-button label="{{ __('Send') }}" wire:click="confirmAndSend" class="btn-primary"
                icon="o-paper-airplane" />
        </x-slot:actions>
    </x-modal>
</div>
