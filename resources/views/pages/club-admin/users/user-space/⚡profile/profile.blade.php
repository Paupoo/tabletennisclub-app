@php
    if (!isset($user)) { $user = App\Models\User::firstOrFail(); }
@endphp

<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header title="{{ __('My Profile') }}" subtitle="{{ __('Member since Sept 2024') }}" separator progress-indicator>
        <x-slot:actions>
            <x-button label="{{ __('Edit Profile') }}" icon="o-pencil" class="btn-outline btn-sm"
                @click="$wire.drawer = true" responsive />
        </x-slot:actions>
    </x-header>

    <div class="flex gap-8 items-start">

        {{-- ════════════════════════════════
             SIDEBAR GAUCHE
        ════════════════════════════════ --}}
        <div class="w-72 shrink-0 space-y-4">

            {{-- Avatar + nom + badges --}}
           
            <x-admin.shared.side-card shadow>
                <div class="flex flex-col items-center text-center gap-3">
                <x-avatar :image="$user->photo ?? '/images/empty-user.jpg'" class="!w-24 !rounded-full" />
                <div>
                    <div class="font-bold text-xl">{{ $user->first_name }} {{ $user->last_name }}</div>
                    <div class="text-sm opacity-50 mt-0.5">{{ $user->is_active ? __('Active member') : __('Inactive member') }}</div>
                </div>
                <div class="flex flex-wrap justify-center gap-1">
                    @if ($user->is_admin)
                        <x-badge value="{{ __('Admin') }}" icon="o-power" class="badge-primary badge-sm" />
                    @endif
                    @if ($user->is_committee_member)
                        <x-badge :value="$user->committee_role->label()" icon="o-star" class="badge-secondary badge-sm text-black" />
                    @endif
                    @if (!$user->is_active)
                        <x-badge value="{{ __('Inactive') }}" class="badge-neutral badge-sm" />
                    @endif
                </div>
                <x-button label="{{ __('Edit') }}" icon="o-pencil" class="btn-outline btn-sm w-fit"
                    @click="$wire.drawer = true" />
            </div>
            </x-admin.shared.side-card>

            {{-- Infos contact --}}
            <x-admin.shared.side-card shadow>
            <div class="divide-y divide-base-200">
                <div class="flex items-center gap-3 px-4 py-3">
                    <x-icon name="o-shield-check" class="w-4 h-4 text-success shrink-0" />
                    <div class="min-w-0">
                        <div class="text-[10px] opacity-40 uppercase tracking-wider font-black">{{ __('Status') }}</div>
                        <div class="text-sm font-semibold text-success truncate">{{ __('Active · till 08/2026') }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-4 py-3">
                    <x-icon name="o-chevron-double-up" class="w-4 h-4 opacity-40 shrink-0" />
                    <div class="min-w-0">
                        <div class="text-[10px] opacity-40 uppercase tracking-wider font-black">{{ __('Ranking') }}</div>
                        <div class="text-sm font-semibold truncate">{{ $user?->ranking ?? 'B4' }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-4 py-3">
                    <x-icon name="o-identification" class="w-4 h-4 opacity-40 shrink-0" />
                    <div class="min-w-0">
                        <div class="text-[10px] opacity-40 uppercase tracking-wider font-black">{{ __('Licence') }}</div>
                        <div class="text-sm font-semibold truncate">
                            {{ $user->is_competitor ? __('Competitor') : __('Recreative') }}
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-4 py-3">
                    <x-icon name="o-map-pin" class="w-4 h-4 opacity-40 shrink-0" />
                    <div class="min-w-0">
                        <div class="text-[10px] opacity-40 uppercase tracking-wider font-black">{{ __('Address') }}</div>
                        <div class="text-sm font-semibold truncate">{{ $user->street }}</div>
                        <div class="text-sm font-semibold truncate">{{ $user->city_name }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-4 py-3">
                    <x-icon name="o-envelope" class="w-4 h-4 opacity-40 shrink-0" />
                    <div class="min-w-0">
                        <div class="text-[10px] opacity-40 uppercase tracking-wider font-black">{{ __('Email') }}</div>
                        <div class="text-sm font-semibold truncate">{{ $user->email }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-4 py-3">
                    <x-icon name="o-phone" class="w-4 h-4 opacity-40 shrink-0" />
                    <div class="min-w-0">
                        <div class="text-[10px] opacity-40 uppercase tracking-wider font-black">{{ __('Phone') }}</div>
                        <div class="text-sm font-semibold truncate">{{ $user->phone_number }}</div>
                    </div>
                </div>
                @if ($user->parent_phone_number)
                    <div class="flex items-center gap-3 px-4 py-3">
                        <x-icon name="o-phone" class="w-4 h-4 opacity-40 shrink-0" />
                        <div class="min-w-0">
                            <div class="text-[10px] opacity-40 uppercase tracking-wider font-black">{{ __('Parent / Tutor') }}</div>
                            <div class="text-sm font-semibold truncate">{{ $user->parent_phone_number }}</div>
                        </div>
                    </div>
                @endif
            </div>
            </x-admin.shared.side-card>

            {{-- Stats --}}
             <x-admin.shared.side-card shadow>
            <div class="divide-y divide-base-200">
           
                @foreach ([
                    ['icon' => 'o-trophy',             'label' => __('Matches'),       'value' => '42',    'sub' => __('This season'),         'color' => ''],
                    ['icon' => 'o-arrow-trending-up',  'label' => __('Win Rate'),      'value' => '65%',   'sub' => __('+5% vs last season'),  'color' => 'text-primary'],
                    ['icon' => 'o-calculator',         'label' => __('Points'),        'value' => '+142',  'sub' => __('Since Sept'),          'color' => 'text-success'],
                    ['icon' => 'o-academic-cap',       'label' => __('Trainings'),     'value' => '28',    'sub' => __('This season'),         'color' => ''],
                    ['icon' => 'o-sparkles',           'label' => __('Best Perf'),     'value' => 'B2',    'sub' => __('All time'),            'color' => ''],
                    ['icon' => 'o-shield-exclamation', 'label' => __('Worst Counter'), 'value' => 'C0',    'sub' => __('This season'),         'color' => ''],
                ] as $stat)
                    <div class="flex items-center justify-between px-4 py-3">
                        <div class="flex items-center gap-2">
                            <x-icon name="{{ $stat['icon'] }}" class="w-4 h-4 opacity-40 shrink-0" />
                            <div>
                                <div class="text-sm font-semibold">{{ $stat['label'] }}</div>
                                <div class="text-[10px] opacity-40">{{ $stat['sub'] }}</div>
                            </div>
                        </div>
                        <span class="font-black text-sm {{ $stat['color'] }}">{{ $stat['value'] }}</span>
                    </div>
                @endforeach
            </div>
             </x-admin.shared.side-card>

        </div>

        {{-- ════════════════════════════════
             CONTENU PRINCIPAL
        ════════════════════════════════ --}}
        <div class="flex-1 min-w-0 space-y-8">

            {{-- Équipes --}}
            <x-card title="{{ __('My Teams') }}" icon="o-user-group" shadow separator>
                <x-tabs wire:model="activeTeamTab">
                    @foreach($user->teams as $team)
                        <x-tab name="team-{{ $team->id }}" label="{{ $team->name }}" icon="o-user-group">

                            <div class="flex items-center justify-between mb-4 pt-2">
                                <div>
                                    <p class="text-base font-bold">Ottignies-Blocry · {{ $team->name }}</p>
                                    <p class="text-xs opacity-50">
                                        {{ $team->league->category ?? 'Division 3' }} · {{ __('Season 2025–2026') }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                @foreach ($team->users as $mate)
                                    @php $isYou = $mate->id === Auth::id(); @endphp
                                    <div @class([
                                        'flex items-center justify-between p-2 rounded-lg border transition-all',
                                        'bg-primary/5 border-primary/20 ring-1 ring-primary/30' => $isYou,
                                        'bg-base-200/40 border-base-200/50 hover:shadow-sm'    => !$isYou,
                                    ])>
                                        <div class="flex items-center gap-3">
                                            <x-avatar class="!w-7 !rounded-full"
                                                :image="$mate->photo ?? '/images/empty-user.jpg'" />
                                            <div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-sm font-semibold leading-none">
                                                        {{ $mate->first_name }} {{ $mate->last_name }}
                                                    </span>
                                                    @if ($isYou)
                                                        <span class="text-[9px] font-black uppercase tracking-widest opacity-40">
                                                            {{ __('(you)') }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <span class="text-[10px] opacity-40 font-black uppercase">
                                                    {{ $mate->ranking }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-4 flex justify-end">
                                <x-button label="{{ __('Team page') }}" icon="o-arrow-right"
                                    class="btn-ghost btn-sm text-xs opacity-50" />
                            </div>

                        </x-tab>
                    @endforeach
                </x-tabs>
            </x-card>

            {{-- Historique --}}
            <x-card title="{{ __('Individual History') }}" icon="o-presentation-chart-line" shadow separator>
                <div class="space-y-6">
                    @php
                        $history = [
                            [
                                'week' => 12, 'match_day' => '30/01',
                                'opponent_team' => 'Mont-Saint-Guibert C', 'global_score' => '12-4',
                                'games' => [
                                    ['player' => 'Dupont A.',   'rank' => 'C0', 'score' => '3-1', 'win' => true],
                                    ['player' => 'Durand L.',   'rank' => 'C2', 'score' => '3-0', 'win' => true],
                                    ['player' => 'Lefebvre G.', 'rank' => 'B6', 'score' => '2-3', 'win' => false],
                                    ['player' => 'Moreau P.',   'rank' => 'C0', 'score' => '3-1', 'win' => true],
                                ],
                            ],
                            [
                                'week' => 11, 'match_day' => '23/01',
                                'opponent_team' => 'Mont-Saint-Guibert C', 'global_score' => '12-4',
                                'games' => [
                                    ['player' => 'Dewit F.',      'rank' => 'B2', 'score' => '3-0', 'win' => false],
                                    ['player' => 'Bourguigon S.', 'rank' => 'C2', 'score' => '1-3', 'win' => true],
                                    ['player' => 'Anciaux T.',    'rank' => 'B6', 'score' => '3-2', 'win' => false],
                                    ['player' => 'Fernandez J.',  'rank' => 'C0', 'score' => '2-3', 'win' => true],
                                ],
                            ],
                            [
                                'week' => 4, 'match_day' => '16/01',
                                'opponent_team' => 'Auderghem J', 'global_score' => '7-9',
                                'games' => [
                                    ['player' => 'Van Pee R.', 'rank' => 'C0', 'score' => '3-1', 'win' => true],
                                    ['player' => 'Renders E.', 'rank' => 'C2', 'score' => '3-0', 'win' => true],
                                    ['player' => 'Godart A.',  'rank' => 'B6', 'score' => '2-3', 'win' => false],
                                ],
                            ],
                        ];
                    @endphp

                    @foreach ($history as $encounter)
                        <div class="relative pl-4 border-l-2 border-base-200">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black opacity-40 uppercase tracking-widest">
                                            {{ __('Week') }} {{ $encounter['week'] }}
                                        </span>
                                        <span class="text-[10px] opacity-30 uppercase tracking-widest">
                                            {{ $encounter['match_day'] }}
                                        </span>
                                    </div>
                                    <h3 class="text-sm font-bold flex items-center gap-2">
                                        {{ $encounter['opponent_team'] }}
                                        <span class="text-[10px] px-1.5 py-0.5 bg-base-200 rounded text-base-content/60 font-mono">
                                            {{ $encounter['global_score'] }}
                                        </span>
                                    </h3>
                                </div>
                                <x-button icon="o-arrow-right"
                                    class="btn-ghost btn-xs opacity-30 hover:opacity-100"
                                    tooltip="{{ __('Match details') }}" />
                            </div>

                            <div class="grid grid-cols-2 gap-1.5">
                                @foreach ($encounter['games'] as $game)
                                    <div class="flex items-center justify-between p-2 rounded-lg bg-base-100 border border-base-200/50 hover:shadow-sm transition-all">
                                        <div class="flex items-center gap-2">
                                            <div @class([
                                                'w-1.5 h-5 rounded-full',
                                                'bg-success/50' => $game['win'],
                                                'bg-error/50'   => !$game['win'],
                                            ])></div>
                                            <div>
                                                <div class="text-xs font-bold leading-none">{{ $game['player'] }}</div>
                                                <div class="text-[9px] opacity-40 font-black uppercase">{{ $game['rank'] }}</div>
                                            </div>
                                        </div>
                                        <div @class([
                                            'font-mono text-xs font-black px-2 py-1 rounded',
                                            'text-success bg-success/5' => $game['win'],
                                            'text-error bg-error/5'     => !$game['win'],
                                        ])>
                                            {{ $game['score'] }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <x-slot:actions>
                    <x-button label="{{ __('See all results') }}" class="btn-ghost btn-sm text-xs opacity-50" />
                </x-slot:actions>
            </x-card>

        </div>
    </div>

    {{-- ════════════════════════════════
         DRAWER — Edit profile
    ════════════════════════════════ --}}
    <x-drawer wire:model="drawer" title="{{ __('Update info') }}" right separator with-close-button
        class="w-full lg:w-1/2 2xl:w-1/2">
        <x-form wire:submit="save">
            <div class="grid grid-cols-6 gap-4 md:gap-6">
                <div class="col-span-6 md:col-span-2">
                    <x-header title="{{ __('Personal') }}" subtitle="{{ __('Personal information') }}" />
                </div>
                <div class="col-span-6 md:col-span-4 grid gap-2">
                    <div class="grid lg:grid-cols-2 gap-6">
                        <x-input label="{{ __('Email') }}" wire:model="email" />
                        <x-input label="{{ __('Street') }}" wire:model="street" />
                        <x-input label="{{ __('Postal Code') }}" wire:model.live.debounce.500ms="city_code"
                            type="number" inputmode="numeric" pattern="[0-9]*"
                            autocomplete="city-code" min="1000" max="9999" />
                        <x-input label="{{ __('City') }}" wire:model="city_name" />
                        <x-input label="{{ __('Phone Number') }}" wire:model="phone_number" />
                        <x-input label="{{ __('Parent or tutor phone number') }}" wire:model="guardian_phone_number" />
                        <div>
                            <div wire:key="photo-container-{{ $imageKey }}">
                                <x-file label="{{ __('Photo') }}" wire:model="photo"
                                    accept="image/png, image/jpeg, image/webp" crop-after-change>
                                    <img src="{{ $photo ? $photo->temporaryUrl() : ($currentPhoto ? asset($currentPhoto) : asset('images/empty-user.jpg')) }}"
                                        alt="{{ __('Avatar') }}" class="h-36 rounded-lg object-cover">
                                </x-file>
                            </div>
                            @if ($currentPhoto)
                                <x-button label="{{ __('Delete photo') }}"
                                    class="m-2 text-xs btn-soft btn-ghost w-36"
                                    wire:click="$set('deleteModal', true)" />
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <x-slot:actions>
                <x-button label="{{ __('Reset') }}" />
                <x-button label="{{ $user ? __('Update') : __('Create') }}" class="btn-primary"
                    type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-drawer>

    {{-- ════════════════════════════════
         MODAL — Delete photo
    ════════════════════════════════ --}}
    <x-modal wire:model="deleteModal" title="{{ __('Confirmation of deletion') }}" subtitle="{{ __('Warning!') }}">
        <x-slot>
            {{ __('Are you sure you want to delete this picture? This action is irreversible.') }}
        </x-slot>
        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" @click="$wire.deleteModal = false" />
            <x-button label="{{ __('Delete') }}" class="btn-error" wire:click="deletePhoto" spinner />
        </x-slot:actions>
    </x-modal>

</div>