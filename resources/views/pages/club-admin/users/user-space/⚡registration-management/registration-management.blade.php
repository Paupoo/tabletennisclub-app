<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header title="{{ __('Affiliation and Training') }}" subtitle="{{ __('Manage your season subscription') }}"
        separator>
        <x-slot:actions>
            <x-button label="{{ __('Add a family member') }}" icon="o-plus" class="btn-primary btn-sm   " wire:click="$set('addMemberModal', true)" />
        </x-slot:actions>
    </x-header>

    <x-admin.shared.info-bar :title="__('Season 2024-2025 Registration')" :description="__('The new season is open! Do not forget to register your family members and all your training sessions here to take advantage of the best price!')" class="mb-6">

    </x-admin.shared.info-bar>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- Colonne de droite : Les onglets --}}
        <div class="lg:col-span-2 space-y-6 lg:order-last">
            <x-tabs wire:model="selectedTab">
                @foreach($registrations as $userId => $reg)
                <x-tab name="tab-{{ $userId }}" label="{{ $reg['name'] }}">

                    <div class="space-y-6 mt-4">
                        {{-- 1. Choix de la formule (Lié spécifiquement à ce membre) --}}
                        <x-card title="1. {{ __('Choose formula for') }} {{ $reg['name'] }}" shadow>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                {{-- Option Compétition --}}
                                <div
                                    wire:click="$set('registrations.{{ $userId }}.formula', 'competitive')"
                                    @class([ 'relative border-2 rounded-xl p-4 cursor-pointer transition-all duration-200' , 'border-primary bg-primary/5 shadow-md'=> ($registrations[$userId]['formula'] ?? '') === 'competitive',
                                    'border-base-200 hover:border-primary/50' => ($registrations[$userId]['formula'] ?? '') !== 'competitive'
                                    ])
                                    >
                                    <div class="flex justify-between items-start">
                                        <x-icon name="o-trophy" @class(['w-10 h-10', 'text-primary'=> ($registrations[$userId]['formula'] ?? '') === 'competitive', 'opacity-50' => ($registrations[$userId]['formula'] ?? '') !== 'competitive']) />
                                            @if(($registrations[$userId]['formula'] ?? '') === 'competitive')
                                            <x-badge value="Selected" class="badge-primary" />
                                            @else
                                            <x-badge value="Recommended" class="badge-ghost opacity-50" />
                                            @endif
                                    </div>
                                    <div class="mt-4 font-black text-lg">{{ __('Competition') }}</div>
                                    <div class="text-sm opacity-70">{{ __('Official matches, ranking, and advanced coaching.') }}</div>
                                    <div class="mt-4 text-xl font-bold">125&nbsp;€ <span class="text-xs font-normal">/ season</span></div>
                                </div>

                                {{-- Option Récréatif --}}
                                <div
                                    wire:click="$set('registrations.{{ $userId }}.formula', 'recreative')"
                                    @class([ 'relative border-2 rounded-xl p-4 cursor-pointer transition-all duration-200' , 'border-secondary bg-secondary/5 shadow-md'=> ($registrations[$userId]['formula'] ?? '') === 'recreative',
                                    'border-base-200 hover:border-primary/50' => ($registrations[$userId]['formula'] ?? '') !== 'recreative'
                                    ])
                                    >
                                    <div class="flex justify-between items-start">
                                        <x-icon name="o-heart" @class(['w-10 h-10', 'text-secondary'=> ($registrations[$userId]['formula'] ?? '') === 'recreative', 'opacity-50' => ($registrations[$userId]['formula'] ?? '') !== 'recreative']) />
                                            @if(($registrations[$userId]['formula'] ?? '') === 'recreative')
                                            <x-badge value="Selected" class="badge-secondary" />
                                            @endif
                                    </div>
                                    <div class="mt-4 font-black text-lg">{{ __('Recreational') }}</div>
                                    <div class="text-sm opacity-70">{{ __('Free play and social events. No official matches.') }}</div>
                                    <div class="mt-4 text-xl font-bold">60&nbsp;€ <span class="text-xs font-normal">/ season</span></div>
                                </div>

                            </div>
                        </x-card>

                        {{-- 2. Réservation des entraînements (Lié spécifiquement à ce membre) --}}
                        <x-card title="2. {{ __('Available Trainings') }}" shadow>
                            <div class="space-y-2">


                                @foreach ($trainings as $training)
                                <div @class([ 'flex items-center justify-between p-3 border-b border-base-200 last:border-0 transition-colors' , 'opacity-50 bg-base-200/50'=> $training['full'],
                                    'hover:bg-base-200/30' => !$training['full']
                                    ])>
                                    <div class="flex items-center gap-6">
                                        {{-- Colonne Date/Heure --}}
                                        <div class="min-w-[100px]">
                                            <div class="text-xs font-bold uppercase opacity-40">{{ __($training['day']) }}</div>
                                            <div class="text-sm font-semibold">{{ $training['time'] }}</div>
                                        </div>

                                        {{-- Colonne Infos Coach & Niveau --}}
                                        <div class="flex flex-col">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-sm">{{ $training['coach'] }}</span>
                                                <span class="text-xs opacity-40">•</span>
                                                <div class="flex items-center gap-1.5">
                                                    <div class="w-1.5 h-1.5 rounded-full {{ $training['dot_color'] }}"></div>
                                                    <span class="text-[11px] font-medium opacity-60 uppercase tracking-wider italic">
                                                        {{ $training['level'] }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="text-[10px] mt-0.5">
                                                @if ($training['full'])
                                                <span class="text-error uppercase font-bold tracking-tighter">{{ __('Session Full') }}</span>
                                                @else
                                                <span class="opacity-50 italic">{{ $training['spots'] }} {{ __('spots left') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Colonne Action (Checkbox) --}}
                                    <div class="flex items-center ml-4">
                                        @if (!$training['full'])
                                        <x-checkbox
                                            id="user-{{ $userId }}-t-{{ $training['id'] }}"
                                            wire:model.live="registrations.{{ $userId }}.trainings"
                                            value="{{ $training['id'] }}"
                                            class="checkbox-primary checkbox-sm" />
                                        @else
                                        <div class="tooltip tooltip-left" data-tip="{{ __('Full') }}">
                                            <x-icon name="o-lock-closed" class="w-4 h-4 opacity-20" />
                                        </div>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </x-card>
                    </div>

                </x-tab>
                @endforeach
            </x-tabs>
        </div>

        {{-- Colonne de gauche : Le résumé global --}}
        <div class="space-y-6 mt-16">
            <x-card title="{{ __('Global Summary') }}" shadow class="bg-primary/5 border border-primary/10">
                <div class="space-y-4">

                    {{-- 1. Détail par Membre (Licences uniquement) --}}
                    @foreach($registrations as $userId => $reg)
                    <div class="border-b border-base-200 pb-3 last:border-b-0">
                        <div class="font-bold text-sm flex justify-between">
                            <span>{{ $reg['name'] }}</span>
                            <span class="text-base-content/70">
                                {{ ($reg['formula'] ?? 'recreative') === 'competitive' ? '125 €' : '60 €' }}
                            </span>
                        </div>
                        <div class="text-[10px] uppercase tracking-widest opacity-50">
                            {{ __($reg['formula'] ?? 'recreative') }}
                        </div>

                        {{-- On liste les entraînements sous le nom, mais sans prix individuel (car prix groupé) --}}
                        @if(!empty($reg['trainings']))
                        <div class="mt-2 space-y-1">
                            @foreach($reg['trainings'] as $tId)
                            @php $tInfo = collect($trainings)->firstWhere('id', $tId); @endphp
                            @if($tInfo)
                            <div class="flex items-center gap-2 text-[11px] opacity-60 italic">
                                <x-icon name="o-check-circle" class="w-3 h-3 text-success" />
                                <span>{{ __($tInfo['day']) }} ({{ $tInfo['time'] }})</span>
                            </div>
                            @endif
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endforeach

                    {{-- 2. Ligne dédiée aux Entraînements (Logique Alpine/PHP) --}}
                    @if($this->stats['training'] > 0)
                    <div class="flex justify-between items-center p-2 bg-base-100 rounded-lg border border-base-200 shadow-sm">
                        <div class="flex flex-col">
                            <span class="text-xs font-bold">{{ __('Training Sessions') }}</span>
                            <span class="text-[10px] opacity-60 italic">
                                {{ $this->stats['countSessions'] }} {{ __('sessions total') }}
                                @if($this->stats['countMembers'] > 1)
                                • {{ __('Family discount applied') }}
                                @endif
                            </span>
                        </div>
                        <span class="font-bold text-sm">{{ $this->stats['training'] }} €</span>
                    </div>
                    @endif

                    <x-menu-separator />

                    {{-- 3. Total Final --}}
                    <div class="flex justify-between text-lg font-black">
                        <span>{{ __('Total') }}</span>
                        <div class="flex items-center gap-2">
                            <x-loading wire:loading wire:target="registrations" class="loading-xs text-primary" />
                            <span class="text-primary" wire:loading.class="opacity-50">
                                {{ $this->stats['total'] }} €
                            </span>
                        </div>
                    </div>

                    <x-button label="{{ __('Confirm and Pay') }}" icon="o-credit-card" class="btn-primary btn-block shadow-lg" />
                </div>
            </x-card>

            {{-- Petit rappel rassurance --}}
            <div class="bg-base-200/50 p-4 rounded-xl text-xs flex gap-3">
                <x-icon name="o-information-circle" class="w-5 h-5 text-info shrink-0" />
                <span class="opacity-70">
                    {{ __('The total includes the club affiliation and the technical training sessions for the entire family.') }}
                </span>
            </div>

            {{-- Rappel des documents --}}
            <x-card title="{{ __('Required Documents') }}" shadow>
                <x-list-item :item="[]" no-separator no-hover>
                    <x-slot:avatar>
                        <x-icon name="o-document-check" class="text-success w-6 h-6" />
                    </x-slot:avatar>
                    <x-slot:value>Medical Certificate</x-slot:value>
                    <x-slot:sub-value>Already uploaded</x-slot:sub-value>
                </x-list-item>
                <x-list-item :item="[]" no-separator no-hover>
                    <x-slot:avatar>
                        <x-icon name="o-exclamation-triangle" class="text-warning w-6 h-6" />
                    </x-slot:avatar>
                    <x-slot:value>Parental Consent</x-slot:value>
                    <x-slot:sub-value>Missing for minors</x-slot:sub-value>
                </x-list-item>
            </x-card>
        </div>

    </div>



    {{-- Modal de Création Rapide --}}
    <x-modal wire:model="addMemberModal" title="{{ __('Add a family member') }}">
        <div class="space-y-4">
            <x-input label="{{ __('First Name') }}" wire:model="new_first_name" />
            <x-input label="{{ __('Last Name') }}" wire:model="new_last_name" />
            <x-datetime label="{{ __('Birthdate') }}" wire:model="new_birthdate" />
            <x-group label="{{ __('Gender') }}" wire:model="new_gender" :options="[['id' => 'male', 'name' => __('Male')], ['id' => 'female', 'name' => __('Female')]]" inline class="btn-soft" />
            <x-input label="{{ __('Email') }}" wire:model="new_email" />
            <x-input label="{{ __('Phone Number') }}" wire:model="new_phone_number" />
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" @click="$wire.addMemberModal = false" class="btn-ghost" />
            <x-button label="{{ __('Create and add') }}" wire:click="createFamilyMember" class="btn-primary" spinner />
        </x-slot:actions>
    </x-modal>
</div>