<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header title="{{ __('Affiliation and Training') }}" subtitle="{{ __('Manage your season subscription') }}"
        separator>
        @if($registrationsOpen)
        <x-slot:actions>
            <x-button label="{{ __('Add a family member') }}" icon="o-plus" class="btn-outline btn-sm" wire:click="$set('addMemberModal', true)" />
        </x-slot:actions>
        @endif
    </x-header>

    @if(!$registrationsOpen)
    <div class="flex items-start gap-3 p-4 rounded-xl border border-error/30 bg-error/10 mb-6">
        <x-icon name="o-lock-closed" class="w-5 h-5 text-error shrink-0 mt-0.5" />
        <div>
            <div class="font-bold text-sm">{{ __('Registrations are currently closed') }}</div>
            <div class="text-xs opacity-70 mt-0.5">{{ __('The club is not accepting new registrations at this time. Please check back later or contact the club.') }}</div>
        </div>
        <x-badge value="{{ __('Closed') }}" class="badge-error ml-auto shrink-0" />
    </div>
    @else
    <x-admin.shared.info-bar :title="__('Season 2024-2025 Registration')" :description="__('The new season is open! Do not forget to register your family members and all your training sessions here to take advantage of the best price!')" class="mb-6" />
    @endif
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- Colonne de droite : Les onglets --}}
        <div class="lg:col-span-2 space-y-6 lg:order-last">
            <x-tabs wire:model="selectedTab">
                @foreach($registrations as $userId => $reg)
                @php
                    $existingSub = $existingSubscriptions[$userId] ?? null;
                    $subStatus   = $existingSub['status'] ?? null;
                    $isLocked    = in_array($subStatus, ['pending', 'confirmed', 'paid']) || !$registrationsOpen;
                @endphp

                <x-tab name="tab-{{ $userId }}" label="{{ $reg['name'] }}">

                    <div class="space-y-6 mt-4">

                        {{-- Bannière de statut si une subscription existe déjà --}}
                        @if($subStatus === 'pending')
                        <div class="flex items-start gap-3 p-4 rounded-xl border border-warning/30 bg-warning/10">
                            <x-icon name="o-clock" class="w-5 h-5 text-warning shrink-0 mt-0.5" />
                            <div>
                                <div class="font-bold text-sm">{{ __('Registration submitted — awaiting club validation') }}</div>
                                <div class="text-xs opacity-70 mt-0.5">{{ __('The club will review your request shortly. You will be notified once it is validated.') }}</div>
                            </div>
                            <x-badge value="{{ __('Pending') }}" class="badge-warning ml-auto shrink-0" />
                        </div>
                        @elseif($subStatus === 'confirmed')
                        <div class="flex items-start gap-3 p-4 rounded-xl border border-success/30 bg-success/10">
                            <x-icon name="o-check-circle" class="w-5 h-5 text-success shrink-0 mt-0.5" />
                            <div>
                                <div class="font-bold text-sm">{{ __('Your registration has been validated!') }}</div>
                                <div class="text-xs opacity-70 mt-0.5">{{ __('Amount due:') }} <span class="font-bold">{{ $existingSub['amount_due'] }} €</span></div>
                            </div>
                            <x-button label="{{ __('View payment details') }}" icon="o-credit-card" class="btn-success btn-sm ml-auto shrink-0" wire:click="openPaymentModal({{ $userId }})" spinner />
                        </div>

                        @elseif($subStatus === 'paid')
                        <div class="rounded-xl border border-primary/20 bg-primary/5 overflow-hidden">
                            {{-- Header --}}
                            <div class="flex items-center gap-3 p-4 bg-primary/10 border-b border-primary/15">
                                <div class="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center shrink-0">
                                    <x-icon name="o-check-badge" class="w-5 h-5 text-primary" />
                                </div>
                                <div class="flex-1">
                                    <div class="font-black text-sm">{{ __('Affiliation paid — season confirmed!') }}</div>
                                    @if($existingSub['paid_at'])
                                    <div class="text-xs opacity-60">{{ __('Payment received on') }} {{ $existingSub['paid_at'] }}</div>
                                    @endif
                                </div>
                                <x-badge value="{{ __('Paid') }}" class="badge-primary shrink-0" />
                            </div>
                            {{-- Détails --}}
                            <div class="p-4 space-y-3">
                                <div class="flex items-center gap-3">
                                    @if($existingSub['formula'] === 'competitive')
                                    <x-icon name="o-trophy" class="w-5 h-5 text-primary opacity-70 shrink-0" />
                                    <div>
                                        <div class="text-sm font-bold">{{ __('Competition licence') }}</div>
                                        <div class="text-xs opacity-60">{{ __('Official matches, ranking, and advanced coaching.') }}</div>
                                    </div>
                                    <span class="ml-auto font-bold text-sm">125 €</span>
                                    @else
                                    <x-icon name="o-heart" class="w-5 h-5 text-secondary opacity-70 shrink-0" />
                                    <div>
                                        <div class="text-sm font-bold">{{ __('Recreational licence') }}</div>
                                        <div class="text-xs opacity-60">{{ __('Free play and social events.') }}</div>
                                    </div>
                                    <span class="ml-auto font-bold text-sm">60 €</span>
                                    @endif
                                </div>

                                @if(!empty($existingSub['trainings']))
                                <div class="pl-8 space-y-1">
                                    @foreach($existingSub['trainings'] as $tId)
                                    @php $tInfo = collect($trainings)->firstWhere('id', (int) $tId); @endphp
                                    @if($tInfo)
                                    <div class="flex items-center gap-2 text-xs opacity-70">
                                        <x-icon name="o-academic-cap" class="w-3.5 h-3.5 text-primary shrink-0" />
                                        <span>{{ __($tInfo['day']) }} · {{ $tInfo['time'] }} · {{ $tInfo['coach'] }}</span>
                                    </div>
                                    @endif
                                    @endforeach
                                </div>
                                @endif

                                <div class="flex justify-between items-center pt-2 border-t border-primary/10 font-black">
                                    <span class="text-sm">{{ __('Total paid') }}</span>
                                    <span class="text-primary">{{ $existingSub['amount_paid'] }} €</span>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- Wrapper grisé si la demande est déjà soumise --}}
                        <div @class(['space-y-6', 'opacity-50 pointer-events-none select-none' => $isLocked])>

                        {{-- 1. Choix de la formule (Lié spécifiquement à ce membre) --}}
                        <x-card title="1. {{ __('Choose formula for') }} {{ $reg['name'] }}" shadow>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                {{-- Option Compétition --}}
                                <div
                                    wire:click="{{ $isLocked ? '' : "\$set('registrations.{$userId}.formula', 'competitive')" }}"
                                    @class([ 'relative border-2 rounded-xl p-4 transition-all duration-200' , 'cursor-pointer'=> !$isLocked,
                                    'border-primary bg-primary/5 shadow-md' => ($registrations[$userId]['formula'] ?? '') === 'competitive',
                                    'border-base-200 hover:border-primary/50' => ($registrations[$userId]['formula'] ?? '') !== 'competitive' && !$isLocked,
                                    'border-base-200' => ($registrations[$userId]['formula'] ?? '') !== 'competitive' && $isLocked,
                                    ])>
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
                                    wire:click="{{ $isLocked ? '' : "\$set('registrations.{$userId}.formula', 'recreative')" }}"
                                    @class([ 'relative border-2 rounded-xl p-4 transition-all duration-200' , 'cursor-pointer'=> !$isLocked,
                                    'border-secondary bg-secondary/5 shadow-md' => ($registrations[$userId]['formula'] ?? '') === 'recreative',
                                    'border-base-200 hover:border-primary/50' => ($registrations[$userId]['formula'] ?? '') !== 'recreative' && !$isLocked,
                                    'border-base-200' => ($registrations[$userId]['formula'] ?? '') !== 'recreative' && $isLocked,
                                    ])>
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
                                    'hover:bg-base-200/30' => !$training['full'] && !$isLocked
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
                                            :disabled="$isLocked"
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

                        </div>{{-- fin wrapper grisé --}}
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

                    @php
                        $allMembersHaveActiveSub = collect(array_keys($registrations))->every(
                            fn ($uid) => isset($existingSubscriptions[$uid])
                        );
                        $canConfirm = $registrationsOpen && !$allMembersHaveActiveSub;
                    @endphp
                    <x-button
                        label="{{ !$registrationsOpen ? __('Registrations closed') : __('Confirm Subscription') }}"
                        icon="{{ !$registrationsOpen ? 'o-lock-closed' : 'o-credit-card' }}"
                        class="btn-primary btn-block shadow-lg"
                        wire:click="confirmSubscription"
                        :disabled="!$canConfirm"
                        spinner />
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



    {{-- Modal Détails de Paiement --}}
    <x-modal wire:model="paymentModal" title="{{ __('Payment Details') }}" box-class="max-w-md">
        @if(!empty($paymentDetails))
        <div class="space-y-6">

            {{-- QR Code --}}
            <div class="flex flex-col items-center gap-3">
                <img src="{{ $paymentDetails['qr_code'] }}" alt="QR Code" class="w-48 h-48 rounded-xl border border-base-200 shadow" />
                <p class="text-xs opacity-50 text-center">{{ __('Scan this QR code with your banking app') }}</p>
            </div>

            <x-menu-separator />

            {{-- Détails bancaires --}}
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="opacity-60">{{ __('Beneficiary') }}</span>
                    <span class="font-semibold">{{ $paymentDetails['beneficiary'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="opacity-60">{{ __('IBAN') }}</span>
                    <span class="font-mono font-semibold tracking-wide">{{ $paymentDetails['iban'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="opacity-60">{{ __('BIC') }}</span>
                    <span class="font-mono font-semibold">{{ $paymentDetails['bic'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="opacity-60">{{ __('Structured reference') }}</span>
                    <span class="font-mono font-bold text-primary">{{ $paymentDetails['reference'] }}</span>
                </div>
                <div class="flex justify-between items-center pt-1 border-t border-base-200">
                    <span class="font-bold">{{ __('Amount') }}</span>
                    <span class="text-lg font-black text-primary">{{ $paymentDetails['amount_due'] }} €</span>
                </div>
            </div>

            {{-- Avertissement référence --}}
            <div class="flex gap-2 p-3 rounded-lg bg-warning/10 border border-warning/20 text-xs">
                <x-icon name="o-exclamation-triangle" class="w-4 h-4 text-warning shrink-0 mt-0.5" />
                <span class="opacity-80">{{ __('Always include the structured reference when making your transfer so your payment is automatically matched.') }}</span>
            </div>
        </div>
        @endif

        <x-slot:actions>
            <x-button label="{{ __('Close') }}" @click="$wire.paymentModal = false" class="btn-ghost" />
        </x-slot:actions>
    </x-modal>

    {{-- Modal : Ajouter un membre de la famille --}}
    <x-modal wire:model="addMemberModal" title="{{ __('Add a family member') }}" box-class="max-w-md">

        {{-- Sélecteur de mode --}}
        <div class="flex rounded-xl bg-base-200 p-1 gap-1 mb-5">
            <button
                wire:click="$set('memberModalMode', 'search')"
                @class(['flex-1 rounded-lg py-1.5 text-sm font-semibold transition-all', 'bg-base-100 shadow' => $memberModalMode === 'search', 'opacity-50' => $memberModalMode !== 'search'])>
                {{ __('Find existing') }}
            </button>
            <button
                wire:click="$set('memberModalMode', 'create')"
                @class(['flex-1 rounded-lg py-1.5 text-sm font-semibold transition-all', 'bg-base-100 shadow' => $memberModalMode === 'create', 'opacity-50' => $memberModalMode !== 'create'])>
                {{ __('Create new') }}
            </button>
        </div>

        {{-- Mode recherche --}}
        @if($memberModalMode === 'search')
        <div class="space-y-3">
            <x-input
                placeholder="{{ __('Search by name or email…') }}"
                wire:model.live.debounce.250ms="memberSearchQuery"
                icon="o-magnifying-glass"
                autofocus />

            @if(strlen($memberSearchQuery) >= 2)
                @forelse($memberSearchResults as $result)
                <div class="flex items-center gap-3 p-3 rounded-xl border border-base-200 hover:border-primary/40 hover:bg-base-200/40 transition-colors cursor-pointer"
                    wire:click="addExistingMember({{ $result->id }})">
                    <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center shrink-0 font-bold text-primary text-sm">
                        {{ strtoupper(substr($result->first_name, 0, 1)) }}{{ strtoupper(substr($result->last_name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm">{{ $result->first_name }} {{ $result->last_name }}</div>
                        <div class="text-xs opacity-50 truncate">{{ $result->email }}</div>
                    </div>
                    <x-icon name="o-plus-circle" class="w-5 h-5 text-primary shrink-0" />
                </div>
                @empty
                <div class="flex flex-col items-center gap-2 py-6 opacity-40">
                    <x-icon name="o-user-plus" class="w-8 h-8" />
                    <p class="text-sm italic">{{ __('No member found.') }}</p>
                </div>
                <div class="text-center">
                    <x-button label="{{ __('Create a new profile instead') }}" icon="o-plus" class="btn-ghost btn-sm" wire:click="$set('memberModalMode', 'create')" />
                </div>
                @endforelse
            @else
            <p class="text-xs opacity-40 text-center py-4">{{ __('Type at least 2 characters to search.') }}</p>
            @endif
        </div>
        @endif

        {{-- Mode création --}}
        @if($memberModalMode === 'create')
        <div class="space-y-4">
            <x-input label="{{ __('First Name') }}" wire:model="new_first_name" />
            <x-input label="{{ __('Last Name') }}" wire:model="new_last_name" />
            <x-datetime label="{{ __('Birthdate') }}" wire:model="new_birthdate" />
            <x-group label="{{ __('Gender') }}" wire:model="new_gender" :options="$genders" inline class="btn-soft" />
            <x-input label="{{ __('Email') }}" wire:model="new_email" />
            <x-input label="{{ __('Phone Number') }}" wire:model="new_phone_number" />
        </div>
        @endif

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" @click="$wire.addMemberModal = false; $wire.memberModalMode = 'search'; $wire.memberSearchQuery = ''" class="btn-ghost" />
            @if($memberModalMode === 'create')
            <x-button label="{{ __('Create and add') }}" wire:click="createFamilyMember" class="btn-primary" spinner />
            @endif
        </x-slot:actions>
    </x-modal>
</div>