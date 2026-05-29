<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header :title="$meetingId ? __('Edit meeting') : __('New meeting')"
        :subtitle="__('Configure your meeting step by step')">
        <x-slot:actions>
            @if ($meetingId && $this->currentMeeting?->status !== \App\Enums\MeetingStatusEnum::CANCELLED)
                <x-button :label="__('Cancel meeting')" icon="o-x-circle"
                    class="btn-error btn-outline btn-sm"
                    wire:click="$set('showCancelModal', true)" />
            @endif
        </x-slot:actions>
    </x-header>

    <div class="mx-auto max-w-3xl pb-20">

        {{-- ── Step navigator dynamique ────────────────────────────── --}}
        @php
            $visibleSteps = $datePollMode
                ? [
                    1 => ['label' => __('Info'),    'icon' => 'o-information-circle'],
                    2 => ['label' => __('Agenda'),  'icon' => 'o-list-bullet'],
                    3 => ['label' => __('Poll'),    'icon' => 'o-calendar'],
                    4 => ['label' => __('Review'),  'icon' => 'o-check-circle'],
                ]
                : [
                    1 => ['label' => __('Info'),    'icon' => 'o-information-circle'],
                    2 => ['label' => __('Agenda'),  'icon' => 'o-list-bullet'],
                    4 => ['label' => __('Review'),  'icon' => 'o-check-circle'],
                ];
            $stepKeys = array_keys($visibleSteps);
        @endphp
        <div class="mb-8 flex items-center gap-0 overflow-x-auto">
            @foreach ($visibleSteps as $num => $info)
                @php
                    $isCurrent  = (int) $step === $num;
                    $isPast     = (int) $step > $num;
                    $reachable  = $num <= $this->maxReachableStep;
                @endphp
                <button
                    wire:click="{{ $reachable ? "\$set('step', '{$num}')" : 'null' }}"
                    @class([
                        'flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap',
                        'bg-primary text-primary-content shadow'                                         => $isCurrent,
                        'text-base-content/70 hover:bg-base-200 hover:text-base-content cursor-pointer' => $reachable && ! $isCurrent,
                        'text-base-content/30 cursor-not-allowed'                                        => ! $reachable,
                    ])>
                    <x-icon name="{{ $info['icon'] }}" class="h-4 w-4 shrink-0" />
                    <span>{{ $info['label'] }}</span>
                    @if ($isPast && $reachable)
                        <x-icon name="o-check-circle" class="h-3.5 w-3.5 text-success shrink-0" />
                    @endif
                </button>
                @if (! $loop->last)
                    <x-icon name="o-chevron-right" class="mx-0.5 h-4 w-4 shrink-0 text-base-content/15" />
                @endif
            @endforeach
        </div>

        {{-- ── Step 1 — Basic info ──────────────────────────────────── --}}
        @if ($step == '1')
        <x-card>
            <div class="space-y-5">
                {{-- ── Mode toggle ────────────────────────────────── --}}
                <div class="rounded-xl border border-base-200 bg-base-50 p-1">
                    <div class="grid grid-cols-2 gap-1">
                        <button type="button"
                            wire:click="$set('datePollMode', false)"
                            @class([
                                'flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all',
                                'bg-base-100 shadow text-base-content' => ! $datePollMode,
                                'text-base-content/50 hover:text-base-content' => $datePollMode,
                            ])>
                            <x-icon name="o-calendar-days" class="h-4 w-4" />
                            {{ __('Fixed date') }}
                        </button>
                        <button type="button"
                            wire:click="$set('datePollMode', true)"
                            @class([
                                'flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all',
                                'bg-base-100 shadow text-base-content' => $datePollMode,
                                'text-base-content/50 hover:text-base-content' => ! $datePollMode,
                            ])>
                            <x-icon name="o-chart-bar" class="h-4 w-4" />
                            {{ __('Poll availability') }}
                        </button>
                    </div>
                </div>

                <x-input :label="__('Title')" wire:model="title"
                    :placeholder="__('e.g. Committee meeting - October 2026')"
                    required />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-select :label="__('Type')" wire:model.live="type"
                        :options="$this->typeOptions" />
                    <x-select :label="__('Format')" wire:model.live="format"
                        :options="$this->formatOptions" />
                </div>

                @if ($format === 'physical')
                    <x-input :label="__('Location')" wire:model="location"
                        icon="o-map-pin"
                        :placeholder="__('Club room, bar address…')" />
                @else
                    <x-input :label="__('Meeting link')" wire:model="meetingLink"
                        icon="o-video-camera"
                        placeholder="https://meet.google.com/…" />
                @endif

                <x-textarea :label="__('Description')" wire:model="description"
                    :placeholder="__('Optional context for attendees')" rows="3" />

                {{-- Date/heure : uniquement en mode date fixe --}}
                @if (! $datePollMode)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-datetime type="datetime-local" :label="__('Date & time')" wire:model="scheduledAt" />
                        <x-datetime type="datetime-local" :label="__('End time')" wire:model="endsAt" />
                    </div>
                    <x-datepicker :label="__('RSVP deadline')" wire:model="rsvpDeadline"
                        :placeholder="__('Optional')" />
                @else
                    <x-alert icon="o-calendar" class="alert-info alert-soft"
                        :title="__('Date to be determined by poll')"
                        :description="__('You will propose date options in step 3. The committee will vote on their availability.')" />
                @endif

                {{-- Quorum — AG only --}}
                @if ($type === 'general_assembly')
                    <x-input type="number" :label="__('Quorum (optional)')" wire:model="quorum"
                        min="1" icon="o-users"
                        :hint="__('Minimum number of confirmed members required')" />
                @endif

                {{-- Meal --}}
                <div class="rounded-xl border border-base-200 p-4 space-y-4">
                    <x-toggle :label="__('This meeting includes a meal')"
                        wire:model.live="hasMeal" />
                    @if ($hasMeal)
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-input :label="__('Meal description')" wire:model="mealDescription"
                                :placeholder="__('e.g. Pizzas, 3-course menu…')" />
                            <x-input type="number" :label="__('Price per person (€)')"
                                wire:model="mealPrice" min="0" step="0.50" />
                        </div>
                    @endif
                </div>
            </div>
        </x-card>

        {{-- ── Step 2 — Agenda ──────────────────────────────────────── --}}
        @elseif ($step == '2')
        <x-card>
            <div class="space-y-3">
                @forelse ($agendaItems as $i => $item)
                    <div class="flex items-start gap-2 rounded-lg border border-base-200 bg-base-50 p-3"
                        wire:key="agenda-{{ $i }}">
                        <div class="flex flex-col gap-1 pt-1">
                            <button type="button" wire:click="moveAgendaItemUp({{ $i }})"
                                @class(['btn btn-ghost btn-xs btn-circle', 'opacity-20 pointer-events-none' => $i === 0])>
                                <x-icon name="o-chevron-up" class="h-3 w-3" />
                            </button>
                            <button type="button" wire:click="moveAgendaItemDown({{ $i }})"
                                @class(['btn btn-ghost btn-xs btn-circle', 'opacity-20 pointer-events-none' => $i === count($agendaItems) - 1])>
                                <x-icon name="o-chevron-down" class="h-3 w-3" />
                            </button>
                        </div>
                        <div class="flex-1 space-y-2">
                            <x-input wire:model="agendaItems.{{ $i }}.title"
                                :placeholder="__('Point :n', ['n' => $i + 1])" />
                            <x-textarea wire:model="agendaItems.{{ $i }}.description"
                                :placeholder="__('Optional details…')" rows="2" />
                        </div>
                        <button type="button" wire:click="removeAgendaItem({{ $i }})"
                            class="btn btn-ghost btn-xs btn-circle text-error mt-1">
                            <x-icon name="o-trash" class="h-4 w-4" />
                        </button>
                    </div>
                @empty
                    <x-empty-state icon="o-list-bullet"
                        :heading="__('No agenda items yet')"
                        :message="__('Add points to discuss during the meeting.')" />
                @endforelse
                <x-button icon="o-plus" :label="__('Add item')"
                    class="btn-ghost btn-sm" wire:click="addAgendaItem" />
            </div>
        </x-card>

        {{-- ── Step 3 — Date poll (mode poll uniquement) ───────────── --}}
        @elseif ($step == '3' && $datePollMode)
        <x-card>
            <x-alert icon="o-information-circle"
                :title="__('Date poll')"
                :description="__('Propose multiple dates. The committee will vote on their availability and you\'ll pick the best one.')"
                class="alert-info alert-soft mb-4" />

            @php
                $sortedProposalIndices = collect($dateProposals)
                    ->sortBy(fn ($p) => filled($p['proposed_at']) ? $p['proposed_at'] : '9999')
                    ->keys();
            @endphp
            <div class="space-y-3">
                @forelse ($sortedProposalIndices as $i)
                    <div class="flex items-center gap-3" wire:key="proposal-{{ $i }}">
                        <x-datetime type="datetime-local" wire:model="dateProposals.{{ $i }}.proposed_at"
                            class="flex-1" />
                        <button type="button" wire:click="removeDateProposal({{ $i }})"
                            class="btn btn-ghost btn-sm btn-circle text-error">
                            <x-icon name="o-trash" class="h-4 w-4" />
                        </button>
                    </div>
                @empty
                    <x-empty-state icon="o-calendar"
                        :heading="__('No date proposals yet')"
                        :message="__('Add at least one date option.')" />
                @endforelse
                <x-button icon="o-plus" :label="__('Add date option')"
                    class="btn-ghost btn-sm" wire:click="addDateProposal" />
            </div>
        </x-card>

        {{-- ── Step 4 — Review & save ───────────────────────────────── --}}
        @elseif ($step == '4')
        <x-card>
            <div class="space-y-4 text-sm">

                {{-- Détails réunion --}}
                <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                    <span class="text-base-content/50">{{ __('Title') }}</span>
                    <span class="font-medium">{{ $title ?: '—' }}</span>

                    <span class="text-base-content/50">{{ __('Type') }}</span>
                    <span>{{ \App\Enums\MeetingTypeEnum::tryFrom($type)?->getLabel() ?? '—' }}</span>

                    <span class="text-base-content/50">{{ __('Format') }}</span>
                    <span>{{ \App\Enums\MeetingFormatEnum::tryFrom($format)?->getLabel() ?? '—' }}</span>

                    @if (! $datePollMode && $scheduledAt)
                        <span class="text-base-content/50">{{ __('Date') }}</span>
                        <span>{{ \Carbon\Carbon::parse($scheduledAt)->translatedFormat('d M Y · H\hi') }}</span>
                    @endif

                    @if ($format === 'physical' && $location)
                        <span class="text-base-content/50">{{ __('Location') }}</span>
                        <span>{{ $location }}</span>
                    @endif

                    @if ($format === 'virtual' && $meetingLink)
                        <span class="text-base-content/50">{{ __('Link') }}</span>
                        <span class="break-all">{{ $meetingLink }}</span>
                    @endif

                    @if ($type === 'general_assembly' && $quorum)
                        <span class="text-base-content/50">{{ __('Quorum') }}</span>
                        <span>{{ $quorum }} {{ __('members') }}</span>
                    @endif
                </div>

                {{-- Agenda --}}
                @if (count($agendaItems) > 0)
                    <div class="border-t border-base-200 pt-3">
                        <p class="mb-2 font-semibold">{{ __('Agenda (:n items)', ['n' => count($agendaItems)]) }}</p>
                        <ol class="list-decimal list-inside space-y-1 text-base-content/70">
                            @foreach ($agendaItems as $item)
                                @if (filled($item['title']))
                                    <li>{{ $item['title'] }}</li>
                                @endif
                            @endforeach
                        </ol>
                    </div>
                @endif

                {{-- Sondage --}}
                @if ($datePollMode && count($dateProposals) > 0)
                    <div class="border-t border-base-200 pt-3">
                        <p class="mb-2 font-semibold">{{ __('Date poll (:n options)', ['n' => count(array_filter($dateProposals, fn($p) => filled($p['proposed_at'])))]) }}</p>
                        <ul class="space-y-1 text-base-content/70">
                            @foreach (collect($dateProposals)->filter(fn ($p) => filled($p['proposed_at']))->sortBy('proposed_at') as $p)
                                <li>{{ \Carbon\Carbon::parse($p['proposed_at'])->translatedFormat('d M Y · H\hi') }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Repas --}}
                @if ($hasMeal)
                    <div class="border-t border-base-200 pt-3">
                        <p class="mb-2 font-semibold">{{ __('Meal') }}</p>
                        <p class="text-base-content/70">
                            {{ $mealDescription ?: '—' }}
                            @if ($mealPrice) — {{ number_format((float) $mealPrice, 2) }} €/{{ __('person') }} @endif
                        </p>
                    </div>
                @endif

                {{-- ── Bloc invitations ─────────────────────────────── --}}
                <div class="border-t border-base-200 pt-3">
                    <div class="flex items-center justify-between">
                        <p class="font-semibold">{{ __('Invitations') }}</p>
                        <x-badge :value="$this->inviteeCount . ' ' . __('members')" class="badge-ghost" />
                    </div>
                    <p class="mt-1 text-base-content/60">
                        <x-icon name="{{ $type === 'general_assembly' ? 'o-users' : 'o-user-group' }}" class="h-4 w-4 inline mr-1" />
                        {{ $this->inviteeLabel }}
                    </p>
                </div>

                {{-- ── Note d'envoi automatique ─────────────────────── --}}
                @if (! $meetingId)
                    <x-alert icon="o-paper-airplane"
                        class="alert-info alert-soft"
                        :title="$datePollMode
                            ? __('A date poll will be sent automatically to the :n committee members upon creation.', ['n' => $this->inviteeCount])
                            : ($scheduledAt
                                ? __('Official invitations with calendar file (.ics) will be sent automatically to the :n members upon creation.', ['n' => $this->inviteeCount])
                                : __('No date set — invitations can be sent manually once the date is confirmed.')
                            )" />
                @endif
            </div>
        </x-card>
        @endif

        {{-- ── Footer actions ───────────────────────────────────────── --}}
        <div class="mt-6 flex justify-between">
            <div>
                @if ((int) $step > 1)
                    <x-button :label="__('Back')" icon="o-arrow-left"
                        wire:click="prevStep" />
                @endif
            </div>
            <div class="flex gap-2">
                <x-button :label="__('Cancel')" class="btn-ghost"
                    link="{{ route('admin.meetings.index') }}" />
                @if ((int) $step < 4)
                    <x-button :label="__('Next')" icon-right="o-arrow-right"
                        class="btn-primary"
                        wire:click="nextStep" />
                @else
                    <x-button :label="$meetingId ? __('Save changes') : __('Create meeting')"
                        icon="o-check" class="btn-primary"
                        wire:click="save" spinner="save" />
                @endif
            </div>
        </div>
    </div>

    {{-- ── Cancel modal ──────────────────────────────────────────────── --}}
    <x-modal wire:model="showCancelModal" :title="__('Cancel meeting')" class="backdrop-blur">
        <x-alert
            :title="__('This action cannot be undone')"
            :description="__('All invited members will receive a cancellation notification.')"
            icon="o-exclamation-triangle"
            class="alert-error alert-soft" />
        <x-slot:actions>
            <x-button :label="__('Keep meeting')" wire:click="$set('showCancelModal', false)" />
            <x-button :label="__('Yes, cancel it')" icon="o-x-circle"
                class="btn-error" wire:click="cancelMeeting" spinner="cancelMeeting" />
        </x-slot:actions>
    </x-modal>
</div>
