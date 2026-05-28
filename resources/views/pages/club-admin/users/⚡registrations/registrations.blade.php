<div>
    <x-header :title="__('Registrations')" :subtitle="__('All affiliations by season')" separator progress-indicator>
        <x-slot:middle>
            <x-select
                wire:model.live="selectedSeasonId"
                :options="$this->seasonOptions"
                :placeholder="__('All seasons')"
                icon="o-calendar"
                class="w-full" />
        </x-slot:middle>
        <x-slot:actions>
            <x-input class="input-sm w-48" clearable icon="o-magnifying-glass"
                :placeholder="__('Search a member...')"
                wire:model.live.debounce.300ms="search" />
            <x-button class="btn-ghost {{ $this->activeFiltersCount > 0 ? 'btn-active' : '' }}"
                wire:click="$toggle('showFilters')">
                <x-icon name="o-funnel" class="h-5 w-5" />
                {{ __('Filters') }}
                @if ($this->activeFiltersCount > 0)
                    <x-badge class="badge-sm badge-primary" value="{{ $this->activeFiltersCount }}" />
                @endif
            </x-button>
            @if (! $this->registrationClosed)
                <x-button :label="__('Register a member')" icon="o-user-plus"
                    class="btn-primary btn-sm"
                    @click="$wire.memberDrawer = true" />
            @endif
            <x-button
                :label="$this->registrationClosed ? __('Open Registrations') : __('Close Registrations')"
                :icon="$this->registrationClosed ? 'o-lock-open' : 'o-lock-closed'"
                class="btn-outline btn-sm"
                wire:click="toggleRegistrations" />
        </x-slot:actions>
    </x-header>

    {{-- ── Filtres ────────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-bar :active-filters-count="$this->activeFiltersCount" :show="$showFilters">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Status') }}
                </p>
                <x-select :options="$statusOptions" :placeholder="__('All statuses')"
                    wire:model.live="statusFilter" class="w-full" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-bar>

    {{-- ── Cartes stats ──────────────────────────────────────────────── --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $statCards = [
                ['label' => __('Total'),     'key' => 'total',     'icon' => 'o-users',       'bg' => 'bg-base-200',    'color' => 'text-base-content/60'],
                ['label' => __('Pending'),   'key' => 'pending',   'icon' => 'o-clock',       'bg' => 'bg-warning/10',  'color' => 'text-warning'],
                ['label' => __('Confirmed'), 'key' => 'confirmed', 'icon' => 'o-check-circle','bg' => 'bg-info/10',     'color' => 'text-info'],
                ['label' => __('Paid'),      'key' => 'paid',      'icon' => 'o-banknotes',   'bg' => 'bg-success/10',  'color' => 'text-success'],
            ];
        @endphp
        @foreach ($statCards as $card)
            <x-card class="shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $card['bg'] }}">
                        <x-icon name="{{ $card['icon'] }}" class="h-5 w-5 {{ $card['color'] }}" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold {{ $card['color'] }}">{{ $stats[$card['key']] ?? 0 }}</p>
                        <p class="text-xs text-base-content/40">{{ $card['label'] }}</p>
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>

    {{-- ── Demandes en attente (Flux A + Flux B) ───────────────────────
         Toute demande nécessitant une action admin, avant le tableau principal --}}
    @php $totalPending = $this->pendingSubscriptions->count() + $this->trainingRequests->count(); @endphp
    @if ($totalPending > 0)
        <div class="mb-6">
            <div class="mb-3 flex items-center gap-2">
                <x-icon name="o-clock" class="h-4 w-4 text-warning" />
                <span class="text-sm font-bold uppercase tracking-widest text-warning">{{ __('Pending Requests') }}</span>
                <x-badge value="{{ $totalPending }}" class="badge-warning badge-sm" />
                <div class="flex-1 border-t border-warning/30"></div>
            </div>
            <x-card class="border border-warning/30 shadow-sm">
                <div class="divide-y divide-base-200">
                    @foreach ($this->pendingSubscriptions as $sub)
                        <div class="flex items-center gap-4 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold">{{ $sub->user->first_name }} {{ $sub->user->last_name }}</div>
                                <div class="mt-0.5 text-xs opacity-50">{{ __('New affiliation request') }}</div>
                            </div>
                            <x-button :label="__('Review')" icon="o-check-circle"
                                class="btn-sm btn-warning"
                                wire:click="review({{ $sub->id }})" />
                        </div>
                    @endforeach
                    @foreach ($this->trainingRequests as $sub)
                        <div class="flex items-center gap-4 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold">{{ $sub->user->first_name }} {{ $sub->user->last_name }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    @foreach ($sub->trainingPacks as $pack)
                                        <x-badge value="{{ $pack->name }}" class="badge-warning badge-xs" />
                                    @endforeach
                                </div>
                            </div>
                            <x-button :label="__('Review')" icon="o-academic-cap"
                                class="btn-sm btn-warning"
                                wire:click="reviewTrainingRequest({{ $sub->id }})" />
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>
    @endif

    {{-- ── Vue mobile (list) ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 lg:hidden">
        @forelse ($registrations as $req)
            @php
                $statusBadge = match ($req->status) {
                    'pending'   => ['class' => 'badge-warning badge-soft', 'label' => __('To process')],
                    'confirmed' => ['class' => 'badge-info badge-soft',    'label' => __('Confirmed')],
                    'paid'      => ['class' => 'badge-success badge-soft', 'label' => __('Paid')],
                    'cancelled' => ['class' => 'badge-ghost',              'label' => __('Cancelled')],
                    default     => ['class' => 'badge-ghost',              'label' => $req->status],
                };
            @endphp
            <x-list-item :item="$req" class="bg-base-100 cursor-pointer rounded-lg border"
                wire:key="mobile-reg-{{ $req->id }}"
                wire:click="review({{ $req->id }})">
                <x-slot:value>
                    <span class="font-medium">{{ $req->name }}</span>
                </x-slot:value>
                <x-slot:sub-value>
                    <div class="mt-0.5 flex flex-wrap items-center gap-2">
                        <x-badge :value="$statusBadge['label']" class="{{ $statusBadge['class'] }} badge-sm" />
                        <span class="text-xs text-base-content/40">{{ $req->type }}</span>
                        @if ($req->amount_due > 0)
                            <span class="text-xs font-semibold text-base-content/60">{{ number_format($req->amount_due, 2) }} €</span>
                        @endif
                    </div>
                </x-slot:sub-value>
                <x-slot:actions>
                    <x-button :label="__('Details')" wire:click.stop="review({{ $req->id }})"
                        class="btn-xs btn-ghost" />
                </x-slot:actions>
            </x-list-item>
        @empty
            <x-empty-state
                icon="o-users"
                :heading="__('No registrations found')"
                :message="__('Try adjusting your search or filters.')" />
        @endforelse
    </div>

    {{-- ── Vue desktop (table) ────────────────────────────────────────── --}}
    <div class="hidden lg:block">
        <x-card class="mb-8 shadow-sm">
            @if ($registrations->isEmpty())
                <x-empty-state
                    icon="o-users"
                    :heading="__('No registrations found')"
                    :message="__('Try adjusting your search or filters.')" />
            @else
                <x-table :headers="$headers" :rows="$registrations" hover>
                    @scope('cell_name', $req)
                        <div>
                            <span class="font-bold text-base-content">{{ $req->name }}</span>
                            <div class="hidden text-xs opacity-50 md:block">{{ $req->type }}</div>
                        </div>
                    @endscope

                    @scope('cell_type', $req)
                        <span class="text-xs uppercase tracking-widest opacity-60">{{ $req->type }}</span>
                    @endscope

                    @scope('cell_trainings_count', $req)
                        @if ($req->trainings_count > 0)
                            <span class="text-sm font-semibold">{{ $req->trainings_count }}</span>
                        @else
                            <span class="text-xs opacity-30">—</span>
                        @endif
                    @endscope

                    @scope('cell_amount_due', $req)
                        @if ($req->amount_due > 0)
                            <div>
                                <span class="text-sm font-bold">{{ number_format($req->amount_due, 2) }} €</span>
                                @if ($req->payment_status === 'pending')
                                    <div class="text-xs text-warning opacity-70">{{ __('Awaiting payment') }}</div>
                                @elseif ($req->payment_status === 'paid')
                                    <div class="text-xs text-success opacity-70">{{ __('Paid') }}</div>
                                @endif
                            </div>
                        @else
                            <span class="text-xs opacity-30">—</span>
                        @endif
                    @endscope

                    @scope('cell_status', $req)
                        @php
                            $s = match ($req->status) {
                                'pending'   => ['class' => 'badge-warning badge-soft', 'label' => __('To process')],
                                'confirmed' => ['class' => 'badge-info badge-soft',    'label' => __('Confirmed')],
                                'paid'      => ['class' => 'badge-success badge-soft', 'label' => __('Paid')],
                                'cancelled' => ['class' => 'badge-ghost',              'label' => __('Cancelled')],
                                default     => ['class' => 'badge-ghost',              'label' => $req->status],
                            };
                        @endphp
                        <x-badge :value="$s['label']" class="{{ $s['class'] }} badge-sm" />
                    @endscope

                    @scope('actions', $req)
                        <x-button :label="__('Details')" wire:click="review({{ $req->id }})"
                            class="btn-xs btn-ghost" />
                    @endscope
                </x-table>
            @endif
        </x-card>
    </div>

    {{-- ── Modales et drawers (inchangés) ─────────────────────────────── --}}

    {{-- ── Modal de review (tous statuts) ─────────────────────────────── --}}
    <x-modal wire:model="reviewModal" title="{{ $currentRequest?->name ?? '' }}" separator class="backdrop-blur-sm">

        {{-- Vue lecture seule pour confirmed/paid/cancelled --}}
        @if ($currentRequest && $currentRequest->status !== 'pending' && ! $paymentGenerated)
            <div class="space-y-6">

                <div>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Affiliation') }}</h3>
                    <div class="flex items-center gap-3 rounded-xl border border-base-300 bg-base-200/60 p-3 text-sm">
                        <x-icon name="{{ $currentRequest->type === __('Compétition') ? 'o-trophy' : 'o-heart' }}" class="h-4 w-4 shrink-0 opacity-50" />
                        <span class="flex-1">{{ $currentRequest->type }}</span>
                        @if ($currentRequest->status === 'paid')
                            <x-badge value="{{ __('Paid') }}" class="badge-success badge-sm" />
                        @elseif ($currentRequest->status === 'confirmed')
                            <x-badge value="{{ __('Confirmed') }}" class="badge-info badge-sm" />
                        @elseif ($currentRequest->status === 'cancelled')
                            <x-badge value="{{ __('Cancelled') }}" class="badge-ghost badge-sm" />
                        @endif
                    </div>
                </div>

                @if ($currentRequest->enrolled_packs->count() > 0 || $currentRequest->pending_packs->count() > 0)
                    <div>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Training Packs') }}</h3>
                        <div class="space-y-2">
                            @foreach ($currentRequest->enrolled_packs as $pack)
                                <div class="flex items-center gap-3 rounded-lg border border-base-200 p-2.5 text-sm">
                                    <x-icon name="o-academic-cap" class="h-3.5 w-3.5 shrink-0 text-primary opacity-60" />
                                    <span class="flex-1">{{ $pack->name }}</span>
                                    <x-badge value="{{ __('Enrolled') }}" class="badge-primary badge-xs" />
                                    <span class="text-xs font-semibold opacity-50">{{ number_format((float) $pack->price, 2) }} €</span>
                                    @if (in_array($currentRequest->status, ['confirmed', 'paid']))
                                        <x-button icon="o-arrow-uturn-left" :tooltip="__('Remove & refund')"
                                            class="btn-ghost btn-xs text-error"
                                            wire:click="openRefundModal({{ $currentRequest->id }}, {{ $pack->id }})"
                                            spinner />
                                    @endif
                                </div>
                            @endforeach
                            @foreach ($currentRequest->pending_packs as $pack)
                                <div class="flex items-center gap-3 rounded-lg border border-warning/20 bg-warning/5 p-2.5 text-sm">
                                    <x-icon name="o-academic-cap" class="h-3.5 w-3.5 shrink-0 text-warning opacity-60" />
                                    <span class="flex-1">{{ $pack->name }}</span>
                                    <x-badge value="{{ __('Awaiting validation') }}" class="badge-warning badge-xs" />
                                    <span class="text-xs font-semibold opacity-50">{{ number_format((float) $pack->price, 2) }} €</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($currentRequest->payments))
                    <div>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Payments') }}</h3>
                        <div class="space-y-2">
                            @foreach ($currentRequest->payments as $payment)
                                <div class="flex items-center gap-3 rounded-lg border border-base-200 p-2.5 text-sm">
                                    <x-icon name="o-credit-card" class="h-3.5 w-3.5 shrink-0 opacity-40" />
                                    <span class="flex-1 font-mono text-xs opacity-60">{{ $payment['reference'] }}</span>
                                    <span class="text-sm font-bold">{{ number_format($payment['amount_due'], 2) }} €</span>
                                    @if ($payment['status'] === 'paid')
                                        <x-badge value="{{ __('Paid') }}" class="badge-success badge-xs" />
                                    @else
                                        <x-badge value="{{ __('Pending') }}" class="badge-warning badge-xs" />
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-1 flex justify-between border-t border-base-200 pt-2 text-sm">
                            <span class="opacity-50">{{ __('Total') }}</span>
                            <span class="font-black">{{ number_format($currentRequest->amount_due, 2) }} €</span>
                        </div>
                    </div>
                @endif

            </div>
        @endif

        {{-- En attente : vue approve/reject --}}
        @if (! $paymentGenerated && $currentRequest && $currentRequest->status === 'pending')
            <div class="space-y-6">
                <div>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Members & Licence') }}</h3>
                    <div class="space-y-3">
                        @foreach ($currentRequest->members as $member)
                            <div class="rounded-xl border border-base-300/50 bg-base-200 p-4">
                                <div class="font-bold text-base-content">{{ $member['first_name'] }} {{ $member['last_name'] }}</div>
                                <div class="mt-0.5 text-xs opacity-50">{{ $currentRequest->type }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($currentRequest->pending_packs->count() > 0)
                    <div>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Training Packs Requested') }}</h3>
                        <div class="space-y-2">
                            @foreach ($currentRequest->pending_packs as $pack)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-base-200 p-3 transition-colors hover:border-primary/30 has-checked:border-primary/20 has-checked:bg-primary/5">
                                    <input type="checkbox" wire:model.live="approvedPackIds" value="{{ $pack->id }}"
                                        class="checkbox checkbox-primary checkbox-sm shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-semibold">{{ $pack->name }}</div>
                                        <div class="text-xs opacity-50">{{ number_format((float) $pack->price, 2) }} €</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs italic opacity-40">{{ __('Unchecked packs will be removed from the request.') }}</p>
                    </div>
                @endif

                <div class="overflow-hidden rounded-xl border border-base-200 text-sm">
                    <div class="flex items-center justify-between px-4 py-3">
                        <div class="flex items-center gap-2 opacity-60">
                            <x-icon name="{{ $currentRequest->type === __('Compétition') ? 'o-trophy' : 'o-heart' }}" class="h-3.5 w-3.5 shrink-0" />
                            <span>{{ __('Affiliation') }} · {{ $currentRequest->type }}</span>
                        </div>
                        <span class="font-semibold">{{ number_format($currentRequest->subscription_price, 2) }} €</span>
                    </div>
                    @if (! empty($this->approvedPackIds))
                        <div class="flex items-center justify-between border-t border-base-200 px-4 py-3">
                            <div class="flex items-center gap-2 opacity-60">
                                <x-icon name="o-academic-cap" class="h-3.5 w-3.5 shrink-0" />
                                <span>{{ __('Training packs') }} ({{ count($this->approvedPackIds) }})</span>
                            </div>
                            <span class="font-semibold">{{ number_format($this->pendingReviewEstimatedTotal - $currentRequest->subscription_price, 2) }} €</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between border-t border-base-200 bg-base-200/50 px-4 py-3 font-bold">
                        <span>{{ __('Total if approved') }}</span>
                        <span class="text-primary text-base">{{ number_format($this->pendingReviewEstimatedTotal, 2) }} €</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- Après approbation : paiement généré --}}
        @if ($paymentGenerated && ! empty($paymentData))
            <div class="space-y-6">
                <div class="flex flex-col items-center gap-3">
                    <img src="{{ $paymentData['qr_code'] }}" alt="QR Code" class="h-48 w-48 rounded-xl border border-base-200 shadow" />
                    <p class="text-center text-xs opacity-50">{{ __('Scan this QR code with your banking app') }}</p>
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
                    <div class="flex items-center justify-between border-t border-base-200 pt-1">
                        <span class="font-bold">{{ __('Amount') }}</span>
                        <span class="text-primary text-lg font-black">{{ $paymentData['amount_due'] }} €</span>
                    </div>
                </div>
                <div class="flex gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-xs">
                    <x-icon name="o-exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-warning" />
                    <span class="opacity-80">{{ __('Always include the structured reference when making your transfer so your payment is automatically matched.') }}</span>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-base-300 bg-base-200/50 p-3 text-sm">
                    <x-icon name="o-envelope" class="h-4 w-4 shrink-0 opacity-50" />
                    <span class="flex-1 opacity-70">{{ $paymentData['member_name'] }} &lt;{{ $paymentData['member_email'] }}&gt;</span>
                    @if (($paymentData['invitation_counter'] ?? 0) > 0)
                        <span class="shrink-0 text-xs italic opacity-50">
                            {{ __('Sent :n×', ['n' => $paymentData['invitation_counter']]) }}
                        </span>
                    @endif
                </div>
            </div>
        @endif

        {{-- Raison de refus (uniquement en review pending) --}}
        @if (! $paymentGenerated && $currentRequest && $currentRequest->status === 'pending')
            <div x-data="{ rejectOpen: false }" class="mt-4">
                <button type="button" @click="rejectOpen = !rejectOpen"
                    class="flex items-center gap-1.5 text-xs text-error opacity-60 transition-opacity hover:opacity-100">
                    <x-icon name="o-chevron-down" class="h-3.5 w-3.5 transition-transform" ::class="rejectOpen ? '' : '-rotate-90'" />
                    {{ __('Add a rejection reason') }}
                </button>
                <div x-show="rejectOpen" x-collapse class="mt-3 space-y-3 rounded-xl border border-error/20 bg-error/5 p-4">
                    <div class="mb-2 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Rejection template') }}</div>
                    <div class="space-y-2">
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" wire:model="rejectionTemplate" value="" class="radio radio-xs" />
                            <span class="opacity-70">{{ __('No template — custom message only') }}</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" wire:model="rejectionTemplate" value="level" class="radio radio-error radio-xs" />
                            <span>{{ __('Wrong level (too strong / too weak)') }}</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" wire:model="rejectionTemplate" value="full_teams" class="radio radio-error radio-xs" />
                            <span>{{ __('No spots left in competition teams') }}</span>
                        </label>
                    </div>
                    <textarea wire:model="rejectionMessage"
                        :placeholder="__('Optional personal note to the member...')"
                        class="textarea textarea-bordered textarea-sm w-full text-sm" rows="2"></textarea>
                </div>
            </div>
        @endif

        <x-slot:actions>
            @if (! $paymentGenerated && $currentRequest && $currentRequest->status === 'pending')
                <x-button :label="__('Reject')" wire:click="reject" class="btn-ghost text-error" spinner />
                <x-button :label="__('Approve and Invoice')" wire:click="approve" class="btn-primary shadow-lg" spinner />
            @elseif ($paymentGenerated)
                <x-button :label="__('Close')" @click="$wire.reviewModal = false" class="btn-ghost" />
                <x-button :label="__('Send by email')" icon="o-paper-airplane" class="btn-primary" wire:click="sendPaymentEmail" spinner />
            @else
                <x-button :label="__('Close')" @click="$wire.reviewModal = false" class="btn-ghost" />
            @endif
        </x-slot:actions>
    </x-modal>

    {{-- ── Modal demande d'entraînement (Flux B) ───────────────────────── --}}
    <x-modal wire:model="trainingRequestModal" :title="__('Training Request')" separator class="backdrop-blur-sm">

        @if (! $paymentGenerated && $currentTrainingRequest)
            <div class="space-y-6">
                <div class="flex items-center gap-3 rounded-xl border border-base-300 bg-base-200/60 p-3 text-sm">
                    <x-icon name="o-user" class="h-4 w-4 shrink-0 opacity-50" />
                    <span class="font-semibold">{{ $currentTrainingRequest->user->first_name }} {{ $currentTrainingRequest->user->last_name }}</span>
                    @if ($currentTrainingRequest->status === 'confirmed')
                        <x-badge value="{{ __('Confirmed member') }}" class="badge-info badge-sm ml-auto" />
                    @else
                        <x-badge value="{{ __('Paid member') }}" class="badge-primary badge-sm ml-auto" />
                    @endif
                </div>

                <div>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Packs Requested') }}</h3>
                    @php $bd = $this->trainingRequestPricingBreakdown; @endphp
                    <div class="space-y-2">
                        @foreach ($currentTrainingRequest->trainingPacks as $pack)
                            @php
                                $pb         = ($bd['new_packs'] ?? [])[$pack->id] ?? null;
                                $inApproved = in_array($pack->id, $approvedPackIds);
                                $discounted = $inApproved && ($pb['discounted'] ?? false);
                            @endphp
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-base-200 p-3 transition-colors hover:border-warning/30 has-checked:border-warning/20 has-checked:bg-warning/5">
                                <input type="checkbox" wire:model.live="approvedPackIds" value="{{ $pack->id }}"
                                    class="checkbox checkbox-warning checkbox-sm shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold">{{ $pack->name }}</div>
                                    <div class="mt-0.5 flex items-center gap-1.5">
                                        @if ($discounted)
                                            <span class="text-xs line-through opacity-40">{{ number_format($pb['full_price'], 2) }} €</span>
                                            <span class="text-xs font-bold text-success">{{ number_format($pb['effective_price'], 2) }} €</span>
                                            <x-badge class="badge-soft badge-success badge-xs" value="−10 €" />
                                        @else
                                            <span class="text-xs opacity-50">{{ number_format((float) $pack->price, 2) }} €</span>
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs italic opacity-40">{{ __('Unchecked packs will be removed from the request.') }}</p>

                    @if (! empty($bd['retro_adjustments'] ?? []))
                        <div class="mt-3 space-y-2 rounded-xl border border-info/20 bg-info/5 p-3">
                            <div class="text-info mb-1 flex items-center gap-1.5 text-xs font-semibold">
                                <x-icon name="o-information-circle" class="h-3.5 w-3.5 shrink-0" />
                                {{ __('Multi-pack discount — also applied retroactively') }}
                            </div>
                            @foreach ($bd['retro_adjustments'] as $adj)
                                <div class="flex items-center justify-between text-xs">
                                    <span class="flex-1 truncate pr-3 opacity-60">{{ $adj['name'] }}</span>
                                    <span class="flex shrink-0 items-center gap-1.5">
                                        <span class="line-through opacity-40">{{ number_format($adj['original_price'], 2) }} €</span>
                                        <x-icon name="o-arrow-right" class="h-3 w-3 opacity-30" />
                                        <span class="font-semibold">{{ number_format($adj['new_price'], 2) }} €</span>
                                        <x-badge class="badge-soft badge-info badge-xs" value="−10 €" />
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-3 flex justify-between border-t border-base-200 pt-3 text-sm">
                        <span class="opacity-50">{{ __('Expected additional payment') }}</span>
                        <span class="font-bold text-warning">{{ number_format($this->trainingRequestEstimatedDelta, 2) }} €</span>
                    </div>
                </div>
            </div>
        @endif

        @if ($paymentGenerated && ! empty($paymentData))
            <div class="space-y-6">
                <div class="flex flex-col items-center gap-3">
                    <img src="{{ $paymentData['qr_code'] }}" alt="QR Code" class="h-48 w-48 rounded-xl border border-base-200 shadow" />
                    <p class="text-center text-xs opacity-50">{{ __('Scan this QR code with your banking app') }}</p>
                </div>
                <x-menu-separator />
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="opacity-60">{{ __('Structured reference') }}</span>
                        <span class="font-mono font-bold text-primary">{{ $paymentData['reference'] }}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-base-200 pt-1">
                        <span class="font-bold">{{ __('Amount') }}</span>
                        <span class="text-primary text-lg font-black">{{ $paymentData['amount_due'] }} €</span>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-base-300 bg-base-200/50 p-3 text-sm">
                    <x-icon name="o-envelope" class="h-4 w-4 shrink-0 opacity-50" />
                    <span class="flex-1 opacity-70">{{ $paymentData['member_name'] }} &lt;{{ $paymentData['member_email'] }}&gt;</span>
                </div>
            </div>
        @endif

        @if (! $paymentGenerated && $currentTrainingRequest)
            <div x-data="{ rejectOpen: false }" class="mt-4">
                <button type="button" @click="rejectOpen = !rejectOpen"
                    class="flex items-center gap-1.5 text-xs text-error opacity-60 transition-opacity hover:opacity-100">
                    <x-icon name="o-chevron-down" class="h-3.5 w-3.5 transition-transform" ::class="rejectOpen ? '' : '-rotate-90'" />
                    {{ __('Add a rejection reason') }}
                </button>
                <div x-show="rejectOpen" x-collapse class="mt-3 space-y-3 rounded-xl border border-error/20 bg-error/5 p-4">
                    <div class="mb-2 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Rejection template') }}</div>
                    <div class="space-y-2">
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" wire:model="rejectionTemplate" value="" class="radio radio-xs" />
                            <span class="opacity-70">{{ __('No template — custom message only') }}</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" wire:model="rejectionTemplate" value="level" class="radio radio-error radio-xs" />
                            <span>{{ __('Wrong level (too strong / too weak)') }}</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" wire:model="rejectionTemplate" value="full_teams" class="radio radio-error radio-xs" />
                            <span>{{ __('No spots left in competition teams') }}</span>
                        </label>
                    </div>
                    <textarea wire:model="rejectionMessage"
                        :placeholder="__('Optional personal note to the member...')"
                        class="textarea textarea-bordered textarea-sm w-full text-sm" rows="2"></textarea>
                </div>
            </div>
        @endif

        <x-slot:actions>
            @if (! $paymentGenerated)
                <x-button :label="__('Cancel')" @click="$wire.trainingRequestModal = false" class="btn-ghost" />
                <x-button :label="__('Reject all')" wire:click="rejectTrainingRequest" class="btn-ghost text-error" spinner />
                <x-button :label="__('Approve')" icon="o-check" wire:click="approveTrainingRequest" class="btn-warning shadow-lg" spinner />
            @else
                <x-button :label="__('Close')" @click="$wire.trainingRequestModal = false; $wire.paymentGenerated = false" class="btn-ghost" />
                <x-button :label="__('Send by email')" icon="o-paper-airplane" class="btn-primary" wire:click="sendPaymentEmail" spinner />
            @endif
        </x-slot:actions>
    </x-modal>

    {{-- ── Drawer inscription/renouvellement ───────────────────────────── --}}
    <x-drawer wire:model="memberDrawer" :title="__('Family Registration')" right separator with-close-button class="w-11/12 md:w-5/12">
        <div class="space-y-6">
            <div class="rounded-xl bg-base-200 p-4">
                <x-input :placeholder="__('Search for a member to add to the group...')"
                    wire:model.live.debounce.300ms="searchMember"
                    icon="o-magnifying-glass"
                    :hint="__('Add all family members here')" />

                @if (strlen($searchMember) > 2)
                    <div class="mt-2 rounded-lg border border-base-300 bg-base-100 shadow-lg">
                        @foreach ($membersFound as $m)
                            <div class="flex cursor-pointer items-center justify-between border-b p-3 last:border-none hover:bg-base-200"
                                wire:click="addToBasket({{ $m->id }})">
                                <span class="text-sm font-bold">{{ $m->first_name }} {{ $m->last_name }}</span>
                                <x-icon name="o-plus-circle" class="h-5 w-5 text-primary" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="space-y-4">
                @forelse ($familyBasket as $userId => $config)
                    <div class="relative rounded-2xl border-2 border-base-300 bg-base-100 p-4 shadow-sm">
                        <button wire:click="removeFromBasket({{ $userId }})"
                            class="absolute right-2 top-2 text-error transition-transform hover:scale-110">
                            <x-icon name="o-trash" class="h-4 w-4" />
                        </button>
                        <h3 class="mb-4 flex items-center gap-2 text-xs font-black uppercase text-primary">
                            <x-icon name="o-user" class="h-4 w-4" />
                            {{ $config['name'] }}
                        </h3>
                        <div class="grid grid-cols-1 gap-4">
                            <x-radio :label="__('Licence type')"
                                wire:model="familyBasket.{{ $userId }}.licence_type"
                                :options="[['id' => 'competitive', 'name' => __('Competitive')], ['id' => 'recreative', 'name' => __('Recreational')]]"
                                class="radio-sm" />
                            <x-choices :label="__('Trainings')"
                                wire:model="familyBasket.{{ $userId }}.trainings"
                                :options="$this->trainingOptions()"
                                compact allow-all />
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border-2 border-dashed py-10 text-center italic opacity-40">
                        {{ __('No member selected. Use the search above.') }}
                    </div>
                @endforelse
            </div>
        </div>

        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.memberDrawer = false" />
            @if (count($familyBasket) > 0)
                <x-button :label="__('Validate group registration') . ' (' . count($familyBasket) . ')'"
                    icon="o-check" class="btn-primary"
                    wire:click="saveFamilyRegistration" />
            @endif
        </x-slot:actions>
    </x-drawer>

    {{-- ── Modal remboursement ───────────────────────────────────────── --}}
    @php
        $refundSub  = $refundSubscriptionId ? $this->registrations()->firstWhere('id', $refundSubscriptionId) : null;
        $refundPack = $refundPackId ? $refundSub?->enrolled_packs->firstWhere('id', $refundPackId) : null;
    @endphp
    <x-modal wire:model="refundModal" :title="__('Remove & Refund')" separator class="backdrop-blur-sm">
        @if ($refundPack)
            <div class="space-y-4">
                <p class="text-sm">
                    {{ __('You are about to remove :member from :pack and generate a refund of :amount€.', [
                        'member' => $refundSub?->name ?? '',
                        'pack'   => $refundPack->name,
                        'amount' => number_format((float) $refundPack->price, 2),
                    ]) }}
                </p>
                @php
                    $subModel = $refundSubscriptionId ? App\Models\ClubAdmin\Subscription\Subscription::with('user')->find($refundSubscriptionId) : null;
                    $userIban = $subModel?->user?->iban;
                @endphp
                @if ($userIban)
                    <div class="flex items-center gap-2 rounded-lg border border-success/20 bg-success/10 p-3 text-sm">
                        <x-icon name="o-building-library" class="h-4 w-4 shrink-0 text-success" />
                        <span>{{ __('Refund IBAN:') }} <span class="font-mono font-bold">{{ $userIban }}</span></span>
                    </div>
                @else
                    <div class="flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm">
                        <x-icon name="o-exclamation-triangle" class="h-4 w-4 shrink-0 text-warning" />
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
