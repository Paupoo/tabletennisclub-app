<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header separator :subtitle="__('Tournaments, dinners, and club meetings')"
        :title="__('Events and Activities')" />

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">

        {{-- ── Sidebar filtres ──────────────────────────────────────────────── --}}
        <div class="space-y-4">

            <x-card class="border border-primary/10 bg-primary/5" shadow :title="__('Filters')">
                <x-checkbox
                    class="mt-2"
                    :label="__('Only upcoming')"
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
                            :label="__('Quick register')"
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
                            @php
                                $eventName = $payment->payable?->tournament?->name ?? $payment->payable?->meeting?->title;
                            @endphp
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="truncate text-xs font-semibold">{{ $eventName }}</div>
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
            <x-card icon="o-calendar-days" separator shadow :title="__('Upcoming Tournaments')">

                @forelse ($this->upcomingTournaments as $tournament)
                    @php
                        $reg           = $tournament->users->first()?->pivot;
                        $regStatus     = $reg?->registration_status;
                        $isActive      = in_array($regStatus, ['registered', 'confirmed']);
                        $isSpotOffered = $regStatus === 'spot_offered';
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

                                {{-- Doubles self-pair --}}
                                @if ($tournament->match_type === 'double' && $tournament->doubles_registration_mode === 'self')
                                    @php $myPair = $tournament->pairs->first(); @endphp
                                    @if ($myPair)
                                        <x-badge class="badge-info badge-sm" icon="o-user-group"
                                            value="{{ $myPair->player1_id === $this->user->id ? $myPair->player2?->full_name : $myPair->player1?->full_name }}" />
                                        <x-button class="btn-ghost btn-xs text-error" icon="o-x-mark"
                                            :tooltip="__('Remove pair')"
                                            wire:click="removeFromPair({{ $tournament->id }})" />
                                    @elseif ($partnerTournamentId === $tournament->id)
                                        <x-select wire:model.live="selectedPartnerId"
                                            :options="$this->availablePartners"
                                            :placeholder="__('Choose partner…')"
                                            class="select-xs max-w-40" />
                                        <x-button class="btn-primary btn-xs" icon="o-user-group"
                                            :label="__('Confirm')"
                                            wire:click="registerAsPair({{ $tournament->id }})"
                                            :disabled="! $selectedPartnerId" />
                                        <x-button class="btn-ghost btn-xs" icon="o-x-mark"
                                            wire:click="$set('partnerTournamentId', 0)" />
                                    @else
                                        <x-button class="btn-outline btn-xs" icon="o-user-group"
                                            :label="__('Choose partner')"
                                            wire:click="openPartnerSelect({{ $tournament->id }})" />
                                    @endif
                                @endif

                                <x-button
                                    class="btn-ghost btn-xs text-error/70"
                                    icon="o-x-mark"
                                    :tooltip="__('Cancel registration')"
                                    spinner="cancelRegistration"
                                    wire:click="openCancelConfirm({{ $tournament->id }})"
                                />
                            @elseif ($isSpotOffered)
                                @if ($reg->confirmation_deadline)
                                    <span class="hidden text-[10px] text-base-content/40 sm:inline">
                                        {{ $reg->confirmation_deadline->format('d/m H:i') }}
                                    </span>
                                @endif
                                <span class="flex items-center gap-1.5 text-xs font-semibold text-success">
                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-success"></span>
                                    <span class="hidden sm:inline">{{ __('Confirm attendance') }}</span>
                                </span>
                                <x-button
                                    class="btn-success btn-xs"
                                    icon="o-check"
                                    :label="__('Confirm')"
                                    spinner="confirmTournamentSpot"
                                    wire:click="confirmTournamentSpot({{ $tournament->id }})"
                                />
                                <x-button
                                    class="btn-ghost btn-xs text-error/70"
                                    icon="o-x-mark"
                                    :tooltip="__('Decline this spot')"
                                    spinner="cancelRegistration"
                                    wire:click="openCancelConfirm({{ $tournament->id }})"
                                />
                            @elseif ($isWaiting)
                                <x-badge class="badge-warning badge-soft badge-sm" value="{{ __('Waitlist') }}{{ $reg->waitlist_position ? ' #' . $reg->waitlist_position : '' }}" />
                                <x-button
                                    class="btn-ghost btn-xs text-error/70"
                                    icon="o-x-mark"
                                    :tooltip="__('Leave waitlist')"
                                    spinner="cancelRegistration"
                                    wire:click="openCancelConfirm({{ $tournament->id }})"
                                />
                            @elseif ($isFull)
                                <x-badge class="badge-ghost badge-sm" value="{{ __('Full') }}" />
                                <x-button
                                    class="btn-outline btn-sm btn-warning px-4"
                                    :label="__('Join waitlist')"
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
                                    :label="__('Register')"
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

            {{-- Section : Mes réunions ──────────────────────────────────── --}}
            @if ($this->upcomingMeetings->isNotEmpty())
                <x-card icon="o-calendar-days" separator shadow :title="__('Upcoming Meetings')">
                    @forelse ($this->upcomingMeetings as $meeting)
                        <x-admin.shared.compact-event-preview
                            :location="$meeting->format === \App\Domains\Shared\Enums\MeetingFormatEnum::PHYSICAL ? $meeting->location : null"
                            :startDateTime="$meeting->scheduled_at->format('Y-m-d H:i:s')"
                            link="#"
                            name="{{ $meeting->title }}"
                            type="meeting"
                        >
                            <x-slot:actions>
                                @php
                                    $reg        = $this->meetingRegistrations[$meeting->id] ?? null;
                                    $payment    = $reg?->payment;
                                    $statusEnum = $reg?->status ?? \App\Domains\Shared\Enums\MeetingUserStatusEnum::INVITED;
                                    $mealPaid   = $payment && ($payment->amount_paid > 0 || $payment->status !== 'pending');
                                @endphp
                                <x-badge :value="$statusEnum->getLabel()" class="{{ $statusEnum->getBadgeClass() }} badge-sm" />

                                @if ($meeting->has_meal)
                                    @if ($reg?->meal_reserved === true && $mealPaid)
                                        <x-badge :value="__('Meal · paid')" class="badge-success badge-sm" />
                                    @elseif ($reg?->meal_reserved === true)
                                        <x-badge :value="__('Meal · pending')" class="badge-warning badge-soft badge-sm" />
                                    @elseif ($reg?->meal_reserved === false)
                                        <x-badge :value="__('No meal')" class="badge-ghost badge-sm" />
                                    @endif
                                @endif

                                @if ($payment && $payment->status === 'pending')
                                    <x-button
                                        class="btn-warning btn-xs"
                                        icon="o-credit-card"
                                        :label="__('Pay')"
                                        spinner="openPaymentModal"
                                        wire:click="openPaymentModal({{ $payment->id }})"
                                    />
                                @endif

                                <x-button
                                    class="btn-ghost btn-xs"
                                    icon="o-pencil-square"
                                    :label="__('Manage')"
                                    wire:click="openMeetingRsvp({{ $meeting->id }})"
                                />
                            </x-slot:actions>
                        </x-admin.shared.compact-event-preview>
                    @empty
                    @endforelse
                </x-card>
            @endif

            {{-- Section : Mes entraînements ────────────────────────────────── --}}
            @if ($this->upcomingTrainingSessions->isNotEmpty())
                <x-card icon="o-academic-cap" separator shadow :title="__('My upcoming sessions')">
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
            $payment = \App\Domains\ClubAdmin\Payment\Models\Payment::find($selectedPaymentId);
            $isMeeting = $payment?->payable instanceof \App\Domains\Meetings\Models\MeetingUser;
            $eventName = $isMeeting ? $payment?->payable?->meeting?->title : $payment?->payable?->tournament?->name;
            $eventType = $isMeeting ? __('Meeting') : __('Tournament');
        @endphp
        <div class="flex flex-col items-center gap-5">
            @if ($eventName)
                <div class="w-full rounded-xl bg-primary/5 border border-primary/10 px-4 py-3 text-center">
                    <div class="text-[10px] font-bold uppercase tracking-wider opacity-50 mb-0.5">{{ $eventType }}</div>
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
                    <span class="font-mono text-xs">{{ $this->ourClub->bank_account }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="opacity-60">BIC</span>
                    <span class="font-mono text-xs">{{ $this->ourClub->bic }}</span>
                </div>
            </div>
        </div>
    @endif
    <x-slot:actions>
        <x-button :label="__('Close')" wire:click="$set('paymentModal', false)" />
    </x-slot:actions>
    </x-modal>

    {{-- Modal participation réunion --}}
    <x-modal wire:model="meetingRsvpModal" :title="__('My participation')" box-class="max-w-sm">
        @php $rsvpMeeting = $this->rsvpMeetingId ? \App\Domains\Meetings\Models\Meeting::find($this->rsvpMeetingId) : null; @endphp
        @if ($rsvpMeeting)
            @php $rsvpReg = $this->meetingRegistrations[$rsvpMeeting->id] ?? null; @endphp
            <div class="space-y-4">
                <p class="text-sm font-semibold">{{ $rsvpMeeting->title }}</p>

                <x-radio
                    :label="__('Will you attend?')"
                    wire:model="rsvpAttendance"
                    :options="[
                        ['id' => 'confirmed', 'name' => __('Yes, I will attend')],
                        ['id' => 'declined', 'name' => __('No, I cannot attend')],
                    ]"
                    class="radio-sm" />

                @if ($rsvpMeeting->has_meal)
                    <div x-show="$wire.rsvpAttendance === 'confirmed'"
                        class="rounded-xl border border-warning/30 bg-warning/5 p-3 space-y-2">
                        <div class="flex items-center gap-2 text-sm font-semibold">
                            <x-icon name="o-cake" class="h-4 w-4 text-warning" />
                            {{ __('Meal') }}
                            @if ($rsvpMeeting->meal_price)
                                <span class="text-base-content/60">— {{ number_format($rsvpMeeting->meal_price, 2) }} €</span>
                            @endif
                        </div>
                        @if ($rsvpMeeting->meal_description)
                            <p class="text-xs text-base-content/70">{{ $rsvpMeeting->meal_description }}</p>
                        @endif

                        @if ($rsvpReg?->mealPaymentLocked())
                            <div class="flex items-center gap-2 text-xs text-base-content/70">
                                <x-icon name="o-lock-closed" class="h-4 w-4 text-base-content/40 shrink-0" />
                                {{ __('Meal already paid — contact the organizer to change it.') }}
                            </div>
                        @else
                            <x-radio
                                wire:model="rsvpMeal"
                                :options="[
                                    ['id' => 'reserve', 'name' => __('Reserve the meal')],
                                    ['id' => 'skip', 'name' => __('I will skip the meal')],
                                ]"
                                class="radio-sm" />
                        @endif
                    </div>
                @endif
            </div>

            <x-slot:actions>
                <x-button :label="__('Cancel')" wire:click="$set('meetingRsvpModal', false)" />
                <x-button :label="__('Save')" icon="o-check" class="btn-primary"
                    wire:click="saveMeetingRsvp" spinner="saveMeetingRsvp" />
            </x-slot:actions>
        @endif
    </x-modal>

    <x-confirm-modal model="cancelConfirmModal" :title="__('Cancel registration?')"
        :confirmLabel="__('Confirm')" confirmClass="btn-error" confirmAction="confirmCancel">
        <p>{{ __('This will remove you from the tournament. This action is irreversible.') }}</p>
    </x-confirm-modal>
</div>
