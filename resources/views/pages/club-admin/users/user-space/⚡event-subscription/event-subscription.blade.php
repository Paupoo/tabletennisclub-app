<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header separator subtitle="{{ __('Tournaments, dinners, and club meetings') }}"
        title="{{ __('Events and Activities') }}" />

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">

        {{-- ── Sidebar filtres ──────────────────────────────────────────────── --}}
        <div class="space-y-4">

            <x-card class="border border-primary/10 bg-primary/5" shadow title="{{ __('Filters') }}">
                <x-checkbox
                    class="mt-2"
                    label="{{ __('Only upcoming') }}"
                    tight
                    wire:model.live="onlyUpcoming"
                />
            </x-card>

            @php $nextTournament = $this->upcomingTournaments->first(); @endphp
            @if ($nextTournament)
                <x-card class="border border-primary/10 bg-primary/5" shadow>
                    <div class="mb-1 text-[10px] font-bold uppercase tracking-wider opacity-50">
                        {{ __('Next tournament') }}
                    </div>
                    <div class="font-black text-primary">{{ $nextTournament->name }}</div>
                    <div class="mt-0.5 text-xs opacity-70">
                        {{ $nextTournament->start_date->translatedFormat('d M Y') }}
                    </div>
                    @php $reg = $nextTournament->users->first()?->pivot; @endphp
                    @if (! $reg || ! in_array($reg->registration_status, ['registered', 'confirmed', 'spot_offered', 'waiting']))
                        <x-button
                            class="btn-primary btn-xs mt-3"
                            label="{{ __('Quick register') }}"
                            spinner="register"
                            wire:click="register({{ $nextTournament->id }})"
                        />
                    @endif
                </x-card>
            @endif

            {{-- Paiements en attente --}}
            @if ($this->pendingPayments->isNotEmpty())
                <x-card class="border border-warning/30 bg-warning/5" shadow>
                    <div class="mb-3 text-[10px] font-bold uppercase tracking-wider text-warning">
                        {{ __('Payments due') }}
                    </div>
                    <div class="space-y-3">
                        @foreach ($this->pendingPayments as $payment)
                            @php $tournament = $payment->payable->tournament; @endphp
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="truncate text-xs font-semibold">{{ $tournament->name }}</div>
                                    <div class="text-[10px] opacity-60">
                                        {{ number_format($payment->amount_due, 2, ',', ' ') }} €
                                    </div>
                                </div>
                                <x-button
                                    class="btn-warning btn-xs shrink-0"
                                    icon="o-credit-card"
                                    spinner="openPaymentModal"
                                    wire:click="openPaymentModal({{ $payment->id }})"
                                />
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>

        {{-- ── Contenu principal ────────────────────────────────────────────── --}}
        <div class="space-y-6 lg:col-span-3">

            {{-- Section : À venir --}}
            <x-card icon="o-calendar-days" separator shadow title="{{ __('Upcoming Tournaments') }}">

                @forelse ($this->upcomingTournaments as $tournament)
                    @php
                        $reg          = $tournament->users->first()?->pivot;
                        $regStatus    = $reg?->registration_status;
                        $isActive     = in_array($regStatus, ['registered', 'confirmed', 'spot_offered']);
                        $isWaiting    = $regStatus === 'waiting';
                        $isFull       = $tournament->max_users > 0
                            && $tournament->active_registrations_count >= $tournament->max_users;
                        $remaining    = $tournament->max_users > 0
                            ? max(0, $tournament->max_users - $tournament->active_registrations_count)
                            : null;
                    @endphp

                    <x-admin.shared.compact-event-preview
                        :location="null"
                        :remainingSlots="$remaining"
                        :startDateTime="$tournament->start_date->format('Y-m-d H:i:s')"
                        link="#"
                        name="{{ $tournament->name }}"
                        type="tournament"
                    >
                        <x-slot:actions>

                            {{-- Statut inscription --}}
                            @if ($isActive)
                                <x-badge class="badge-success badge-sm" value="{{ __('Registered') }}" />
                                @if ($reg->payment_id && ! $reg->has_paid)
                                    <x-button
                                        class="btn-warning btn-xs"
                                        icon="o-credit-card"
                                        :label="__('Pay')"
                                        spinner="openPaymentModal"
                                        wire:click="openPaymentModal({{ $reg->payment_id }})"
                                    />
                                @endif
                                <x-button
                                    class="btn-ghost btn-sm text-error"
                                    icon="o-x-circle"
                                    label="{{ __('Cancel') }}"
                                    spinner="cancelRegistration"
                                    wire:click="cancelRegistration({{ $tournament->id }})"
                                    wire:confirm="{{ __('Cancel your registration for this tournament?') }}"
                                />
                            @elseif ($isWaiting)
                                <x-badge class="badge-warning badge-sm" value="{{ __('Waitlisted') }}" />
                                <x-button
                                    class="btn-ghost btn-sm text-error"
                                    icon="o-x-circle"
                                    label="{{ __('Leave waitlist') }}"
                                    spinner="cancelRegistration"
                                    wire:click="cancelRegistration({{ $tournament->id }})"
                                    wire:confirm="{{ __('Leave the waitlist for this tournament?') }}"
                                />
                            @elseif ($isFull)
                                <x-badge class="badge-ghost badge-sm" value="{{ __('Full') }}" />
                                <x-button
                                    class="btn-outline btn-sm btn-warning px-4"
                                    label="{{ __('Join waitlist') }}"
                                    spinner="register"
                                    wire:click="register({{ $tournament->id }})"
                                />
                            @else
                                @if ($tournament->price > 0)
                                    <span class="text-xs font-medium text-base-content/60">
                                        {{ number_format((float) $tournament->price, 2, ',', ' ') }} €
                                    </span>
                                @endif
                                <x-button
                                    class="btn-primary btn-outline btn-sm px-6"
                                    label="{{ __('Register') }}"
                                    spinner="register"
                                    wire:click="register({{ $tournament->id }})"
                                />
                            @endif

                        </x-slot:actions>
                    </x-admin.shared.compact-event-preview>

                @empty
                    <div class="flex flex-col items-center py-10 text-base-content/40">
                        <x-icon class="mb-3 h-10 w-10" name="o-calendar" />
                        <p class="text-sm">{{ __('No upcoming tournaments at the moment.') }}</p>
                    </div>
                @endforelse

            </x-card>

            {{-- Section : Mes entraînements ────────────────────────────────── --}}
            @if ($this->upcomingTrainingSessions->isNotEmpty())
                <x-card icon="o-academic-cap" separator shadow title="{{ __('My upcoming sessions') }}">
                    <div class="space-y-2">
                        @foreach ($this->upcomingTrainingSessions as $session)
                            <div class="flex items-center justify-between rounded-lg border border-base-200 px-3 py-2">
                                <div class="flex items-center gap-3">
                                    <div class="text-center">
                                        <div class="text-[10px] font-bold uppercase text-base-content/40">
                                            {{ $session->start->translatedFormat('M') }}
                                        </div>
                                        <div class="text-lg font-bold leading-none">
                                            {{ $session->start->format('d') }}
                                        </div>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium">{{ $session->trainingPack?->name }}</p>
                                        <p class="text-xs text-base-content/60">
                                            {{ $session->start->format('H:i') }} – {{ $session->end->format('H:i') }}
                                            · {{ $session->room?->name }}
                                        </p>
                                    </div>
                                </div>
                                <x-badge value="{{ $session->trainingPack?->level?->value }}"
                                    class="badge-primary badge-soft badge-sm" />
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            {{-- Section : Passés (collapse) --}}
            @if ($this->myPastTournaments->isNotEmpty())
                <x-collapse>
                    <x-slot:heading>
                        <div class="text-sm font-bold opacity-40">
                            {{ __('Past tournaments') }}
                            <span class="ml-1 font-normal">({{ $this->myPastTournaments->count() }})</span>
                        </div>
                    </x-slot:heading>
                    <x-slot:content>
                        <div class="space-y-1 opacity-60">
                            @foreach ($this->myPastTournaments as $tournament)
                                <div class="flex items-center justify-between border-b border-dashed py-2 text-sm">
                                    <span class="font-medium">{{ $tournament->name }}</span>
                                    <span class="text-xs text-base-content/60">
                                        {{ $tournament->start_date->translatedFormat('d M Y') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </x-slot:content>
                </x-collapse>
            @endif

        </div>
    </div>

    {{-- Modal détails paiement --}}
    <x-modal wire:model="paymentModal" :title="__('Payment details')" box-class="max-w-sm">
    @if ($paymentQr && $selectedPaymentId)
        @php
            $payment = \App\Models\ClubAdmin\Payment\Payment::with(['payable.tournament'])->find($selectedPaymentId);
            $eventName = $payment?->payable?->tournament?->name;
        @endphp
        <div class="flex flex-col items-center gap-5">
            @if ($eventName)
                <div class="w-full rounded-xl bg-primary/5 border border-primary/10 px-4 py-3 text-center">
                    <div class="text-[10px] font-bold uppercase tracking-wider opacity-50 mb-0.5">{{ __('Tournament') }}</div>
                    <div class="font-bold text-sm text-primary">{{ $eventName }}</div>
                </div>
            @endif
            <img
                alt="QR Code"
                class="w-48 h-48 rounded-xl border border-base-200 shadow"
                src="{{ $paymentQr }}"
            />
            <div class="w-full divide-y divide-base-200 text-sm">
                <div class="flex items-center justify-between py-2">
                    <span class="opacity-60">{{ __('Amount') }}</span>
                    <span class="font-bold">{{ number_format($payment->amount_due, 2, ',', ' ') }} €</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="opacity-60">{{ __('Reference') }}</span>
                    <span class="font-mono text-xs">{{ $payment->reference }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="opacity-60">IBAN</span>
                    <span class="font-mono text-xs">BE23 7323 3320 8791</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="opacity-60">BIC</span>
                    <span class="font-mono text-xs">CREGBEBB</span>
                </div>
            </div>
        </div>
    @endif
    <x-slot:actions>
        <x-button :label="__('Close')" wire:click="$set('paymentModal', false)" />
    </x-slot:actions>
    </x-modal>
</div>
