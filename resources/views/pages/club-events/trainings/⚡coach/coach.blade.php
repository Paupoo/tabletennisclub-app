<div>
    @if ($selectedSessionId && $selectedSession)
        {{-- ================================================================
             SESSION DETAIL
        ================================================================ --}}
        <x-header separator
            :subtitle="$selectedSession->start->translatedFormat('l d F Y') . ' · ' . $selectedSession->start->format('H:i') . '–' . $selectedSession->end->format('H:i')"
            :title="$selectedSession->trainingPack?->name ?? __('Session')">
            <x-slot:actions>
                <x-button class="btn-ghost" icon="o-arrow-left" label="{{ __('Back') }}"
                    wire:click="backToList" />
                <x-button class="btn-error btn-soft" icon="o-x-circle" label="{{ __('Cancel session') }}"
                    wire:click="openCancel" />
            </x-slot:actions>
        </x-header>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- Left — session info --}}
            <div class="space-y-4">
                <x-card>
                    <div class="space-y-2 text-sm text-base-content/70">
                        <div class="flex items-center gap-2">
                            <x-icon class="h-4 w-4 opacity-40" name="o-map-pin" />
                            {{ $selectedSession->room?->name ?? '—' }}
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon class="h-4 w-4 opacity-40" name="o-academic-cap" />
                            {{ $selectedSession->trainingPack?->level?->value }} ·
                            {{ $selectedSession->trainingPack?->type?->value }}
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon class="h-4 w-4 opacity-40" name="o-users" />
                            {{ $enrolledMembers->count() }} {{ __('enrolled') }}
                        </div>
                    </div>
                </x-card>

                {{-- Attendance legend --}}
                <x-card title="{{ __('Attendance') }}">
                    <div class="space-y-1.5 text-xs text-base-content/60">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-success"></span>
                            {{ __('Present') }}
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-error"></span>
                            {{ __('Absent') }}
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-warning"></span>
                            {{ __('Excused') }}
                        </div>
                    </div>
                </x-card>
            </div>

            {{-- Right — member list --}}
            <div class="lg:col-span-2">
                <x-card title="{{ __('Members') }}">
                    @forelse ($enrolledMembers as $member)
                        @php
                            $isMinor = $member->birthdate && \Carbon\Carbon::parse($member->birthdate)->age < 18;
                            $guardian = $isMinor ? $member->guardians->first() : null;
                            $currentStatus = $attendanceStatus[$member->id] ?? 'enrolled';
                            $presenceRate = $this->presenceRate($member->id);
                            $interclubDivisions = $member->teams->map(fn ($t) => $t->league?->name ?? null)->filter()->unique()->implode(', ');
                        @endphp

                        <div class="border-b border-base-200 py-4 last:border-0">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3">
                                    <x-avatar class="h-10 w-10"
                                        placeholder="{{ mb_substr($member->first_name, 0, 1) }}" />
                                    <div>
                                        <p class="font-medium">{{ $member->full_name }}</p>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-base-content/60">
                                            @if ($member->ranking && $member->ranking !== 'NA')
                                                <span>{{ $member->ranking }}</span>
                                            @endif

                                            @if ($presenceRate > 0)
                                                <span
                                                    @class([
                                                        'text-success' => $presenceRate >= 70,
                                                        'text-warning' => $presenceRate >= 40 && $presenceRate < 70,
                                                        'text-error' => $presenceRate < 40,
                                                    ])>
                                                    {{ $presenceRate }}% {{ __('presence') }}
                                                </span>
                                            @endif

                                            @if ($interclubDivisions)
                                                <span>{{ $interclubDivisions }}</span>
                                            @endif

                                            @if ($member->phone_number)
                                                <span>{{ $member->phone_number }}</span>
                                            @endif

                                            @if ($isMinor && $guardian)
                                                <span class="text-warning">
                                                    {{ __('Guardian') }}:
                                                    {{ $guardian->guardian_phone_number ?? $guardian->phone_number }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Attendance buttons --}}
                                <div class="flex shrink-0 gap-1">
                                    <x-button
                                        @class([
                                            'btn-xs',
                                            'btn-success' => $currentStatus === 'present',
                                            'btn-ghost' => $currentStatus !== 'present',
                                        ])
                                        icon="o-check" tooltip="{{ __('Present') }}"
                                        wire:click="setAttendance({{ $member->id }}, 'present')" />
                                    <x-button
                                        @class([
                                            'btn-xs',
                                            'btn-warning' => $currentStatus === 'excused',
                                            'btn-ghost' => $currentStatus !== 'excused',
                                        ])
                                        icon="o-clock" tooltip="{{ __('Excused') }}"
                                        wire:click="setAttendance({{ $member->id }}, 'excused')" />
                                    <x-button
                                        @class([
                                            'btn-xs',
                                            'btn-error' => $currentStatus === 'absent',
                                            'btn-ghost' => $currentStatus !== 'absent',
                                        ])
                                        icon="o-x-mark" tooltip="{{ __('Absent') }}"
                                        wire:click="setAttendance({{ $member->id }}, 'absent')" />
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-10 text-center text-base-content/40">
                            <x-icon class="mx-auto mb-2 h-8 w-8" name="o-users" />
                            <p>{{ __('No members enrolled in this pack.') }}</p>
                        </div>
                    @endforelse
                </x-card>
            </div>
        </div>

    @else
        {{-- ================================================================
             PLANNING — upcoming sessions list
        ================================================================ --}}
        <x-header separator subtitle="{{ __('Your upcoming sessions') }}" title="{{ __('My sessions') }}" />

        @forelse ($upcomingSessions as $session)
            <div wire:click="viewSession({{ $session->id }})"
                class="mb-3 flex cursor-pointer items-center justify-between rounded-xl border border-base-200 bg-base-100 px-4 py-3 transition hover:border-primary/30 hover:bg-primary/5">
                <div class="flex items-center gap-4">
                    <div class="text-center">
                        <div class="text-xs font-bold uppercase text-base-content/50">
                            {{ $session->start->translatedFormat('M') }}
                        </div>
                        <div class="text-2xl font-bold leading-none">{{ $session->start->format('d') }}</div>
                        <div class="text-xs text-base-content/50">{{ $session->start->translatedFormat('D') }}
                        </div>
                    </div>
                    <div>
                        <p class="font-medium">{{ $session->trainingPack?->name }}</p>
                        <p class="text-xs text-base-content/60">
                            {{ $session->start->format('H:i') }} – {{ $session->end->format('H:i') }}
                            · {{ $session->room?->name }}
                        </p>
                        <p class="mt-0.5 text-xs text-base-content/40">
                            {{ $session->trainingPack?->level?->value }}
                        </p>
                    </div>
                </div>
                <x-icon class="h-5 w-5 text-base-content/30" name="o-chevron-right" />
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-base-300 py-16 text-center text-base-content/40">
                <x-icon class="mx-auto mb-2 h-10 w-10" name="o-calendar" />
                <p>{{ __('No upcoming sessions.') }}</p>
            </div>
        @endforelse
    @endif

    {{-- ================================================================
         CANCELLATION MODAL
    ================================================================ --}}
    <x-modal title="{{ __('Cancel this session') }}" wire:model="cancelModal" separator>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div @class([
                    'cursor-pointer rounded-xl border-2 p-3 text-center transition',
                    'border-warning bg-warning/10' => $cancelType === 'FREE',
                    'border-base-200' => $cancelType !== 'FREE',
                ]) wire:click="$set('cancelType', 'FREE')">
                    <x-icon class="mx-auto mb-1 h-6 w-6 text-warning" name="o-sun" />
                    <p class="text-sm font-semibold">{{ __('Free practice') }}</p>
                    <p class="text-xs text-base-content/60">{{ __('Room open, no coach') }}</p>
                </div>
                <div @class([
                    'cursor-pointer rounded-xl border-2 p-3 text-center transition',
                    'border-error bg-error/10' => $cancelType === 'CLOSED',
                    'border-base-200' => $cancelType !== 'CLOSED',
                ]) wire:click="$set('cancelType', 'CLOSED')">
                    <x-icon class="mx-auto mb-1 h-6 w-6 text-error" name="o-lock-closed" />
                    <p class="text-sm font-semibold">{{ __('Room closed') }}</p>
                    <p class="text-xs text-base-content/60">{{ __('Inaccessible') }}</p>
                </div>
            </div>

            <x-textarea label="{{ __('Note (optional)') }}"
                placeholder="{{ __('E.g. Bank holiday, maintenance…') }}" wire:model="cancelNote" rows="2" />
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Abandon') }}" wire:click="$set('cancelModal', false)" />
            <x-button class="btn-error" icon="o-x-circle" label="{{ __('Confirm cancellation') }}"
                wire:click="confirmCancel" />
        </x-slot:actions>
    </x-modal>
</div>
