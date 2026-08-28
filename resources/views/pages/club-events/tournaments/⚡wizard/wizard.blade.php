<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator :title="__('Tournament Setup Assistant')"
        :subtitle="__('Configure and manage your tournament')">
        <x-slot:actions>
            {{-- Cancel button — always accessible when tournament exists and not already cancelled --}}
            @if ($tournamentId && !in_array($this->currentTournament?->status?->value, ['cancelled', null]))
                <x-button
                    :label="__('Cancel tournament')"
                    icon="o-x-circle"
                    class="btn-error btn-outline btn-sm"
                    wire:click="$set('showCancelModal', true)" />
            @endif
        </x-slot:actions>
    </x-header>

    <div class="max-w-6xl mx-auto pb-20">

        {{-- ── Metro-line navigation ────────────────────────────────────────── --}}
        @php
            $currentStep = (int) $step;
            $tournamentStatus = $this->currentTournament?->status?->value;

            // Maximum reachable step based on tournament status (independent of $step position).
            $maxReachable = match(true) {
                in_array($tournamentStatus, ['setup', 'pending', 'closed'])   => 6,
                in_array($tournamentStatus, ['published'])                    => 5,
                in_array($tournamentStatus, ['locked'])                       => 4,
                $tournamentId !== null                                        => 3,
                default                                                       => 1,
            };

            $wizardSteps = [
                1 => ['label' => __('Configuration'), 'icon' => 'o-cog-6-tooth'],
                2 => ['label' => __('Web event'),      'icon' => 'o-globe-alt'],
                3 => ['label' => __('Validation'),     'icon' => 'o-lock-closed'],
                4 => ['label' => __('Invitations'),    'icon' => 'o-envelope'],
                5 => ['label' => __('Registrations'),  'icon' => 'o-users'],
                6 => ['label' => __('Launch'),         'icon' => 'o-rocket-launch'],
            ];
        @endphp

        <div class="flex items-center gap-0 mb-8 overflow-x-auto">
            @foreach ($wizardSteps as $num => $info)
                @php $reachable = $num <= $maxReachable; @endphp

                {{-- Step button --}}
                <button
                    wire:click="{{ $reachable ? "\$set('step', '{$num}')" : 'null' }}"
                    @class([
                        'flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap',
                        'bg-primary text-primary-content shadow'                                         => $num === $currentStep,
                        'text-base-content/70 hover:bg-base-200 hover:text-base-content cursor-pointer' => $reachable && $num !== $currentStep,
                        'text-base-content/30 cursor-not-allowed'                                        => !$reachable,
                    ])
                >
                    <x-icon name="{{ $info['icon'] }}" class="w-4 h-4 shrink-0" />
                    <span>{{ $info['label'] }}</span>
                    @if ($reachable && $num < $currentStep)
                        <x-icon name="o-check-circle" class="w-3.5 h-3.5 text-success shrink-0" />
                    @endif
                </button>

                {{-- Separator --}}
                @if ($num < 6)
                    <x-icon name="o-chevron-right" class="w-4 h-4 text-base-content/15 shrink-0 mx-0.5" />
                @endif

            @endforeach
        </div>

        {{-- ── Step content ──────────────────────────────────────────────────── --}}
        @if ($step == '1')
            @include('admin.club-events.tournaments.partials.steps.setup')
        @elseif ($step == '2')
            @include('admin.club-events.tournaments.partials.steps.article')
        @elseif ($step == '3')
            @include('admin.club-events.tournaments.partials.steps.validate')
        @elseif ($step == '4')
            @include('admin.club-events.tournaments.partials.steps.invitations')
        @elseif ($step == '5')
            @include('admin.club-events.tournaments.partials.steps.registrations')
        @elseif ($step == '6')
            @include('admin.club-events.tournaments.partials.steps.start')
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
