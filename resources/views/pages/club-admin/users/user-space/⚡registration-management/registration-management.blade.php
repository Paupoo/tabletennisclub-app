<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header :title="__('My season')" :subtitle="__('Manage your club membership and training enrollments')" separator />

    @php $isSingleMember = count($registrations) === 1; @endphp

    <div x-data="{ selected: @entangle('selectedTab') }">
        @unless($isSingleMember)
            {{-- Family member selector --}}
            <div class="flex items-center gap-2 mb-3">
                <div class="flex items-center gap-1.5">
                    <x-icon name="o-users" class="w-4 h-4 text-base-content/40" />
                    <span class="text-xs font-bold uppercase tracking-wide text-base-content/50">{{ __('Your family') }}</span>
                </div>
                <span class="text-xs opacity-50 italic shrink-0">— {{ __('Select the person to manage') }}</span>
            </div>
            <div class="grid grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] gap-2.5 mb-6">
                @foreach($registrations as $userId => $reg)
                    @php
                        $memberEntry = collect(($subscriptionHistory[$userId] ?? ['history' => []])['history'])
                            ->firstWhere('is_current_season', true);
                        $memberStatus = ($memberEntry && $memberEntry['status'] !== 'cancelled') ? $memberEntry['status'] : null;
                        $memberInitials = collect(explode(' ', trim($reg['name'])))
                            ->filter()
                            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                            ->take(2)
                            ->implode('');
                    @endphp
                    <button type="button"
                        @click="selected = 'tab-{{ $userId }}'"
                        :class="selected === 'tab-{{ $userId }}' ? 'border-primary bg-primary/5 shadow-sm' : 'border-base-300 hover:border-primary/50'"
                        :aria-selected="selected === 'tab-{{ $userId }}'"
                        role="tab"
                        class="flex items-center gap-3 rounded-xl border-2 p-3 text-left transition-colors cursor-pointer">
                        <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center text-sm font-bold shrink-0">
                            {{ $memberInitials }}
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold text-sm truncate">{{ $reg['name'] }}</div>
                            @if($memberStatus === 'paid')
                                <div class="text-xs text-success font-medium">✓ {{ __('Paid') }}</div>
                            @elseif($memberStatus === 'confirmed')
                                <div class="text-xs text-info font-medium">{{ __('To pay') }}</div>
                            @elseif($memberStatus === 'pending')
                                <div class="text-xs text-warning-content font-medium">{{ __('Pending') }}</div>
                            @else
                                <div class="text-xs text-error font-medium">{{ __('To register') }}</div>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        @endunless

        @foreach($registrations as $userId => $reg)
        @php
            $existingSub      = $existingSubscriptions[$userId] ?? null;
            $hasActiveSub     = $existingSub !== null;
            $userHistoryData  = $subscriptionHistory[$userId] ?? ['history' => [], 'can_reaffiliate' => false];
            $userHistory      = $userHistoryData['history'];
            $canReAffiliate   = $userHistoryData['can_reaffiliate'];
            $currentEntry     = collect($userHistory)->firstWhere('is_current_season', true);
            $pastEntries      = collect($userHistory)->where('is_current_season', false)->values();
            $selectedPacks    = $pendingPackIds[$userId] ?? [];
            $isRegistering    = (! $currentEntry || ($currentEntry['status'] ?? null) === 'cancelled') && ($affiliationsOpen || $canReAffiliate);
        @endphp

        <div x-show="selected === 'tab-{{ $userId }}'" role="tabpanel">
            <div class="space-y-10 mt-4">

                {{-- ── A. AFFILIATION ────────────────────────────────────────────── --}}
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="flex items-center gap-1.5">
                            <x-icon name="o-identification" class="w-4 h-4 text-base-content/40" />
                            <span class="text-xs font-bold uppercase tracking-wide text-base-content/50">{{ __('Club Membership') }}</span>
                        </div>
                        <div class="flex-1 border-t border-base-200"></div>
                    </div>

                    {{-- Current season accordion – open by default --}}
                    <x-section-accordion label="{{ $currentSeasonName }}" color="gray" :uppercase="false" class="mb-3">
                        <x-slot:suffix>
                            @if($currentEntry && $currentEntry['status'] !== 'cancelled')
                                @if($currentEntry['status'] === 'paid')
                                    <x-badge value="{{ __('Paid') }}" class="badge-success badge-sm" />
                                @elseif($currentEntry['status'] === 'confirmed')
                                    <x-badge value="{{ __('Confirmed') }}" class="badge-info badge-sm" />
                                @elseif($currentEntry['status'] === 'pending')
                                    <x-badge value="{{ __('Pending') }}" class="badge-warning badge-sm" />
                                @endif
                            @elseif($affiliationsOpen)
                                <x-badge value="{{ __('Open') }}" class="badge-ghost badge-sm" />
                            @else
                                <x-badge value="{{ __('Closed') }}" class="badge-error badge-sm" />
                            @endif
                        </x-slot:suffix>
                            @if($currentEntry && $currentEntry['status'] !== 'cancelled')
                                @if($currentEntry['status'] === 'pending')
                                    <div class="flex items-start gap-3 p-4 rounded-xl border border-warning/30 bg-warning/10">
                                        <x-icon name="o-clock" class="w-5 h-5 text-warning-content shrink-0 mt-0.5" />
                                        <div class="flex-1">
                                            <div class="font-bold text-sm">{{ __('Affiliation submitted — awaiting club validation') }}</div>
                                            <div class="text-xs opacity-70 mt-0.5">{{ __('You will be notified once it is validated.') }}</div>
                                            {{-- La formule demandée reste visible : c'est le choix du membre, et il décide de ce qui lui sera facturé. --}}
                                            <div class="flex items-center gap-1.5 text-xs font-semibold mt-2">
                                                <x-icon name="{{ $currentEntry['is_competitive'] ? 'o-trophy' : 'o-heart' }}" class="w-3.5 h-3.5 shrink-0 opacity-70" />
                                                <span>{{ $currentEntry['is_competitive'] ? __('Competition licence') : __('Recreational licence') }}</span>
                                            </div>
                                            @if(!empty($currentEntry['enrolled_packs']))
                                                <div class="mt-2 space-y-1">
                                                    @foreach($currentEntry['enrolled_packs'] as $packInfo)
                                                        <div class="flex items-center gap-1.5 text-xs opacity-60 flex-wrap">
                                                            <x-icon name="o-academic-cap" class="w-3 h-3 shrink-0" />
                                                            <span>{{ $packInfo['name'] }}</span>
                                                            @if(!empty($packInfo['schedule']))
                                                                <span>·</span>
                                                                <span>{{ $packInfo['schedule'] }}</span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <x-button
                                            :label="__('Cancel')"
                                            icon="o-x-mark"
                                            class="btn-ghost btn-sm text-error shrink-0"
                                            wire:click="confirmCancelAffiliation({{ $userId }})"
                                            spinner />
                                    </div>
                                @elseif($currentEntry['status'] === 'confirmed')
                                    <div class="rounded-xl border border-info/30 bg-info/10 overflow-hidden">
                                        <div class="flex items-center gap-3 p-4">
                                            <x-icon name="o-check-circle" class="w-5 h-5 text-info shrink-0" />
                                            <div class="flex-1">
                                                <div class="font-bold text-sm">{{ __('Your affiliation has been validated!') }}</div>
                                                <div class="text-xs opacity-70 mt-0.5">{{ __('Please complete your payment(s) below.') }}</div>
                                                <div class="flex items-center gap-1.5 text-xs font-semibold mt-2">
                                                    <x-icon name="{{ $currentEntry['is_competitive'] ? 'o-trophy' : 'o-heart' }}" class="w-3.5 h-3.5 shrink-0 opacity-70" />
                                                    <span>{{ $currentEntry['is_competitive'] ? __('Competition licence') : __('Recreational licence') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        @if(!empty($currentEntry['pending_payments']))
                                            <div class="border-t border-info/20 divide-y divide-info/10">
                                                @foreach($currentEntry['pending_payments'] as $payment)
                                                    <div class="flex items-center gap-3 px-4 py-3">
                                                        <x-icon name="o-credit-card" class="w-4 h-4 text-info opacity-60 shrink-0" />
                                                        <div class="flex-1 min-w-0">
                                                            <div class="font-mono text-xs opacity-60">{{ $payment['reference'] }}</div>
                                                            <div class="font-bold text-base text-info">{{ number_format($payment['amount_due'], 2) }} €</div>
                                                        </div>
                                                        <x-button
                                                            :label="__('Pay')"
                                                            icon="o-qr-code"
                                                            class="btn-info btn-sm shrink-0"
                                                            wire:click="openPaymentModal({{ $userId }}, {{ $payment['id'] }})"
                                                            spinner />
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @elseif($currentEntry['status'] === 'paid')
                                    <div class="rounded-xl border border-success/20 bg-success/5 overflow-hidden">
                                        <div class="flex items-center gap-3 p-4 bg-success/10 border-b border-success/15">
                                            <div class="w-8 h-8 rounded-full bg-success/20 flex items-center justify-center shrink-0">
                                                <x-icon name="o-check-badge" class="w-5 h-5 text-success" />
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-bold text-sm">{{ __('Affiliation paid — season confirmed!') }}</div>
                                            </div>
                                            <x-badge value="{{ __('Paid') }}" class="badge-success shrink-0" />
                                        </div>
                                        <div class="p-4 space-y-3">
                                            <div class="flex items-center gap-3">
                                                @if($currentEntry['is_competitive'])
                                                    <x-icon name="o-trophy" class="w-5 h-5 text-success opacity-70 shrink-0" />
                                                    <span class="text-sm font-bold">{{ __('Competition licence') }}</span>
                                                @else
                                                    <x-icon name="o-heart" class="w-5 h-5 text-secondary opacity-70 shrink-0" />
                                                    <span class="text-sm font-bold">{{ __('Recreational licence') }}</span>
                                                @endif
                                                <span class="ml-auto font-bold text-sm">{{ $currentEntry['amount_due'] }} €</span>
                                            </div>
                                            @foreach($currentEntry['enrolled_packs'] as $packInfo)
                                                <div class="flex items-center gap-2 text-xs opacity-60 pl-8 flex-wrap">
                                                    <x-icon name="o-academic-cap" class="w-3.5 h-3.5 text-success shrink-0" />
                                                    <span>{{ $packInfo['name'] }}</span>
                                                    @if(!empty($packInfo['schedule']))
                                                        <span>·</span>
                                                        <span>{{ $packInfo['schedule'] }}</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        {{-- Supplementary payments from mid-season training additions --}}
                                        @if(!empty($currentEntry['pending_payments']))
                                            <div class="border-t border-success/15 divide-y divide-success/10">
                                                <div class="px-4 pt-3 pb-1 text-xs font-bold uppercase tracking-wide opacity-60">{{ __('Additional payments') }}</div>
                                                @foreach($currentEntry['pending_payments'] as $payment)
                                                    <div class="flex items-center gap-3 px-4 py-3">
                                                        <x-icon name="o-credit-card" class="w-4 h-4 text-warning-content opacity-60 shrink-0" />
                                                        <div class="flex-1 min-w-0">
                                                            <div class="font-mono text-xs opacity-60">{{ $payment['reference'] }}</div>
                                                            <div class="font-bold text-base text-warning-content">{{ number_format($payment['amount_due'], 2) }} €</div>
                                                        </div>
                                                        <x-button
                                                            :label="__('Pay')"
                                                            icon="o-qr-code"
                                                            class="btn-warning btn-sm shrink-0"
                                                            wire:click="openPaymentModal({{ $userId }}, {{ $payment['id'] }})"
                                                            spinner />
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            @else
                                {{-- No active subscription for current season (or was cancelled) --}}
                                @if($affiliationsOpen || $canReAffiliate)
                                    @php $formula = $reg['formula'] ?? 'recreative'; @endphp

                                    @if($canReAffiliate)
                                        <div class="flex items-center gap-2 p-3 rounded-lg border border-warning/30 bg-warning/10 text-sm mb-4">
                                            <x-icon name="o-arrow-path" class="w-4 h-4 text-warning-content shrink-0" />
                                            <span class="opacity-70">{{ __('Your previous affiliation was rejected. You can submit a new one below.') }}</span>
                                        </div>
                                    @endif

                                    {{-- How it works: licence vs training --}}
                                    <div class="flex items-start gap-3 p-4 rounded-xl border border-info/30 bg-info/10 mb-5">
                                        <x-icon name="o-information-circle" class="w-5 h-5 text-info shrink-0 mt-0.5" />
                                        <div class="text-sm">
                                            <span class="font-bold">{{ __('How does it work?') }}</span>
                                            <span class="opacity-80">{{ __('The licence is required to play at the club. Directed training sessions are optional and billed in addition, whichever licence you choose.') }}</span>
                                        </div>
                                    </div>

                                    <div class="space-y-5">
                                        {{-- Section: your licence --}}
                                        <div class="flex items-center gap-2">
                                            <div class="flex items-center gap-1.5">
                                                <x-icon name="o-identification" class="w-4 h-4 text-base-content/40" />
                                                <span class="text-xs font-bold uppercase tracking-wide text-base-content/50">{{ __('Your licence') }}</span>
                                            </div>
                                            <span class="text-xs opacity-50 italic shrink-0">{{ __('Required') }}</span>
                                            <div class="flex-1 border-t border-base-200"></div>
                                        </div>

                                        {{-- Formula selection --}}
                                        {{-- Un choix exclusif et obligatoire : un groupe de boutons radio.
                                             Les cartes sont les libellés, la case elle-même reste lisible par
                                             un lecteur d'écran et atteignable au clavier sans être visible. --}}
                                        <fieldset class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <legend class="sr-only">{{ __('Your licence') }}</legend>

                                            <label @class(['relative block border-2 rounded-xl p-4 transition-all duration-200 cursor-pointer',
                                                'has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary has-[:focus-visible]:ring-offset-2',
                                                'border-primary bg-primary/5 shadow-md' => $formula === 'competitive',
                                                'border-base-200 hover:border-primary/50' => $formula !== 'competitive',
                                            ])>
                                                <input type="radio" class="sr-only"
                                                    name="licence-formula-{{ $userId }}"
                                                    value="competitive"
                                                    wire:model.live="registrations.{{ $userId }}.formula" />
                                                <div class="flex justify-between items-start">
                                                    <x-icon name="o-trophy" @class(['w-10 h-10',
                                                        'text-primary' => $formula === 'competitive',
                                                        'opacity-50'   => $formula !== 'competitive',
                                                    ]) />
                                                    @if($formula === 'competitive')
                                                        <x-badge value="{{ __('Selected') }}" class="badge-primary" />
                                                    @endif
                                                </div>
                                                <div class="mt-4 font-bold text-lg">{{ __('Competition') }}</div>
                                                <div class="text-sm opacity-70">{{ __('Official interclub matches and AFTT ranking.') }}</div>
                                                <div class="mt-4 text-xl font-bold">125&nbsp;€ <span class="text-xs font-normal">/ season</span></div>
                                            </label>

                                            <label @class(['relative block border-2 rounded-xl p-4 transition-all duration-200 cursor-pointer',
                                                'has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-secondary has-[:focus-visible]:ring-offset-2',
                                                'border-secondary bg-secondary/5 shadow-md' => $formula === 'recreative',
                                                'border-base-200 hover:border-secondary/50' => $formula !== 'recreative',
                                            ])>
                                                <input type="radio" class="sr-only"
                                                    name="licence-formula-{{ $userId }}"
                                                    value="recreative"
                                                    wire:model.live="registrations.{{ $userId }}.formula" />
                                                <div class="flex justify-between items-start">
                                                    <x-icon name="o-heart" @class(['w-10 h-10',
                                                        'text-secondary' => $formula === 'recreative',
                                                        'opacity-50'     => $formula !== 'recreative',
                                                    ]) />
                                                    @if($formula === 'recreative')
                                                        <x-badge value="{{ __('Selected') }}" class="badge-secondary" />
                                                    @endif
                                                </div>
                                                <div class="mt-4 font-bold text-lg">{{ __('Recreational') }}</div>
                                                <div class="text-sm opacity-70">{{ __('Free play and social events. No official matches.') }}</div>
                                                <div class="mt-4 text-xl font-bold">60&nbsp;€ <span class="text-xs font-normal">/ season</span></div>
                                            </label>
                                        </fieldset>


                                    </div>
                                @else
                                    <div class="flex items-start gap-3 p-4 rounded-xl border border-error/30 bg-error/10">
                                        <x-icon name="o-lock-closed" class="w-5 h-5 text-error shrink-0 mt-0.5" />
                                        <div>
                                            <div class="font-bold text-sm">{{ __('Affiliations are currently closed') }}</div>
                                            <div class="text-xs opacity-70 mt-0.5">{{ __('The club is not accepting new affiliations at this time. Please check back later.') }}</div>
                                        </div>
                                    </div>
                                @endif
                            @endif

                            {{-- ── ENTRAÎNEMENTS ──────────────────────────────────── --}}
                                <div class="mt-6 pt-6 border-t border-base-200">
                                    <div class="flex items-center gap-2 mb-4">
                                        <div class="flex items-center gap-1.5">
                                            <x-icon name="o-academic-cap" class="w-4 h-4 text-base-content/40" />
                                            <span class="text-xs font-bold uppercase tracking-wide text-base-content/50">{{ __('Directed training') }}</span>
                                        </div>
                                        @if(!$hasActiveSub && ($affiliationsOpen || $canReAffiliate))
                                            <span class="text-xs opacity-50 italic shrink-0">{{ __('Optional, billed in addition — tick to include in your affiliation') }}</span>
                                        @else
                                            <span class="text-xs opacity-50 italic shrink-0">{{ __('Optional, billed in addition to your licence') }}</span>
                                        @endif
                                        <div class="flex-1 border-t border-base-200"></div>
                                    </div>

                                    @if(empty($availablePacks))
                                        <div class="flex flex-col items-center gap-2 py-8 opacity-40">
                                            <x-icon name="o-academic-cap" class="w-10 h-10" />
                                            <p class="text-sm italic">{{ __('No training packs available for this season.') }}</p>
                                        </div>
                                    @else
                                        <div class="space-y-3">
                                            @foreach($availablePacks as $pack)
                                            @php
                                                $enrollment       = $pack['enrollments'][$userId] ?? ['status' => null];
                                                $enrollStatus     = $enrollment['status'] ?? null;
                                                $isOwnPack        = $pack['trainer_id'] === $userId;
                                                $isSelectedForReg = !$hasActiveSub && in_array($pack['id'], $selectedPacks);
                                            @endphp
                                            <div x-data="{ descOpen: false }">
                                                <div @class(['flex items-center gap-4 p-4 rounded-xl border bg-base-100 transition-colors',
                                                    'border-primary/40' => $enrollStatus === 'enrolled' || $isSelectedForReg,
                                                    'border-base-300'   => !($enrollStatus === 'enrolled' || $isSelectedForReg),
                                                    'opacity-50'        => $isOwnPack,
                                                ])>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2 flex-wrap">
                                                            <div class="w-2 h-2 rounded-full {{ $pack['dot_color'] }} shrink-0"></div>
                                                            <span class="font-bold text-sm">{{ $pack['name'] }}</span>
                                                            @if($pack['is_full'] && $enrollStatus === null && !$isOwnPack)
                                                                <x-admin.shared.status-badge status="full" />
                                                            @endif
                                                            @if($isOwnPack)
                                                                <x-badge value="{{ __('You are the coach') }}" class="badge-ghost badge-sm" />
                                                            @endif
                                                            @if(!$pack['is_open_enrollment'])
                                                                @if($pack['is_full'])
                                                                    <span class="text-xs text-error font-semibold">{{ trans_choice(':n on waitlist|:n on waitlist', $pack['waitlist_count'], ['n' => $pack['waitlist_count']]) }}</span>
                                                                @else
                                                                    <span class="text-xs opacity-60">{{ trans_choice(':n spot left|:n spots left', $pack['spots_remaining'], ['n' => $pack['spots_remaining']]) }}</span>
                                                                @endif
                                                            @endif
                                                        </div>
                                                        <div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 mt-1.5 text-xs">
                                                            @if(!empty($pack['schedule']))
                                                                <span class="opacity-40">{{ __('Schedule') }}</span>
                                                                <span class="font-medium">{{ $pack['schedule'] }}</span>
                                                            @endif
                                                            @if(!empty($pack['room']))
                                                                <span class="opacity-40">{{ __('Room') }}</span>
                                                                <span class="font-medium">{{ $pack['room'] }}</span>
                                                            @endif
                                                            <span class="opacity-40">{{ __('Level') }}</span>
                                                            <span class="font-medium">{{ __($pack['level']) }}</span>
                                                            @if(!empty($pack['coach']))
                                                                <span class="opacity-40">{{ __('Trainer') }}</span>
                                                                <span class="font-medium">{{ $pack['coach'] }}</span>
                                                            @endif
                                                        </div>
                                                        <div class="mt-1 flex items-center gap-2">
                                                            <span class="text-xs font-semibold">{{ number_format($pack['price'], 2) }} €</span>
                                                            @if($pack['allow_discount'])
                                                                <span class="text-xs opacity-40 italic">{{ __('(discount may apply)') }}</span>
                                                            @endif
                                                            @if(!empty($pack['description']))
                                                                <button type="button" @click="descOpen = !descOpen" class="text-xs text-primary underline">
                                                                    <span x-text="descOpen ? '{{ __('Hide') }}' : '{{ __('Info') }}'"></span>
                                                                </button>
                                                            @endif
                                                        </div>
                                                        @if(!empty($pack['description']))
                                                            <div x-show="descOpen" x-collapse class="text-xs opacity-60 mt-1 leading-relaxed">
                                                                {{ $pack['description'] }}
                                                            </div>
                                                        @endif
                                                    </div>

                                                    @if($pack['is_open_enrollment'])
                                                        <x-badge value="{{ __('Free entry') }}" class="badge-ghost badge-sm shrink-0" />
                                                    @elseif(!$isOwnPack)
                                                        @if(!$hasActiveSub)
                                                            <label class="flex items-center cursor-pointer shrink-0 p-1">
                                                                <input type="checkbox"
                                                                    wire:model.live="pendingPackIds.{{ $userId }}"
                                                                    value="{{ $pack['id'] }}"
                                                                    class="checkbox checkbox-sm checkbox-primary" />
                                                            </label>
                                                        @else
                                                        <div class="flex items-center gap-2 shrink-0">
                                                            @if($enrollStatus === 'enrolled')
                                                                <x-admin.shared.status-badge status="enrolled" />
                                                                <div class="text-xs opacity-40 italic">{{ __('Contact the club to unenroll') }}</div>
                                                            @elseif($enrollStatus === 'pending')
                                                                <x-admin.shared.status-badge status="pending" />
                                                                <x-button
                                                                    :label="__('Cancel')"
                                                                    icon="o-x-mark"
                                                                    class="btn-ghost btn-sm"
                                                                    wire:click="confirmLeaveTrainingPack({{ $pack['id'] }}, {{ $userId }}, 'cancel')"
                                                                    spinner />
                                                            @elseif($enrollStatus === 'waiting')
                                                                <x-admin.shared.status-badge status="waiting" :detail="$enrollment['position']" />
                                                                <x-button
                                                                    :label="__('Leave')"
                                                                    icon="o-x-mark"
                                                                    class="btn-ghost btn-sm"
                                                                    wire:click="leaveTrainingPack({{ $pack['id'] }}, {{ $userId }})"
                                                                    spinner />
                                                            @elseif($enrollStatus === 'offered')
                                                                <div class="text-right">
                                                                    <x-admin.shared.status-badge status="offered" />
                                                                    @if(!empty($enrollment['deadline']))
                                                                        <div class="text-xs opacity-60 mt-0.5">{{ __('Confirm by') }} {{ \Carbon\Carbon::parse($enrollment['deadline'])->format('d/m H:i') }}</div>
                                                                    @endif
                                                                </div>
                                                                <x-button
                                                                    :label="__('Confirm')"
                                                                    icon="o-check"
                                                                    class="btn-success btn-sm"
                                                                    wire:click="confirmWaitlistOffer({{ $pack['id'] }}, {{ $userId }})"
                                                                    spinner />
                                                                <x-button
                                                                    :label="__('Decline')"
                                                                    icon="o-x-mark"
                                                                    class="btn-ghost btn-sm"
                                                                    wire:click="confirmLeaveTrainingPack({{ $pack['id'] }}, {{ $userId }}, 'decline')"
                                                                    spinner />
                                                            @else
                                                                @if($pack['is_full'])
                                                                    <x-button
                                                                        :label="__('Join waitlist')"
                                                                        icon="o-queue-list"
                                                                        class="btn-outline btn-sm"
                                                                        wire:click="enrollInPack({{ $pack['id'] }}, {{ $userId }})"
                                                                        spinner />
                                                                @else
                                                                    <x-button
                                                                        :label="__('Request')"
                                                                        icon="o-plus"
                                                                        class="btn-primary btn-sm"
                                                                        wire:click="enrollInPack({{ $pack['id'] }}, {{ $userId }})"
                                                                        spinner />
                                                                @endif
                                                            @endif
                                                        </div>
                                                        @endif
                                                    @endif
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if($isRegistering)
                                        <div class="mt-4 p-4 rounded-xl border border-base-200 bg-base-200/40">
                                            <x-toggle
                                                wire:model="registrations.{{ $userId }}.wants_directed_training"
                                                :label="__('No slot suits you? Let us know you are interested in directed training')"
                                                :hint="__('The club will contact you when organising the training schedule.')" />
                                        </div>
                                    @endif
                                </div>

                            {{-- ── RÉCAPITULATIF & ENVOI (inscription en cours) ────── --}}
                            @if($isRegistering)
                                <div class="mt-6 pt-6 border-t border-base-200">
                                    <div class="flex items-center gap-2 mb-4">
                                        <div class="flex items-center gap-1.5">
                                            <x-icon name="o-paper-airplane" class="w-4 h-4 text-base-content/40" />
                                            <span class="text-xs font-bold uppercase tracking-wide text-base-content/50">{{ __('Summary and submit') }}</span>
                                        </div>
                                        <div class="flex-1 border-t border-base-200"></div>
                                    </div>

                                    @php
                                        $formula       = $reg['formula'] ?? 'recreative';
                                        $formulaPrice  = $formula === 'competitive' ? 125.0 : 60.0;
                                        $packsData     = collect($availablePacks)->whereIn('id', $selectedPacks)->values();
                                        $discountable  = $packsData->filter(fn($p) => $p['allow_discount']);
                                        $applyDiscount = $discountable->count() > 1;
                                        $trainingTotal = $packsData->reduce(function ($carry, $p) use ($applyDiscount) {
                                            $price = (float) $p['price'];
                                            if ($p['allow_discount'] && $applyDiscount) {
                                                $price = max(0.0, $price - 10.0);
                                            }
                                            return $carry + $price;
                                        }, 0.0);
                                        $estimatedTotal = $formulaPrice + $trainingTotal;
                                    @endphp

                                    <div class="space-y-5">
                                        {{-- Price estimate --}}
                                        <div class="rounded-xl border border-base-200 bg-base-200/40 p-4 space-y-2">
                                            <div class="text-xs font-bold uppercase tracking-wide opacity-60 mb-3">{{ __('Price estimate') }}</div>
                                            <div class="flex justify-between text-sm">
                                                <span class="opacity-70">{{ $formula === 'competitive' ? __('Competition licence') : __('Recreational licence') }}</span>
                                                <span class="font-semibold">{{ number_format($formulaPrice, 2) }} €</span>
                                            </div>
                                            @foreach($packsData as $packItem)
                                                @php
                                                    $packPrice = (float) $packItem['price'];
                                                    $packDiscount = $packItem['allow_discount'] && $applyDiscount ? 10.0 : 0.0;
                                                    $packFinal = max(0.0, $packPrice - $packDiscount);
                                                @endphp
                                                <div class="flex justify-between text-sm">
                                                    <span class="opacity-70">{{ $packItem['name'] }}</span>
                                                    <span class="font-semibold">
                                                        @if($packDiscount > 0)
                                                            <span class="line-through opacity-40 mr-1">{{ number_format($packPrice, 2) }}</span>
                                                        @endif
                                                        {{ number_format($packFinal, 2) }} €
                                                    </span>
                                                </div>
                                            @endforeach
                                            @if($applyDiscount)
                                                <div class="text-xs opacity-50 italic">{{ __('Multi-pack discount applied (−10€/pack)') }}</div>
                                            @endif
                                            <div class="flex justify-between text-base font-bold pt-2 border-t border-base-200">
                                                <span>{{ __('Total') }}</span>
                                                <span class="text-primary">{{ number_format($estimatedTotal, 2) }} €</span>
                                            </div>
                                            <div class="text-xs opacity-40 italic">{{ __('Indicative — the club may adjust training prices upon validation.') }}</div>
                                        </div>

                                        {{-- Season involvement --}}
                                        <div class="rounded-xl border border-base-200 bg-base-200/40 p-4 space-y-4">
                                            <div class="text-xs font-bold uppercase tracking-wide opacity-60">{{ __('Getting involved this season') }}</div>
                                            <p class="text-xs opacity-60 -mt-2">{{ __('Optional — help us organise the season. You can update these anytime.') }}</p>

                                            <x-toggle
                                                wire:model.live="registrations.{{ $userId }}.can_drive"
                                                :label="__('I can drive to away matches')"
                                                :hint="__('Carpooling helps the whole club.')" />

                                            @if($reg['can_drive'] ?? false)
                                                <x-input
                                                    type="number"
                                                    min="1"
                                                    max="8"
                                                    wire:model="registrations.{{ $userId }}.seats_available"
                                                    :label="__('Seats available (incl. driver)')"
                                                    icon="o-user-group" />
                                            @endif

                                            <x-toggle
                                                wire:model="registrations.{{ $userId }}.wants_to_be_captain"
                                                :label="__('I would like to be a team captain')" />

                                            <x-toggle
                                                wire:model="registrations.{{ $userId }}.volunteer_help"
                                                :label="__('I am willing to help as a volunteer')" />
                                        </div>

                                        <x-button
                                            :label="__('Submit my affiliation')"
                                            icon="o-paper-airplane"
                                            class="btn-primary"
                                            wire:click="confirmAffiliation({{ $userId }})"
                                            spinner />
                                    </div>
                                </div>
                            @endif

                            {{-- ── DOCUMENTS (saison courante) ───────────────────────── --}}
                            <div class="mt-6 pt-6 border-t border-base-200">
                                <div class="flex items-center gap-2 mb-4">
                                    <div class="flex items-center gap-1.5">
                                        <x-icon name="o-document-text" class="w-4 h-4 text-base-content/40" />
                                        <span class="text-xs font-bold uppercase tracking-wide text-base-content/50">{{ __('Documents') }}</span>
                                    </div>
                                    <div class="flex-1 border-t border-base-200"></div>
                                </div>

                                <div class="space-y-4">
                                    <div class="flex items-center gap-4">
                                        @if(!empty($reg['medical_certificate_path']))
                                            <x-icon name="o-document-check" class="w-6 h-6 text-success shrink-0" />
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-semibold">{{ __('Medical Certificate') }}</div>
                                                <div class="text-xs opacity-60">{{ __('Uploaded') }}</div>
                                            </div>
                                            <a href="{{ route('admin.user.documents.download', [$reg['user_id'], 'medical']) }}" target="_blank" class="btn btn-ghost btn-xs gap-1">
                                                <x-icon name="o-eye" class="w-3.5 h-3.5" />
                                                {{ __('View') }}
                                            </a>
                                        @else
                                            <x-icon name="o-exclamation-triangle" class="w-6 h-6 text-warning-content shrink-0" />
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-semibold">{{ __('Medical Certificate') }}</div>
                                                <div class="text-xs opacity-60">{{ __('Missing — required for affiliation') }}</div>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-3">
                                            <input type="file" wire:model="medicalCertificate" id="medical-{{ $userId }}" class="hidden" accept=".jpg,.jpeg,.png,.pdf" />
                                            <label for="medical-{{ $userId }}" class="btn btn-outline btn-xs gap-1 cursor-pointer">
                                                <x-icon name="o-arrow-up-tray" class="w-3.5 h-3.5" />
                                                {{ empty($reg['medical_certificate_path']) ? __('Upload') : __('Replace') }}
                                            </label>
                                            @if($medicalCertificate)
                                                <x-button :label="__('Save')" icon="o-check" class="btn-success btn-xs" wire:click="uploadMedicalCertificate({{ $userId }})" spinner />
                                            @endif
                                        </div>
                                        @error('medicalCertificate')
                                            <p class="text-xs text-error">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    @if(!empty($reg['is_minor']))
                                        <x-menu-separator />
                                        <div class="flex items-center gap-4">
                                            @if(!empty($reg['parental_consent_path']))
                                                <x-icon name="o-document-check" class="w-6 h-6 text-success shrink-0" />
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm font-semibold">{{ __('Parental Consent') }}</div>
                                                    <div class="text-xs opacity-60">{{ __('Uploaded') }}</div>
                                                </div>
                                                <a href="{{ route('admin.user.documents.download', [$reg['user_id'], 'parental_consent']) }}" target="_blank" class="btn btn-ghost btn-xs gap-1">
                                                    <x-icon name="o-eye" class="w-3.5 h-3.5" />
                                                    {{ __('View') }}
                                                </a>
                                            @else
                                                <x-icon name="o-exclamation-triangle" class="w-6 h-6 text-warning-content shrink-0" />
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm font-semibold">{{ __('Parental Consent') }}</div>
                                                    <div class="text-xs opacity-60">{{ __('Required for members under 18') }}</div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-3">
                                                <input type="file" wire:model="parentalConsent" id="parental-{{ $userId }}" class="hidden" accept=".jpg,.jpeg,.png,.pdf" />
                                                <label for="parental-{{ $userId }}" class="btn btn-outline btn-xs gap-1 cursor-pointer">
                                                    <x-icon name="o-arrow-up-tray" class="w-3.5 h-3.5" />
                                                    {{ empty($reg['parental_consent_path']) ? __('Upload') : __('Replace') }}
                                                </label>
                                                @if($parentalConsent)
                                                    <x-button :label="__('Save')" icon="o-check" class="btn-success btn-xs" wire:click="uploadParentalConsent({{ $userId }})" spinner />
                                                @endif
                                            </div>
                                            @error('parentalConsent')
                                                <p class="text-xs text-error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @endif
                                </div>
                            </div>
                    </x-section-accordion>

                    {{-- Past seasons --}}
                    @if($pastEntries->count() > 0)
                        <div class="mt-3 space-y-2">
                            @foreach($pastEntries as $past)
                                @if($past['status'] === 'cancelled') @continue @endif
                                <x-section-accordion label="{{ $past['season_name'] }}" :open="false" color="gray" :uppercase="false">
                                    <x-slot:suffix>
                                        @if($past['status'] === 'paid')
                                            <x-badge value="{{ __('Paid') }}" class="badge-primary badge-sm" />
                                        @elseif($past['status'] === 'confirmed')
                                            <x-badge value="{{ __('Confirmed') }}" class="badge-success badge-sm" />
                                        @else
                                            <x-badge value="{{ __($past['status']) }}" class="badge-ghost badge-sm" />
                                        @endif
                                    </x-slot:suffix>
                                    <div class="pl-4 space-y-2">
                                        <div class="flex items-center gap-3 text-sm">
                                            @if($past['is_competitive'])
                                                <x-icon name="o-trophy" class="w-4 h-4 text-primary" />
                                                <span class="opacity-70">{{ __('Competition licence') }}</span>
                                            @else
                                                <x-icon name="o-heart" class="w-4 h-4 text-secondary" />
                                                <span class="opacity-70">{{ __('Recreational licence') }}</span>
                                            @endif
                                            <span class="font-bold ml-auto">{{ $past['amount_due'] }} €</span>
                                        </div>
                                        @foreach($past['enrolled_packs'] as $packInfo)
                                            <div class="flex items-center gap-2 text-xs opacity-50">
                                                <x-icon name="o-academic-cap" class="w-3.5 h-3.5 shrink-0" />
                                                <span>{{ $packInfo['name'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </x-section-accordion>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>
        </div>
        @endforeach
    </div>

    @if($isSingleMember)
        <p class="text-xs text-base-content/40 italic mt-8">
            {{ __('To add a family member, please contact the committee.') }}
        </p>
    @endif

    {{-- Modal: Payment details --}}
    <x-app-modal wire:model="paymentModal" :title="__('Payment Details')" box-class="max-w-md" :open="$paymentModal">
        @if(!empty($paymentDetails))
        <div class="space-y-6">
            <div class="flex flex-col items-center gap-3">
                <img src="{{ $paymentDetails['qr_code'] }}" alt="QR Code" class="w-48 h-48 rounded-xl border border-base-200 shadow" />
                <p class="text-xs opacity-50 text-center">{{ __('Scan this QR code with your banking app') }}</p>
            </div>

            <x-menu-separator />

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
                    <span class="text-lg font-bold text-primary">{{ $paymentDetails['amount_due'] }} €</span>
                </div>
            </div>

            <div class="flex gap-2 p-3 rounded-lg bg-warning/10 border border-warning/20 text-xs">
                <x-icon name="o-exclamation-triangle" class="w-4 h-4 text-warning-content shrink-0 mt-0.5" />
                <span class="opacity-80">{{ __('Always include the structured reference when making your transfer so your payment is automatically matched.') }}</span>
            </div>
        </div>
        @endif

        <x-slot:actions>
            <x-button :label="__('Close')" @click="$wire.paymentModal = false" class="btn-ghost" />
        </x-slot:actions>
    </x-app-modal>

    <x-confirm-modal model="cancelAffiliationModal" :title="__('Cancel affiliation?')" :subtitle="__('Warning!')"
        :confirmLabel="__('Yes, cancel it')" confirmAction="cancelAffiliation" :open="$cancelAffiliationModal">
        <p>{{ __('Are you sure you want to cancel your affiliation request? This action cannot be undone.') }}</p>
    </x-confirm-modal>

    {{-- Confirmation : quitter / annuler / refuser un pack entraînement --}}
    <x-app-modal :subtitle="__('Warning!')" :title="__('Confirm action')" wire:model="leavePackModal" :open="$leavePackModal">
        @if($leavePackContext === 'leave')
            <p>{{ __('Are you sure you want to leave this training pack? Your spot will be offered to the next person on the waitlist.') }}</p>
        @elseif($leavePackContext === 'cancel')
            <p>{{ __('Are you sure you want to cancel your training request? The slot will be freed for another player.') }}</p>
        @else
            <p>{{ __('Are you sure you want to decline this spot offer? You will be removed from the waitlist.') }}</p>
        @endif

        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('leavePackModal', false)" />
            <x-button class="btn-error" :label="__('Confirm')" spinner wire:click="leaveTrainingPackConfirmed" />
        </x-slot:actions>
    </x-app-modal>

</div>
