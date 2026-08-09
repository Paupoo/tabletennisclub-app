<div>
    @php
        $currentStep = (int) $step;

        $wizardSteps = [
            1 => ['label' => __('Welcome'),       'icon' => 'o-hand-raised'],
            2 => ['label' => __('Administrator'),  'icon' => 'o-user-circle'],
            3 => ['label' => __('Club'),           'icon' => 'o-building-office'],
            4 => ['label' => __('Season'),         'icon' => 'o-calendar'],
            5 => ['label' => __('Rooms'),          'icon' => 'o-map-pin'],
            6 => ['label' => __('Tables'),         'icon' => 'o-table-cells'],
            7 => ['label' => __('Done'),           'icon' => 'o-check-badge'],
        ];
    @endphp

    {{-- ── Metro-line navigation ────────────────────────────────────────────── --}}
    <div class="flex items-center gap-0 mb-8 overflow-x-auto pb-2">
        @foreach ($wizardSteps as $num => $info)
            @php
                $reachable = $num <= $maxReachable;
                $locked    = $num <= $submittedStep && $num !== $currentStep;
            @endphp
            <button
                wire:click="{{ ($reachable && !$locked) ? "\$set('step', '{$num}')" : 'null' }}"
                @class([
                    'flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap',
                    'bg-primary text-primary-content shadow'                                          => $num === $currentStep,
                    'text-base-content/40 cursor-not-allowed'                                         => $locked,
                    'text-base-content/70 hover:bg-base-200 hover:text-base-content cursor-pointer'  => $reachable && !$locked && $num !== $currentStep,
                    'text-base-content/30 cursor-not-allowed'                                         => !$reachable,
                ])
            >
                <x-icon name="{{ $info['icon'] }}" class="w-4 h-4 shrink-0" />
                <span class="hidden sm:inline">{{ $info['label'] }}</span>
                @if ($num <= $submittedStep)
                    <x-icon name="o-lock-closed" class="w-3.5 h-3.5 text-base-content/30 shrink-0" />
                @elseif ($reachable && $num < $currentStep)
                    <x-icon name="o-check-circle" class="w-3.5 h-3.5 text-success shrink-0" />
                @endif
            </button>
            @if ($num < 7)
                <x-icon name="o-chevron-right" class="w-4 h-4 text-base-content/15 shrink-0 mx-0.5" />
            @endif
        @endforeach
    </div>

    {{-- ── Step content ────────────────────────────────────────────────────── --}}

    {{-- STEP 1 — Welcome --}}
    @if ($step == '1')
        <div class="animate-in fade-in duration-500 text-center py-4">
            <div class="mb-6">
                <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <x-icon name="o-rocket-launch" class="w-8 h-8 text-primary" />
                </div>
                <h2 class="text-2xl font-bold text-base-content">{{ __('Welcome to the setup wizard') }}</h2>
                <p class="mt-3 text-base-content/60 max-w-lg mx-auto">
                    {{ __('This wizard will guide you through the initial configuration of your table tennis club management application.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-8 text-left max-w-lg mx-auto">
                @foreach ([
                    ['icon' => 'o-user-circle',     'label' => __('Administrator account')],
                    ['icon' => 'o-building-office', 'label' => __('Club information')],
                    ['icon' => 'o-calendar',        'label' => __('First season')],
                    ['icon' => 'o-map-pin',         'label' => __('Training rooms')],
                ] as $item)
                    <div class="flex items-center gap-2.5 p-3 bg-base-200/50 rounded-lg">
                        <x-icon name="{{ $item['icon'] }}" class="w-5 h-5 text-primary shrink-0" />
                        <span class="text-sm text-base-content/80">{{ $item['label'] }}</span>
                    </div>
                @endforeach
            </div>

            <x-button
                :label="__('Start setup')"
                icon="o-arrow-right"
                class="btn-primary btn-lg"
                wire:click="startWizard"
            />
        </div>
    @endif

    {{-- STEP 2 — Administrator account --}}
    @if ($step == '2')
        <div class="animate-in fade-in duration-500">

            <h2 class="text-lg font-semibold text-base-content mb-1">{{ __('Administrator account') }}</h2>
            @if ($submittedStep >= 2)
                <x-alert :title="__('This step has already been completed and can no longer be modified.')" icon="o-lock-closed" class="alert-warning alert-soft mb-4" />
            @else
            <p class="text-sm text-base-content/60 mb-6">{{ __('This account will have access to all features of the application.') }}</p>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input
                    :label="__('First name')"
                    wire:model="firstName"
                    placeholder="Jean"
                    required
                />
                <x-input
                    :label="__('Last name')"
                    wire:model="lastName"
                    placeholder="Dupont"
                    required
                />
                <x-input
                    :label="__('Email address')"
                    wire:model="email"
                    type="email"
                    placeholder="admin@myclub.be"
                    class="sm:col-span-2"
                    required
                />
                <x-input
                    :label="__('Password')"
                    wire:model="password"
                    type="password"
                    required
                />
                <x-input
                    :label="__('Confirm password')"
                    wire:model="passwordConfirmation"
                    type="password"
                    required
                />
            </div>

            @if ($submittedStep < 2)
            <div class="flex justify-end mt-6">
                <x-button
                    :label="__('Next')"
                    icon-right="o-arrow-right"
                    class="btn-primary"
                    wire:click="completeStep2"
                    spinner="completeStep2"
                />
            </div>
            @endif
        </div>
    @endif

    {{-- STEP 3 — Club (licence + info, merged) --}}
    @if ($step == '3')
        <div class="animate-in fade-in duration-500">
            <h2 class="text-lg font-semibold text-base-content mb-1">{{ __('Club information') }}</h2>
            @if ($submittedStep >= 3)
                <x-alert :title="__('This step has already been completed and can no longer be modified.')" icon="o-lock-closed" class="alert-warning alert-soft mb-4" />
            @else
            <p class="text-sm text-base-content/60 mb-6">{{ __('This information will be displayed on the public site and used in official communications.') }}</p>
            @endif

            <div class="space-y-6">
                {{-- Identity --}}
                <div>
                    <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-3">{{ __('Identity') }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input
                            :label="__('Club name')"
                            wire:model="clubName"
                            placeholder="CTT Ottignies-Blocry"
                            class="sm:col-span-2"
                            required
                        />
                        <x-input
                            :label="__('Licence')"
                            wire:model="licence"
                            pattern="[A-Z]{3}[0-9]{3}"
                            placeholder="BBW214"
                            :hint="__('E.g. BBW214, HEW058...')"
                            required
                        />
                        <x-input
                            :label="__('Enterprise number')"
                            wire:model="clubEnterpriseNumber"
                            placeholder="0000.000.000"
                        />
                        <x-input
                            :label="__('BIC / SWIFT code')"
                            wire:model="clubBic"
                            placeholder="GEBABEBB"
                        />
                        <x-input
                            :label="__('IBAN account number')"
                            wire:model="clubBankAccount"
                            placeholder="BE00 0000 0000 0000"
                        />
                    </div>
                </div>

                {{-- Address --}}
                <div>
                    <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-3">{{ __('Address') }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input
                            :label="__('Building name')"
                            wire:model="clubBuildingName"
                            placeholder="Sports Centre"
                            class="sm:col-span-2"
                        />
                        <x-input
                            :label="__('Street')"
                            wire:model="clubStreet"
                            placeholder="Rue de la Station 1"
                            class="sm:col-span-2"
                            required
                        />
                        <x-input
                            :label="__('Postal code')"
                            wire:model="clubCityCode"
                            placeholder="1340"
                            required
                        />
                        <x-input
                            :label="__('City')"
                            wire:model="clubCityName"
                            placeholder="Ottignies"
                            required
                        />
                    </div>
                </div>

                {{-- Contact --}}
                <div>
                    <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-3">{{ __('Contact') }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input
                            :label="__('Contact email')"
                            wire:model="clubEmailContact"
                            type="email"
                            placeholder="contact@myclub.be"
                            required
                        />
                        <x-input
                            :label="__('Phone')"
                            wire:model="clubPhoneContact"
                            placeholder="+32 10 00 00 00"
                        />
                        <x-input
                            :label="__('Website')"
                            wire:model="clubWebsiteUrl"
                            placeholder="https://www.myclub.be"
                            class="sm:col-span-2"
                        />
                    </div>
                </div>
            </div>

            @if ($submittedStep < 3)
            <div class="flex justify-end mt-6">
                <x-button
                    :label="__('Next')"
                    icon-right="o-arrow-right"
                    class="btn-primary"
                    wire:click="completeStep3"
                    spinner="completeStep3"
                />
            </div>
            @endif
        </div>
    @endif

    {{-- STEP 4 — Season --}}
    @if ($step == '4')
        <div class="animate-in fade-in duration-500">
            <h2 class="text-lg font-semibold text-base-content mb-1">{{ __('First season') }}</h2>
            @if ($submittedStep >= 4)
                <x-alert :title="__('This step has already been completed and can no longer be modified.')" icon="o-lock-closed" class="alert-warning alert-soft mb-4" />
            @else
            <p class="text-sm text-base-content/60 mb-6">{{ __('The season is the reference period for memberships, training sessions and interclub matches. It will be marked as the active season.') }}</p>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-lg">
                <x-input
                    :label="__('Season name')"
                    wire:model="seasonName"
                    placeholder="2025-2026"
                    class="sm:col-span-3"
                    required
                />
                <x-input
                    :label="__('Start')"
                    wire:model="seasonStartAt"
                    type="date"
                    required
                />
                <x-input
                    :label="__('End')"
                    wire:model="seasonEndAt"
                    type="date"
                    required
                />
            </div>

            @if ($submittedStep < 4)
            <div class="flex justify-end mt-6">
                <x-button
                    :label="__('Create season')"
                    icon-right="o-arrow-right"
                    class="btn-primary"
                    wire:click="completeStep4"
                    spinner="completeStep4"
                />
            </div>
            @endif
        </div>
    @endif

    {{-- STEP 5 — Rooms --}}
    @if ($step == '5')
        <div class="animate-in fade-in duration-500">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <h2 class="text-lg font-semibold text-base-content">{{ __('Training rooms') }}</h2>
                    @if ($submittedStep >= 5)
                        <x-alert :title="__('This step has already been completed and can no longer be modified.')" icon="o-lock-closed" class="alert-warning alert-soft mt-2" />
                    @else
                    <p class="text-sm text-base-content/60 mt-0.5">{{ __('Add the rooms where your training sessions and interclub matches take place.') }}</p>
                    @endif
                </div>
                <span class="badge badge-soft badge-ghost text-xs">{{ __('Optional') }}</span>
            </div>

            {{-- Room list --}}
            @if (count($rooms) > 0)
                <div class="mt-4 space-y-2 mb-4">
                    @foreach ($rooms as $i => $room)
                        <div class="flex items-center justify-between p-3 bg-base-200/60 rounded-lg">
                            <div>
                                <p class="font-medium text-sm text-base-content">{{ $room['name'] }}</p>
                                <p class="text-xs text-base-content/50">{{ $room['city_code'] }} {{ $room['city_name'] }} — {{ $room['capacity_for_trainings'] }} {{ __('training spots') }}</p>
                            </div>
                            <x-button
                                icon="o-trash"
                                class="btn-ghost btn-sm text-error"
                                wire:click="removeRoom({{ $i }})"
                            />
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-4 mb-4 p-4 bg-base-200/30 rounded-lg text-center">
                    <x-icon name="o-map-pin" class="w-8 h-8 text-base-content/20 mx-auto mb-2" />
                    <p class="text-sm text-base-content/40">{{ __('No rooms added') }}</p>
                </div>
            @endif

            {{-- Add room form --}}
            @if ($submittedStep < 5 && $showRoomForm)
                <div class="border border-base-300 rounded-xl p-4 mt-2">
                    <p class="text-sm font-semibold text-base-content mb-4">{{ __('New room') }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-input
                            :label="__('Room name')"
                            wire:model="roomName"
                            placeholder="Main hall"
                            class="sm:col-span-2"
                            required
                        />
                        <x-input
                            :label="__('Building')"
                            wire:model="roomBuildingName"
                            placeholder="Sports Centre"
                            class="sm:col-span-2"
                        />
                        <x-input
                            :label="__('Street')"
                            wire:model="roomStreet"
                            placeholder="Rue de la Station 1"
                            class="sm:col-span-2"
                            required
                        />
                        <x-input
                            :label="__('Postal code')"
                            wire:model="roomCityCode"
                            type="number"
                            placeholder="1340"
                            required
                        />
                        <x-input
                            :label="__('City')"
                            wire:model="roomCityName"
                            placeholder="Ottignies"
                            required
                        />
                        <x-input
                            :label="__('Training capacity')"
                            wire:model="roomCapacityTraining"
                            type="number"
                            :hint="__('Maximum number of players')"
                        />
                        <x-input
                            :label="__('Interclub capacity')"
                            wire:model="roomCapacityInterclub"
                            type="number"
                            :hint="__('Number of tables for matches')"
                        />
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <x-button
                            :label="__('Cancel')"
                            class="btn-ghost btn-sm"
                            wire:click="$set('showRoomForm', false)"
                        />
                        <x-button
                            :label="__('Add')"
                            icon="o-plus"
                            class="btn-primary btn-sm"
                            wire:click="addRoom"
                            spinner="addRoom"
                        />
                    </div>
                </div>
            @elseif ($submittedStep < 5)
                <x-button
                    :label="__('+ Add a room')"
                    class="btn-ghost btn-sm border border-dashed border-base-300 w-full mt-2"
                    wire:click="$set('showRoomForm', true)"
                />
            @endif

            @if ($submittedStep < 5)
            <div class="flex items-center justify-between mt-6">
                <x-button
                    :label="__('Skip this step')"
                    class="btn-ghost btn-sm"
                    wire:click="skipStep5"
                />
                <x-button
                    :label="__('Next')"
                    icon-right="o-arrow-right"
                    class="btn-primary"
                    wire:click="completeStep5"
                    spinner="completeStep5"
                />
            </div>
            @endif
        </div>
    @endif

    {{-- STEP 6 — Tables --}}
    @if ($step == '6')
        <div class="animate-in fade-in duration-500">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <h2 class="text-lg font-semibold text-base-content">{{ __('Ping-pong tables') }}</h2>
                    @if ($submittedStep >= 6)
                        <x-alert :title="__('This step has already been completed and can no longer be modified.')" icon="o-lock-closed" class="alert-warning alert-soft mt-2" />
                    @else
                    <p class="text-sm text-base-content/60 mt-0.5">{{ __('Add the tables available in each room.') }}</p>
                    @endif
                </div>
                <span class="badge badge-soft badge-ghost text-xs">{{ __('Optional') }}</span>
            </div>

            <div class="mt-4 space-y-6">
                @foreach ($rooms as $i => $room)
                    <div class="border border-base-200 rounded-xl overflow-hidden">
                        <div class="bg-base-200/40 px-4 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <x-icon name="o-map-pin" class="w-4 h-4 text-primary" />
                                <span class="font-medium text-sm">{{ $room['name'] }}</span>
                            </div>
                            <span class="badge badge-sm badge-soft badge-primary">
                                {{ count($tables[$i] ?? []) }} {{ __('table(s)') }}
                            </span>
                        </div>

                        <div class="p-4 space-y-2">
                            @foreach ($tables[$i] ?? [] as $t => $table)
                                <div class="flex items-center justify-between py-1.5 px-2 bg-base-200/30 rounded-lg">
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-check-circle" class="w-4 h-4 text-success shrink-0" />
                                        <span class="text-sm">{{ $table['name'] }}</span>
                                        @if ($table['brand'])
                                            <span class="text-xs text-base-content/40">{{ $table['brand'] }}</span>
                                        @endif
                                    </div>
                                    <x-button
                                        icon="o-trash"
                                        class="btn-ghost btn-xs text-error"
                                        wire:click="removeTable({{ $i }}, {{ $t }})"
                                    />
                                </div>
                            @endforeach

                            @if ($submittedStep < 6 && $showTableForm && $activeRoomIndex === $i)
                                <div class="border border-base-300 rounded-lg p-3 mt-2">
                                    <div class="grid grid-cols-2 gap-3">
                                        <x-input
                                            :label="__('Name / number')"
                                            wire:model="tableName"
                                            placeholder="Table 1"
                                            required
                                        />
                                        <x-input
                                            :label="__('Brand')"
                                            wire:model="tableBrand"
                                            placeholder="Butterfly"
                                        />
                                    </div>
                                    <x-toggle
                                        :label="__('Available')"
                                        wire:model="tableIsAvailable"
                                        class="mt-3"
                                    />
                                    <div class="flex justify-end gap-2 mt-3">
                                        <x-button
                                            :label="__('Cancel')"
                                            class="btn-ghost btn-xs"
                                            wire:click="$set('showTableForm', false)"
                                        />
                                        <x-button
                                            :label="__('Add')"
                                            icon="o-plus"
                                            class="btn-primary btn-xs"
                                            wire:click="addTable"
                                            spinner="addTable"
                                        />
                                    </div>
                                </div>
                            @elseif ($submittedStep < 6)
                                <button
                                    class="w-full text-center text-xs text-base-content/40 hover:text-base-content/60 py-1.5 border border-dashed border-base-300 rounded-lg transition-colors"
                                    wire:click="openTableForm({{ $i }})"
                                >
                                    + {{ __('Add a table') }}
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($submittedStep < 6)
            <div class="flex items-center justify-between mt-6">
                <x-button
                    :label="__('Skip this step')"
                    class="btn-ghost btn-sm"
                    wire:click="skipStep6"
                />
                <x-button
                    :label="__('Next')"
                    icon-right="o-arrow-right"
                    class="btn-primary"
                    wire:click="completeStep6"
                    spinner="completeStep6"
                />
            </div>
            @endif
        </div>
    @endif

    {{-- STEP 7 — Done --}}
    @if ($step == '7')
        <div class="animate-in fade-in duration-500 text-center py-4">
            <div class="w-16 h-16 bg-success/15 rounded-full flex items-center justify-center mx-auto mb-4">
                <x-icon name="o-check-badge" class="w-8 h-8 text-success" />
            </div>

            <h2 class="text-2xl font-bold text-base-content">{{ __('Almost done!') }}</h2>
            <p class="mt-2 text-base-content/60">{{ __('Review the summary below, then click the button to finalize the setup.') }}</p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6 mb-8">
                @php
                    $totalTables = collect($tables)->flatten(1)->count();
                @endphp

                <div class="p-3 bg-success/10 border border-success/20 rounded-xl">
                    <x-icon name="o-user-circle" class="w-6 h-6 text-success mx-auto mb-1" />
                    <p class="text-xs font-semibold text-success">{{ __('Admin') }}</p>
                    <p class="text-xs text-base-content/60 truncate">{{ $email }}</p>
                </div>

                <div class="p-3 bg-success/10 border border-success/20 rounded-xl">
                    <x-icon name="o-building-office" class="w-6 h-6 text-success mx-auto mb-1" />
                    <p class="text-xs font-semibold text-success">{{ __('Club') }}</p>
                    <p class="text-xs text-base-content/60 truncate">{{ $clubName ?: $licence }}</p>
                </div>

                <div class="p-3 bg-success/10 border border-success/20 rounded-xl">
                    <x-icon name="o-calendar" class="w-6 h-6 text-success mx-auto mb-1" />
                    <p class="text-xs font-semibold text-success">{{ __('Season') }}</p>
                    <p class="text-xs text-base-content/60">{{ $seasonName }}</p>
                </div>

                @if (count($rooms) > 0)
                <div class="p-3 bg-success/10 border border-success/20 rounded-xl">
                    <x-icon name="o-map-pin" class="w-6 h-6 text-success mx-auto mb-1" />
                    <p class="text-xs font-semibold text-success">{{ count($rooms) }} {{ __('room(s)') }}</p>
                    <p class="text-xs text-base-content/60">{{ $totalTables }} {{ __('table(s)') }}</p>
                </div>
                @else
                <div class="p-3 bg-base-200/50 border border-base-300/50 rounded-xl">
                    <x-icon name="o-map-pin" class="w-6 h-6 text-base-content/30 mx-auto mb-1" />
                    <p class="text-xs font-semibold text-base-content/40">{{ count($rooms) }} {{ __('room(s)') }}</p>
                    <p class="text-xs text-base-content/60">{{ $totalTables }} {{ __('table(s)') }}</p>
                </div>
                @endif
            </div>

            <x-alert
                :title="__('Email configuration (SMTP)')"
                :description="__('Email server configuration is not covered by this wizard. Modify the MAIL_* variables in your .env file.')"
                icon="o-envelope"
                class="alert-warning alert-soft text-left mb-6"
            />

            <x-button
                :label="__('Go to dashboard')"
                icon-right="o-arrow-right"
                class="btn-primary btn-lg"
                wire:click="completeSetup"
                spinner="completeSetup"
            />
        </div>
    @endif
</div>
