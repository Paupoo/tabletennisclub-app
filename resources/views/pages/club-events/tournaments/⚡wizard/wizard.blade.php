<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    {{--
        La fiche d'un tournoi, côté préparation : un bandeau qui dit où en est le
        tournoi et ce qu'il attend, quatre phases, et dans chaque phase une
        section principale avec les autres résumées en cartes au-dessus. Le jour J
        lancé et le bilan vivent à la régie, sous le même bandeau.

        Un tournoi pas encore enregistré n'a ni bandeau ni phases : seulement ses
        réglages.
    --}}
    @php $tournament = $this->currentTournament; @endphp

    <div class="mx-auto max-w-6xl pb-20">

        @if ($tournament)
            @include('components.admin.club-events.tournaments.partials.shared.tournament-header', [
                'tournament' => $tournament,
                'shownPhase' => $this->shownPhase,
                'banner' => $this->banner,
            ])
        @else
            <x-header progress-indicator :title="__('New tournament')" />
        @endif

        @php
            $isDouble = $tournament?->match_type === 'double';
            $board = $isDouble ? $this->pairBoard : ['pairs' => [], 'bench' => []];
            $benchCount = count($board['bench']);
            $benchAlert = $benchCount > 0
                ? trans_choice(':count player has no partner|:count players have no partner', $benchCount, ['count' => $benchCount])
                : null;
            $onMatchDay = $tournament && \App\Domains\Shared\Enums\TournamentPhaseEnum::activeFor($tournament) === \App\Domains\Shared\Enums\TournamentPhaseEnum::MATCH_DAY;
        @endphp

        @if (! $tournament)
            @include('admin.club-events.tournaments.partials.steps.setup')

        @elseif (! $this->shownPhaseIsOpen)
            <p class="rounded-xl border border-dashed border-base-300 py-12 text-center text-base-content/70" data-phase-not-open>
                {{ $this->shownPhase->opensWhen() }}
            </p>

        {{-- ── Préparation ─────────────────────────────────────────────── --}}
        @elseif ($step === '1')
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" data-summary-cards>
                @include('components.admin.club-events.tournaments.partials.shared.summary-card', [
                    'key' => 'web-page',
                    'title' => __('Web page'),
                    'value' => $eventPostId === null ? __('Not created yet') : ($eventStatus === 'PUBLISHED' ? __('Online') : __('Not online')),
                    'caption' => null,
                    'alert' => null,
                    'button' => __('Edit the web page'),
                    'wire' => "\$set('step', '2')",
                ])
            </div>
            @include('admin.club-events.tournaments.partials.steps.setup')

        @elseif ($step === '2')
            <x-button :label="__('Back to the settings')" icon="o-arrow-left" class="btn-ghost btn-sm" wire:click="$set('step', '1')" data-back />
            @include('admin.club-events.tournaments.partials.steps.article')

        {{-- ── Inscriptions ────────────────────────────────────────────── --}}
        @elseif ($step === '5')
            @php $invited = collect($this->invitationHistory)->sum('count'); @endphp
            <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" data-summary-cards>
                @include('components.admin.club-events.tournaments.partials.shared.summary-card', [
                    'key' => 'invitations',
                    'title' => __('Invitations'),
                    'value' => $invited,
                    'caption' => trans_choice('invitation sent|invitations sent', $invited),
                    'alert' => null,
                    'button' => __('Invite members'),
                    'wire' => "\$set('step', '4')",
                ])
                @if ($isDouble)
                    @include('components.admin.club-events.tournaments.partials.shared.summary-card', [
                        'key' => 'pairs',
                        'title' => __('Pairs'),
                        'value' => count($board['pairs']),
                        'caption' => trans_choice('pair|pairs', count($board['pairs'])),
                        'alert' => $benchAlert,
                        'button' => __('Compose the pairs'),
                        'wire' => "\$set('step', 'pairs')",
                    ])
                @endif
            </div>
            @include('admin.club-events.tournaments.partials.steps.registrations', ['listMode' => 'registrations'])

        @elseif ($step === '4')
            <x-button :label="__('Back to the registrations')" icon="o-arrow-left" class="btn-ghost btn-sm" wire:click="$set('step', '5')" data-back />
            @include('admin.club-events.tournaments.partials.steps.invitations')

        @elseif ($step === 'pairs')
            <x-button :label="$onMatchDay ? __('Back to the roll call') : __('Back to the registrations')" icon="o-arrow-left"
                class="btn-ghost btn-sm" wire:click="$set('step', '{{ $onMatchDay ? '6' : '5' }}')" data-back />
            @include('admin.club-events.tournaments.partials.steps.pairs')

        {{-- ── Jour J, avant le lancement ──────────────────────────────── --}}
        @elseif ($step === '6')
            <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" data-summary-cards>
                @if ($isDouble)
                    @include('components.admin.club-events.tournaments.partials.shared.summary-card', [
                        'key' => 'pairs',
                        'title' => __('Pairs of the day'),
                        'value' => count($board['pairs']),
                        'caption' => trans_choice('pair|pairs', count($board['pairs'])),
                        'alert' => $benchAlert,
                        'button' => __('Rework the pairs'),
                        'wire' => "\$set('step', 'pairs')",
                    ])
                @endif
                @include('components.admin.club-events.tournaments.partials.shared.summary-card', [
                    'key' => 'launch',
                    'title' => __('Launch preparation'),
                    'value' => $this->poolsGenerated ? __('Pools drawn') : __('Pools to draw'),
                    'caption' => null,
                    'alert' => $this->poolsStale ? __('Draw the pools again.') : null,
                    'button' => __('Prepare the launch'),
                    'wire' => "\$set('step', 'launch')",
                ])
            </div>
            @include('admin.club-events.tournaments.partials.steps.registrations', ['listMode' => 'checkin'])

        @elseif ($step === 'launch')
            <x-button :label="__('Back to the roll call')" icon="o-arrow-left" class="btn-ghost btn-sm" wire:click="$set('step', '6')" data-back />
            @include('admin.club-events.tournaments.partials.steps.start')

        @else
            <p class="rounded-xl border border-dashed border-base-300 py-12 text-center text-base-content/70" data-phase-not-open>
                {{ $this->shownPhase->opensWhen() }}
            </p>
        @endif

    </div>

    {{-- Drawer bulk actions registrations --}}
    @include('admin.club-events.tournaments.partials.drawers.bulk-registrations')

    {{-- Modal d'invitation --}}
    @include('admin.club-events.tournaments.partials.modals.invite')

    {{-- Modal de lancement du tournoi --}}
    @include('admin.club-events.tournaments.partials.modals.launch')

    {{-- Ouverture des inscriptions — au niveau du wizard, parce que l'étape 4
         la propose pour un tournoi jamais ouvert et l'étape 5 pour une
         réouverture : un modal par étape en aurait fait deux à maintenir. --}}
    <x-app-modal wire:model="showOpenRegistrationsModal"
        :title="$this->registrationClosed ? __('Reopen registrations?') : __('Open registrations?')"
        class="backdrop-blur" :open="$showOpenRegistrationsModal">
        <div class="flex items-start gap-3 rounded-xl border border-warning/20 bg-warning/10 p-4 text-sm">
            <x-icon name="o-information-circle" class="mt-0.5 h-5 w-5 shrink-0 text-warning-content" />
            <p>
                @if ($this->registrationClosed)
                    {{ __('Reopening registrations will set the tournament back to "published" status. The tournament cannot be started until registrations are closed again.') }}
                @else
                    {{ __('The tournament becomes visible to members, who can sign up from their own space. Name and price stay locked.') }}
                @endif
            </p>
        </div>

        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('showOpenRegistrationsModal', false)" />
            <x-button
                :label="$this->registrationClosed ? __('Reopen registrations') : __('Open registrations')"
                icon="o-lock-open"
                :class="$this->registrationClosed ? 'btn-warning' : 'btn-primary'"
                wire:click="confirmOpenRegistrations" spinner />
        </x-slot:actions>
    </x-app-modal>

    {{-- Plafond relevé : les offres partent à l'enregistrement, et
         `suggestMaxUsers()` peut relever le plafond sans que le champ ait été
         touché. Un simple avertissement sous le champ ne suffirait pas. --}}
    <x-app-modal wire:model="capacityModal" :title="__('Call the waiting list?')" class="backdrop-blur"
        :open="$capacityModal">
        <div class="space-y-4">
            <x-alert
                :title="__(':count people on the waiting list will be offered a spot.', ['count' => $this->pendingWaitlistOffers])"
                :description="__('Each of them gets 48 hours to confirm. The emails go out as soon as you save.')"
                icon="o-envelope"
                class="alert-warning alert-soft" />
            <p class="text-sm text-base-content/70">
                {{ __('Maximum number of players') }} : {{ $maxUsers > 0 ? $maxUsers : __('No limit') }}
            </p>
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('capacityModal', false)" />
            <x-button :label="__('Save and send the offers')" icon="o-paper-airplane" class="btn-primary"
                wire:click="confirmCapacityChange" spinner="confirmCapacityChange" />
        </x-slot:actions>
    </x-app-modal>

{{-- ── Close registrations modal ─────────────────────────────── --}}
    <x-app-modal wire:model="showCloseRegistrationsModal" :title="__('Close registrations?')" class="backdrop-blur" :open="$showCloseRegistrationsModal">
        <div class="space-y-4">
            <div class="flex items-start gap-3 p-4 bg-error/10 border border-error/20 rounded-xl text-sm text-error">
                <x-icon name="o-exclamation-triangle" class="w-5 h-5 shrink-0 mt-0.5" />
                <div class="space-y-1">
                    <p class="font-semibold">{{ __('This action is irreversible.') }}</p>
                    <p>{{ __('All players currently on the waiting list will be removed and notified by email. They will not automatically regain their position if registrations are reopened.') }}</p>
                </div>
            </div>
    
            @php $waitlistCount = collect($this->waitlist ?? [])->count(); @endphp
            @if ($waitlistCount > 0)
                <p class="text-sm text-muted">
                    {{ trans_choice('1 person will be removed from the waitlist.|:count people will be removed from the waitlist.', $waitlistCount, ['count' => $waitlistCount]) }}
                </p>
            @else
                <p class="text-sm text-gray-500">{{ __('The waiting list is currently empty.') }}</p>
            @endif
        </div>
    
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('showCloseRegistrationsModal', false)" />
            <x-button :label="__('Close registrations')" icon="o-lock-closed" class="btn-error"
                wire:click="confirmCloseRegistrations" />
        </x-slot:actions>
    </x-app-modal>

    {{-- Valider : le nom et le prix se figent. L'action du bandeau en Préparation. --}}
    <x-app-modal wire:model="showValidateModal" :title="__('Validate the tournament?')" class="backdrop-blur" :open="$showValidateModal">
        <div class="space-y-3 text-sm">
            <p>{{ __('After validation: name and price cannot be changed') }}</p>
            <ul class="space-y-1">
                <li><span class="text-base-content/70">{{ __('Tournament') }} :</span> <b>{{ $name }}</b></li>
                <li><span class="text-base-content/70">{{ __('Entry fee') }} :</span> <b>{{ $price > 0 ? number_format($price, 2, ',', ' ') . ' €' : __('Free') }}</b></li>
                <li><span class="text-base-content/70">{{ __('Registration deadline') }} :</span>
                    <b>{{ $registration_deadline !== '' ? \Carbon\Carbon::parse($registration_deadline)->isoFormat('dddd D MMMM') : __('Not set — required!') }}</b></li>
            </ul>
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('showValidateModal', false)" />
            <x-button :label="__('Validate')" icon="o-lock-closed" class="btn-primary"
                wire:click="saveAndValidate" spinner="saveAndValidate" :disabled="$registration_deadline === ''" />
        </x-slot:actions>
    </x-app-modal>

    {{-- Cancel confirmation modal --}}
    <x-app-modal wire:model="showCancelModal" :title="__('Cancel tournament')" class="backdrop-blur" :open="$showCancelModal">
        <div class="space-y-4">
            <x-alert
                :title="__('This action cannot be undone')"
                :description="__('All registered and waitlisted players will receive a cancellation notification.')"
                icon="o-exclamation-triangle"
                class="alert-error alert-soft" />
            <p class="text-sm text-base-content/70">
                {{ __('Are you sure you want to cancel :name?', ['name' => $name]) }}
            </p>
        </div>
        <x-slot:actions>
            <x-button :label="__('Keep tournament')" wire:click="$set('showCancelModal', false)" />
            <x-button :label="__('Yes, cancel it')" icon="o-x-circle" class="btn-error"
                wire:click="cancelTournament" spinner="cancelTournament" />
        </x-slot:actions>
    </x-app-modal>

</div>
