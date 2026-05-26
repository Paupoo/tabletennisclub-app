<div>
    <x-header :title="__('Registrations')" :subtitle="__('All affiliations by season')" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input
                :placeholder="__('Search a member...')"
                wire:model.live.debounce.300ms="search"
                icon="o-magnifying-glass"
                class="border-none bg-base-200" />
        </x-slot:middle>
        <x-slot:actions>
            @if(!$this->registrationClosed)
            <x-button
                :label="__('Register a member')"
                icon="o-user-plus"
                class="btn-primary btn-sm"
                @click="$wire.memberDrawer = true" />
            @endif
            <x-button :label="$this->registrationClosed ? __('Open Registrations') : __('Close Registrations')"
                :icon="$this->registrationClosed ? 'o-lock-open' : 'o-lock-closed'"
                class="btn-outline btn-sm"
                wire:click="toggleRegistrations" />
        </x-slot:actions>
    </x-header>

    {{-- ── Filters ─────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-3 mb-5">
        <x-select
            wire:model.live="selectedSeasonId"
            :options="$this->seasonOptions"
            :placeholder="__('All seasons')"
            class="select-sm border-base-300 min-w-44"
            icon="o-calendar" />

        <div class="flex items-center gap-1.5 flex-wrap">
            @foreach([
                ''          => __('All'),
                'pending'   => __('To process'),
                'confirmed' => __('Confirmed'),
                'paid'      => __('Paid'),
                'cancelled' => __('Cancelled'),
            ] as $value => $label)
            <button
                wire:click="$set('statusFilter', '{{ $value }}')"
                @class([
                    'px-3 py-1 rounded-full text-xs font-semibold border transition-colors',
                    'bg-base-content text-base-100 border-base-content' => $statusFilter === $value,
                    'border-base-300 hover:border-base-content/40'       => $statusFilter !== $value,
                ])>
                {{ $label }}
            </button>
            @endforeach
        </div>

        <span class="text-xs opacity-40 ml-auto">{{ $registrations->count() }} {{ __('result(s)') }}</span>
    </div>

    {{-- ── Pending Requests (Flux A + Flux B) ────────────────────────────────
         Toute demande nécessitant une action admin, avant le tableau principal --}}
    @php $totalPending = $this->pendingSubscriptions->count() + $this->trainingRequests->count(); @endphp
    @if($totalPending > 0)
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-3">
            <x-icon name="o-clock" class="w-4 h-4 text-warning" />
            <span class="text-sm font-bold uppercase tracking-widest text-warning">{{ __('Pending Requests') }}</span>
            <x-badge value="{{ $totalPending }}" class="badge-warning badge-sm" />
            <div class="flex-1 border-t border-warning/30"></div>
        </div>
        <x-card class="bg-base-100 border border-warning/30 shadow-sm">
            <div class="divide-y divide-base-200">
                @foreach($this->pendingSubscriptions as $sub)
                <div class="flex items-center gap-4 py-3 first:pt-0 last:pb-0">
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm">{{ $sub->user->first_name }} {{ $sub->user->last_name }}</div>
                        <div class="text-xs opacity-50 mt-0.5">{{ __('New affiliation request') }}</div>
                    </div>
                    <x-button
                        :label="__('Review')"
                        icon="o-check-circle"
                        class="btn-sm btn-warning"
                        wire:click="review({{ $sub->id }})" />
                </div>
                @endforeach
                @foreach($this->trainingRequests as $sub)
                <div class="flex items-center gap-4 py-3 first:pt-0 last:pb-0">
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm">{{ $sub->user->first_name }} {{ $sub->user->last_name }}</div>
                        <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                            @foreach($sub->trainingPacks as $pack)
                                <x-badge value="{{ $pack->name }}" class="badge-warning badge-xs" />
                            @endforeach
                        </div>
                    </div>
                    <x-button
                        :label="__('Review')"
                        icon="o-academic-cap"
                        class="btn-sm btn-warning"
                        wire:click="reviewTrainingRequest({{ $sub->id }})" />
                </div>
                @endforeach
            </div>
        </x-card>
    </div>
    @endif

    {{-- ── All subscriptions ───────────────────────────────────────────────── --}}
    <x-card class="bg-base-100 border-none shadow-sm mb-8">
        <x-table :headers="$headers" :rows="$registrations" hover>

            @scope('cell_name', $req)
            <div>
                <span class="font-bold text-base-content">{{ $req->name }}</span>
                <div class="text-xs opacity-50 hidden md:block">{{ $req->type }}</div>
            </div>
            @endscope

            @scope('cell_type', $req)
            <span class="text-xs uppercase tracking-widest opacity-60">{{ $req->type }}</span>
            @endscope

            @scope('cell_trainings_count', $req)
            @if($req->trainings_count > 0)
                <span class="text-sm font-semibold">{{ $req->trainings_count }}</span>
            @else
                <span class="text-xs opacity-30">—</span>
            @endif
            @endscope

            @scope('cell_amount_due', $req)
            @if($req->amount_due > 0)
                <div>
                    <span class="text-sm font-bold">{{ number_format($req->amount_due, 2) }} €</span>
                    @if($req->payment_status === 'pending')
                        <div class="text-xs text-warning opacity-70">{{ __('Awaiting payment') }}</div>
                    @elseif($req->payment_status === 'paid')
                        <div class="text-xs text-success opacity-70">{{ __('Paid') }}</div>
                    @endif
                </div>
            @else
                <span class="text-xs opacity-30">—</span>
            @endif
            @endscope

            @scope('cell_status', $req)
            @if($req->status === 'pending')
                <x-badge value="{{ __('To process') }}" class="badge-warning badge-sm" />
            @elseif($req->status === 'confirmed')
                <x-badge value="{{ __('Confirmed') }}" class="badge-info badge-sm" />
            @elseif($req->status === 'paid')
                <x-badge value="{{ __('Paid') }}" class="badge-success badge-sm" />
            @elseif($req->status === 'cancelled')
                <x-badge value="{{ __('Cancelled') }}" class="badge-ghost badge-sm" />
            @endif
            @endscope

            @scope('actions', $req)
            <x-button :label="__('Details')" wire:click="review({{ $req->id }})" class="btn-xs btn-ghost" />
            @endscope

        </x-table>
    </x-card>

    {{-- ── Review modal (all statuses) ────────────────────────────────────── --}}
    <x-modal wire:model="reviewModal" title="{{ $currentRequest?->name ?? '' }}" separator class="backdrop-blur-sm">

        {{-- Read-only view for confirmed/paid/cancelled --}}
        @if($currentRequest && $currentRequest->status !== 'pending' && !$paymentGenerated)
        <div class="space-y-6">

            {{-- Affiliation --}}
            <div>
                <h3 class="text-xs font-bold opacity-40 uppercase tracking-widest mb-3">{{ __('Affiliation') }}</h3>
                <div class="flex items-center gap-3 p-3 rounded-xl bg-base-200/60 border border-base-300 text-sm">
                    <x-icon name="{{ $currentRequest->type === __('Compétition') ? 'o-trophy' : 'o-heart' }}" class="w-4 h-4 opacity-50 shrink-0" />
                    <span class="flex-1">{{ $currentRequest->type }}</span>
                    @if($currentRequest->status === 'paid')
                        <x-badge value="{{ __('Paid') }}" class="badge-success badge-sm" />
                    @elseif($currentRequest->status === 'confirmed')
                        <x-badge value="{{ __('Confirmed') }}" class="badge-info badge-sm" />
                    @elseif($currentRequest->status === 'cancelled')
                        <x-badge value="{{ __('Cancelled') }}" class="badge-ghost badge-sm" />
                    @endif
                </div>
            </div>

            {{-- Training packs --}}
            @if($currentRequest->enrolled_packs->count() > 0 || $currentRequest->pending_packs->count() > 0)
            <div>
                <h3 class="text-xs font-bold opacity-40 uppercase tracking-widest mb-3">{{ __('Training Packs') }}</h3>
                <div class="space-y-2">
                    @foreach($currentRequest->enrolled_packs as $pack)
                    <div class="flex items-center gap-3 p-2.5 rounded-lg border border-base-200 text-sm">
                        <x-icon name="o-academic-cap" class="w-3.5 h-3.5 text-primary opacity-60 shrink-0" />
                        <span class="flex-1">{{ $pack->name }}</span>
                        <x-badge value="{{ __('Enrolled') }}" class="badge-primary badge-xs" />
                        <span class="text-xs opacity-50 font-semibold">{{ number_format((float) $pack->price, 2) }} €</span>
                        @if(in_array($currentRequest->status, ['confirmed', 'paid']))
                            <x-button
                                icon="o-arrow-uturn-left"
                                :tooltip="__('Remove & refund')"
                                class="btn-ghost btn-xs text-error"
                                wire:click="openRefundModal({{ $currentRequest->id }}, {{ $pack->id }})"
                                spinner />
                        @endif
                    </div>
                    @endforeach
                    @foreach($currentRequest->pending_packs as $pack)
                    <div class="flex items-center gap-3 p-2.5 rounded-lg border border-warning/20 bg-warning/5 text-sm">
                        <x-icon name="o-academic-cap" class="w-3.5 h-3.5 text-warning opacity-60 shrink-0" />
                        <span class="flex-1">{{ $pack->name }}</span>
                        <x-badge value="{{ __('Awaiting validation') }}" class="badge-warning badge-xs" />
                        <span class="text-xs opacity-50 font-semibold">{{ number_format((float) $pack->price, 2) }} €</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Payments --}}
            @if(!empty($currentRequest->payments))
            <div>
                <h3 class="text-xs font-bold opacity-40 uppercase tracking-widest mb-3">{{ __('Payments') }}</h3>
                <div class="space-y-2">
                    @foreach($currentRequest->payments as $payment)
                    <div class="flex items-center gap-3 p-2.5 rounded-lg border border-base-200 text-sm">
                        <x-icon name="o-credit-card" class="w-3.5 h-3.5 opacity-40 shrink-0" />
                        <span class="font-mono text-xs opacity-60 flex-1">{{ $payment['reference'] }}</span>
                        <span class="font-bold text-sm">{{ number_format($payment['amount_due'], 2) }} €</span>
                        @if($payment['status'] === 'paid')
                            <x-badge value="{{ __('Paid') }}" class="badge-success badge-xs" />
                        @else
                            <x-badge value="{{ __('Pending') }}" class="badge-warning badge-xs" />
                        @endif
                    </div>
                    @endforeach
                </div>
                <div class="flex justify-between pt-2 mt-1 border-t border-base-200 text-sm">
                    <span class="opacity-50">{{ __('Total') }}</span>
                    <span class="font-black">{{ number_format($currentRequest->amount_due, 2) }} €</span>
                </div>
            </div>
            @endif

        </div>
        @endif

        {{-- Pending: approve/reject view --}}
        @if(!$paymentGenerated && $currentRequest && $currentRequest->status === 'pending')
        <div class="space-y-6">
            <div>
                <h3 class="text-xs font-bold opacity-40 uppercase tracking-widest mb-3">{{ __('Members & Licence') }}</h3>
                <div class="space-y-3">
                    @foreach($currentRequest->members as $member)
                    <div class="bg-base-200 p-4 rounded-xl border border-base-300/50">
                        <div class="font-bold text-base-content">{{ $member['first_name'] }} {{ $member['last_name'] }}</div>
                        <div class="text-xs opacity-50 mt-0.5">{{ $currentRequest->type }}</div>
                    </div>
                    @endforeach
                </div>
            </div>

            @if($currentRequest->pending_packs->count() > 0)
            <div>
                <h3 class="text-xs font-bold opacity-40 uppercase tracking-widest mb-3">{{ __('Training Packs Requested') }}</h3>
                <div class="space-y-2">
                    @foreach($currentRequest->pending_packs as $pack)
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-base-200 cursor-pointer hover:border-primary/30 transition-colors has-[:checked]:border-primary/20 has-[:checked]:bg-primary/5">
                        <input
                            type="checkbox"
                            wire:model.live="approvedPackIds"
                            value="{{ $pack->id }}"
                            class="checkbox checkbox-sm checkbox-primary shrink-0" />
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm">{{ $pack->name }}</div>
                            <div class="text-xs opacity-50">{{ number_format((float) $pack->price, 2) }} €</div>
                        </div>
                    </label>
                    @endforeach
                </div>
                <p class="text-xs opacity-40 mt-2 italic">{{ __('Unchecked packs will be removed from the request.') }}</p>
            </div>
            @endif

            {{-- Price summary --}}
            <div class="rounded-xl border border-base-200 overflow-hidden text-sm">
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-2 opacity-60">
                        <x-icon name="{{ $currentRequest->type === __('Compétition') ? 'o-trophy' : 'o-heart' }}" class="w-3.5 h-3.5 shrink-0" />
                        <span>{{ __('Affiliation') }} · {{ $currentRequest->type }}</span>
                    </div>
                    <span class="font-semibold">{{ number_format($currentRequest->subscription_price, 2) }} €</span>
                </div>
                @if(!empty($this->approvedPackIds))
                <div class="flex items-center justify-between px-4 py-3 border-t border-base-200">
                    <div class="flex items-center gap-2 opacity-60">
                        <x-icon name="o-academic-cap" class="w-3.5 h-3.5 shrink-0" />
                        <span>{{ __('Training packs') }} ({{ count($this->approvedPackIds) }})</span>
                    </div>
                    <span class="font-semibold">{{ number_format($this->pendingReviewEstimatedTotal - $currentRequest->subscription_price, 2) }} €</span>
                </div>
                @endif
                <div class="flex items-center justify-between px-4 py-3 bg-base-200/50 border-t border-base-200 font-bold">
                    <span>{{ __('Total if approved') }}</span>
                    <span class="text-primary text-base">{{ number_format($this->pendingReviewEstimatedTotal, 2) }} €</span>
                </div>
            </div>
        </div>
        @endif

        {{-- Post-approval: payment generated --}}
        @if($paymentGenerated && !empty($paymentData))
        <div class="space-y-6">
            <div class="flex flex-col items-center gap-3">
                <img src="{{ $paymentData['qr_code'] }}" alt="QR Code" class="w-48 h-48 rounded-xl border border-base-200 shadow" />
                <p class="text-xs opacity-50 text-center">{{ __('Scan this QR code with your banking app') }}</p>
            </div>
            <x-menu-separator />
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="opacity-60">{{ __('Beneficiary') }}</span>
                    <span class="font-semibold">{{ $paymentData['beneficiary'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="opacity-60">{{ __('IBAN') }}</span>
                    <span class="font-mono font-semibold tracking-wide">{{ $paymentData['iban'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="opacity-60">{{ __('BIC') }}</span>
                    <span class="font-mono font-semibold">{{ $paymentData['bic'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="opacity-60">{{ __('Structured reference') }}</span>
                    <span class="font-mono font-bold text-primary">{{ $paymentData['reference'] }}</span>
                </div>
                <div class="flex justify-between items-center pt-1 border-t border-base-200">
                    <span class="font-bold">{{ __('Amount') }}</span>
                    <span class="text-lg font-black text-primary">{{ $paymentData['amount_due'] }} €</span>
                </div>
            </div>
            <div class="flex gap-2 p-3 rounded-lg bg-warning/10 border border-warning/20 text-xs">
                <x-icon name="o-exclamation-triangle" class="w-4 h-4 text-warning shrink-0 mt-0.5" />
                <span class="opacity-80">{{ __('Always include the structured reference when making your transfer so your payment is automatically matched.') }}</span>
            </div>
            <div class="flex items-center gap-3 p-3 rounded-xl bg-base-200/50 border border-base-300 text-sm">
                <x-icon name="o-envelope" class="w-4 h-4 opacity-50 shrink-0" />
                <span class="flex-1 opacity-70">{{ $paymentData['member_name'] }} &lt;{{ $paymentData['member_email'] }}&gt;</span>
                @if(($paymentData['invitation_counter'] ?? 0) > 0)
                <span class="text-xs opacity-50 italic shrink-0">
                    {{ __('Sent :n×', ['n' => $paymentData['invitation_counter']]) }}
                </span>
                @endif
            </div>
        </div>
        @endif

        {{-- Rejection reason (only when reviewing pending) --}}
        @if(!$paymentGenerated && $currentRequest && $currentRequest->status === 'pending')
        <div x-data="{ rejectOpen: false }" class="mt-4">
            <button type="button" @click="rejectOpen = !rejectOpen" class="flex items-center gap-1.5 text-xs text-error opacity-60 hover:opacity-100 transition-opacity">
                <x-icon name="o-chevron-down" class="w-3.5 h-3.5 transition-transform" ::class="rejectOpen ? '' : '-rotate-90'" />
                {{ __('Add a rejection reason') }}
            </button>
            <div x-show="rejectOpen" x-collapse class="mt-3 space-y-3 p-4 rounded-xl border border-error/20 bg-error/5">
                <div class="text-xs font-bold uppercase tracking-widest opacity-40 mb-2">{{ __('Rejection template') }}</div>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                        <input type="radio" wire:model="rejectionTemplate" value="" class="radio radio-xs" />
                        <span class="opacity-70">{{ __('No template — custom message only') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                        <input type="radio" wire:model="rejectionTemplate" value="level" class="radio radio-xs radio-error" />
                        <span>{{ __('Wrong level (too strong / too weak)') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                        <input type="radio" wire:model="rejectionTemplate" value="full_teams" class="radio radio-xs radio-error" />
                        <span>{{ __('No spots left in competition teams') }}</span>
                    </label>
                </div>
                <textarea
                    wire:model="rejectionMessage"
                    :placeholder="__('Optional personal note to the member...')"
                    class="textarea textarea-sm textarea-bordered w-full text-sm"
                    rows="2"></textarea>
            </div>
        </div>
        @endif

        <x-slot:actions>
            @if(!$paymentGenerated && $currentRequest && $currentRequest->status === 'pending')
            <x-button :label="__('Reject')" wire:click="reject" class="btn-ghost text-error" spinner />
            <x-button :label="__('Approve and Invoice')" wire:click="approve" class="btn-primary shadow-lg" spinner />
            @elseif($paymentGenerated)
            <x-button :label="__('Close')" @click="$wire.reviewModal = false" class="btn-ghost" />
            <x-button :label="__('Send by email')" icon="o-paper-airplane" class="btn-primary" wire:click="sendPaymentEmail" spinner />
            @else
            <x-button :label="__('Close')" @click="$wire.reviewModal = false" class="btn-ghost" />
            @endif
        </x-slot:actions>
    </x-modal>

    {{-- ── Training request modal (Flux B) ────────────────────────────────── --}}
    <x-modal wire:model="trainingRequestModal" :title="__('Training Request')" separator class="backdrop-blur-sm">

        @if(!$paymentGenerated && $currentTrainingRequest)
        <div class="space-y-6">
            <div class="flex items-center gap-3 p-3 rounded-xl bg-base-200/60 border border-base-300 text-sm">
                <x-icon name="o-user" class="w-4 h-4 opacity-50 shrink-0" />
                <span class="font-semibold">{{ $currentTrainingRequest->user->first_name }} {{ $currentTrainingRequest->user->last_name }}</span>
                @if($currentTrainingRequest->status === 'confirmed')
                    <x-badge value="{{ __('Confirmed member') }}" class="badge-info badge-sm ml-auto" />
                @else
                    <x-badge value="{{ __('Paid member') }}" class="badge-primary badge-sm ml-auto" />
                @endif
            </div>

            <div>
                <h3 class="text-xs font-bold opacity-40 uppercase tracking-widest mb-3">{{ __('Packs Requested') }}</h3>
                @php $bd = $this->trainingRequestPricingBreakdown; @endphp
                <div class="space-y-2">
                    @foreach($currentTrainingRequest->trainingPacks as $pack)
                    @php
                        $pb         = ($bd['new_packs'] ?? [])[$pack->id] ?? null;
                        $inApproved = in_array($pack->id, $approvedPackIds);
                        $discounted = $inApproved && ($pb['discounted'] ?? false);
                    @endphp
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-base-200 cursor-pointer hover:border-warning/30 transition-colors has-[:checked]:border-warning/20 has-[:checked]:bg-warning/5">
                        <input
                            type="checkbox"
                            wire:model.live="approvedPackIds"
                            value="{{ $pack->id }}"
                            class="checkbox checkbox-sm checkbox-warning shrink-0" />
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm">{{ $pack->name }}</div>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                @if($discounted)
                                    <span class="text-xs line-through opacity-40">{{ number_format($pb['full_price'], 2) }} €</span>
                                    <span class="text-xs font-bold text-success">{{ number_format($pb['effective_price'], 2) }} €</span>
                                    <x-badge class="badge-success badge-soft badge-xs" value="−10 €" />
                                @else
                                    <span class="text-xs opacity-50">{{ number_format((float) $pack->price, 2) }} €</span>
                                @endif
                            </div>
                        </div>
                    </label>
                    @endforeach
                </div>
                <p class="text-xs opacity-40 mt-2 italic">{{ __('Unchecked packs will be removed from the request.') }}</p>

                {{-- Retroactive discount on already-enrolled packs --}}
                @if(!empty($bd['retro_adjustments'] ?? []))
                <div class="mt-3 rounded-xl border border-info/20 bg-info/5 p-3 space-y-2">
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-info mb-1">
                        <x-icon name="o-information-circle" class="w-3.5 h-3.5 shrink-0" />
                        {{ __('Multi-pack discount — also applied retroactively') }}
                    </div>
                    @foreach($bd['retro_adjustments'] as $adj)
                    <div class="flex items-center justify-between text-xs">
                        <span class="opacity-60 truncate flex-1 pr-3">{{ $adj['name'] }}</span>
                        <span class="flex items-center gap-1.5 shrink-0">
                            <span class="line-through opacity-40">{{ number_format($adj['original_price'], 2) }} €</span>
                            <x-icon name="o-arrow-right" class="w-3 h-3 opacity-30" />
                            <span class="font-semibold">{{ number_format($adj['new_price'], 2) }} €</span>
                            <x-badge class="badge-info badge-soft badge-xs" value="−10 €" />
                        </span>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Total --}}
                <div class="flex justify-between pt-3 mt-3 border-t border-base-200 text-sm">
                    <span class="opacity-50">{{ __('Expected additional payment') }}</span>
                    <span class="font-bold text-warning">{{ number_format($this->trainingRequestEstimatedDelta, 2) }} €</span>
                </div>
            </div>
        </div>
        @endif

        @if($paymentGenerated && !empty($paymentData))
        <div class="space-y-6">
            <div class="flex flex-col items-center gap-3">
                <img src="{{ $paymentData['qr_code'] }}" alt="QR Code" class="w-48 h-48 rounded-xl border border-base-200 shadow" />
                <p class="text-xs opacity-50 text-center">{{ __('Scan this QR code with your banking app') }}</p>
            </div>
            <x-menu-separator />
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="opacity-60">{{ __('Structured reference') }}</span>
                    <span class="font-mono font-bold text-primary">{{ $paymentData['reference'] }}</span>
                </div>
                <div class="flex justify-between items-center pt-1 border-t border-base-200">
                    <span class="font-bold">{{ __('Amount') }}</span>
                    <span class="text-lg font-black text-primary">{{ $paymentData['amount_due'] }} €</span>
                </div>
            </div>
            <div class="flex items-center gap-3 p-3 rounded-xl bg-base-200/50 border border-base-300 text-sm">
                <x-icon name="o-envelope" class="w-4 h-4 opacity-50 shrink-0" />
                <span class="flex-1 opacity-70">{{ $paymentData['member_name'] }} &lt;{{ $paymentData['member_email'] }}&gt;</span>
            </div>
        </div>
        @endif

        @if(!$paymentGenerated && $currentTrainingRequest)
        <div x-data="{ rejectOpen: false }" class="mt-4">
            <button type="button" @click="rejectOpen = !rejectOpen" class="flex items-center gap-1.5 text-xs text-error opacity-60 hover:opacity-100 transition-opacity">
                <x-icon name="o-chevron-down" class="w-3.5 h-3.5 transition-transform" ::class="rejectOpen ? '' : '-rotate-90'" />
                {{ __('Add a rejection reason') }}
            </button>
            <div x-show="rejectOpen" x-collapse class="mt-3 space-y-3 p-4 rounded-xl border border-error/20 bg-error/5">
                <div class="text-xs font-bold uppercase tracking-widest opacity-40 mb-2">{{ __('Rejection template') }}</div>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                        <input type="radio" wire:model="rejectionTemplate" value="" class="radio radio-xs" />
                        <span class="opacity-70">{{ __('No template — custom message only') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                        <input type="radio" wire:model="rejectionTemplate" value="level" class="radio radio-xs radio-error" />
                        <span>{{ __('Wrong level (too strong / too weak)') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                        <input type="radio" wire:model="rejectionTemplate" value="full_teams" class="radio radio-xs radio-error" />
                        <span>{{ __('No spots left in competition teams') }}</span>
                    </label>
                </div>
                <textarea
                    wire:model="rejectionMessage"
                    :placeholder="__('Optional personal note to the member...')"
                    class="textarea textarea-sm textarea-bordered w-full text-sm"
                    rows="2"></textarea>
            </div>
        </div>
        @endif

        <x-slot:actions>
            @if(!$paymentGenerated)
            <x-button :label="__('Cancel')" @click="$wire.trainingRequestModal = false" class="btn-ghost" />
            <x-button :label="__('Reject all')" wire:click="rejectTrainingRequest" class="btn-ghost text-error" spinner />
            <x-button :label="__('Approve')" icon="o-check" wire:click="approveTrainingRequest" class="btn-warning shadow-lg" spinner />
            @else
            <x-button :label="__('Close')" @click="$wire.trainingRequestModal = false; $wire.paymentGenerated = false" class="btn-ghost" />
            <x-button :label="__('Send by email')" icon="o-paper-airplane" class="btn-primary" wire:click="sendPaymentEmail" spinner />
            @endif
        </x-slot:actions>
    </x-modal>

    {{-- ── Drawer pour l'inscription/renouvellement ────────────────────────── --}}
    <x-drawer wire:model="memberDrawer" :title="__('Family Registration')" right separator with-close-button class="w-11/12 md:w-5/12">

        <div class="space-y-6">
            <div class="bg-base-200 p-4 rounded-xl">
                <x-input
                    :placeholder="__('Search for a member to add to the group...')"
                    wire:model.live.debounce.300ms="searchMember"
                    icon="o-magnifying-glass"
                    :hint="__('Add all family members here')" />

                @if(strlen($searchMember) > 2)
                <div class="mt-2 shadow-lg bg-base-100 rounded-lg border border-base-300">
                    @foreach($membersFound as $m)
                    <div class="p-3 flex justify-between items-center hover:bg-base-200 cursor-pointer border-b last:border-none"
                        wire:click="addToBasket({{ $m->id }})">
                        <span class="text-sm font-bold">{{ $m->first_name }} {{ $m->last_name }}</span>
                        <x-icon name="o-plus-circle" class="w-5 h-5 text-primary" />
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            <div class="space-y-4">
                @forelse($familyBasket as $userId => $config)
                <div class="border-2 border-base-300 rounded-2xl p-4 relative bg-base-100 shadow-sm">
                    <button wire:click="removeFromBasket({{ $userId }})" class="absolute top-2 right-2 text-error hover:scale-110 transition-transform">
                        <x-icon name="o-trash" class="w-4 h-4" />
                    </button>

                    <h3 class="font-black text-primary uppercase text-xs mb-4 flex items-center gap-2">
                        <x-icon name="o-user" class="w-4 h-4" />
                        {{ $config['name'] }}
                    </h3>

                    <div class="grid grid-cols-1 gap-4">
                        <x-radio
                            :label="__('Licence type')"
                            wire:model="familyBasket.{{ $userId }}.licence_type"
                            :options="[['id' => 'competitive', 'name' => __('Competitive')], ['id' => 'recreative', 'name' => __('Recreational')]]"
                            class="radio-sm" />

                        <x-choices
                            :label="__('Trainings')"
                            wire:model="familyBasket.{{ $userId }}.trainings"
                            :options="$this->trainingOptions()"
                            compact
                            allow-all />
                    </div>
                </div>
                @empty
                <div class="text-center py-10 opacity-40 italic border-2 border-dashed rounded-2xl">
                    {{ __('No member selected. Use the search above.') }}
                </div>
                @endforelse
            </div>
        </div>

        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.memberDrawer = false" />
            @if(count($familyBasket) > 0)
            <x-button
                :label="__('Validate group registration') . ' (' . count($familyBasket) . ')'"
                icon="o-check"
                class="btn-primary"
                wire:click="saveFamilyRegistration" />
            @endif
        </x-slot:actions>
    </x-drawer>

    {{-- ── Refund confirmation modal ──────────────────────────────────────── --}}
    @php
        $refundSub  = $refundSubscriptionId ? $this->registrations()->firstWhere('id', $refundSubscriptionId) : null;
        $refundPack = $refundPackId ? $refundSub?->enrolled_packs->firstWhere('id', $refundPackId) : null;
    @endphp
    <x-modal wire:model="refundModal" :title="__('Remove & Refund')" separator class="backdrop-blur-sm">
        @if($refundPack)
        <div class="space-y-4">
            <p class="text-sm">
                {{ __('You are about to remove :member from :pack and generate a refund of :amount€.', [
                    'member' => $refundSub?->name ?? '',
                    'pack'   => $refundPack->name,
                    'amount' => number_format((float) $refundPack->price, 2),
                ]) }}
            </p>
            @php $iban = optional(optional($refundSub)->members[0] ?? null)['iban'] ?? null; @endphp
            @php
                $subModel = $refundSubscriptionId ? App\Models\ClubAdmin\Subscription\Subscription::with('user')->find($refundSubscriptionId) : null;
                $userIban = $subModel?->user?->iban;
            @endphp
            @if($userIban)
                <div class="flex items-center gap-2 p-3 rounded-lg bg-success/10 border border-success/20 text-sm">
                    <x-icon name="o-building-library" class="w-4 h-4 text-success shrink-0" />
                    <span>{{ __('Refund IBAN:') }} <span class="font-mono font-bold">{{ $userIban }}</span></span>
                </div>
            @else
                <div class="flex items-center gap-2 p-3 rounded-lg bg-warning/10 border border-warning/20 text-sm">
                    <x-icon name="o-exclamation-triangle" class="w-4 h-4 text-warning shrink-0" />
                    <span>{{ __('No IBAN on file — you will need to handle the refund manually.') }}</span>
                </div>
            @endif
        </div>
        @endif
        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.refundModal = false" class="btn-ghost" />
            <x-button :label="__('Confirm refund')" icon="o-arrow-uturn-left" class="btn-error" wire:click="confirmRefund" spinner />
        </x-slot:actions>
    </x-modal>
</div>
