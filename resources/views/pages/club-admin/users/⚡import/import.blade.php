<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header :title="__('Federation import')"
        :subtitle="__('Seed the roster from the federation affiliate listing')"
        separator progress-indicator>
        <x-slot:actions>
            <x-button class="btn-ghost btn-sm" icon="o-arrow-left" :label="__('Back to members')"
                link="{{ route('admin.users.index') }}" />
        </x-slot:actions>
    </x-header>

    {{-- ── Step 1 · the file ────────────────────────────────────────────────── --}}
    @if ($step === 1)
        <div class="mx-auto max-w-2xl">
            <x-card>
                <div class="space-y-4">
                    <p class="text-sm opacity-70">
                        {{ __('Upload the affiliate listing exported from the federation. Nothing is written yet: the file is read, confronted with the roster, and handed to you line by line.') }}
                    </p>

                    <x-alert icon="o-envelope" class="alert-info alert-soft">
                        {{ __('No email is sent by an import, ever. Members are invited later, from the members list, when the committee decides to.') }}
                    </x-alert>

                    <x-file wire:model="importFile" :label="__('Affiliate listing')" accept=".csv,.txt"
                        hint="CSV · {{ __('semicolon separated, as exported') }}" />

                    <p class="text-xs opacity-50">
                        {{ __('Expected columns: Licence, Nom, DATE NAISSANCE, CH, CD, SA, Statut, Email, Tel, GSM, Adresse, Numéro, CP, Localité') }}
                    </p>
                </div>

                <x-slot:actions>
                    <x-button class="btn-primary" icon="o-arrow-right" :label="__('Read the file')"
                        wire:click="parse" spinner="parse" />
                </x-slot:actions>
            </x-card>
        </div>
    @endif

    {{-- ── Step 2 · the review ──────────────────────────────────────────────── --}}
    @if ($step === 2)
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <x-admin.shared.stat-card :label="__('To create')" :value="(string) $this->tally['create']"
                    icon="o-user-plus" color="success" />
                <x-admin.shared.stat-card :label="__('To update')" :value="(string) $this->tally['update']"
                    icon="o-arrow-path" color="primary" />
                <x-admin.shared.stat-card :label="__('Ignored')" :value="(string) $this->tally['skip']"
                    icon="o-no-symbol" />
                <x-admin.shared.stat-card :label="__('To be decided')" :value="(string) $this->tally['undecided']"
                    icon="o-question-mark-circle" :color="$this->tally['undecided'] > 0 ? 'warning' : 'neutral'" />
            </div>

            @if ($this->tally['undecided'] > 0)
                <x-alert icon="o-exclamation-triangle" class="alert-warning alert-soft">
                    {{ __('Some affiliates could not be told apart from a member the club already holds. Settle each of them before importing.') }}
                </x-alert>
            @endif

            @if (count($failures) > 0)
                <x-alert icon="o-document-minus" class="alert-error alert-soft">
                    <span>{{ __(':count line(s) could not be read and will be recorded as errors.', ['count' => count($failures)]) }}</span>
                </x-alert>
            @endif

            <div class="space-y-3">
                @foreach ($rows as $line => $row)
                    <div wire:key="line-{{ $line }}"
                        class="rounded-xl border border-base-200 bg-base-100 p-4 {{ $row['action'] === '' ? 'border-warning' : '' }}">
                        <div class="grid gap-4 lg:grid-cols-12">
                            {{-- Identity, editable: past two words the split is a guess --}}
                            <div class="lg:col-span-4">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-xs opacity-40">#{{ $row['licence'] }}</span>
                                    @if ($row['needsNameReview'])
                                        <span class="badge badge-warning badge-soft badge-sm">{{ __('Check the name') }}</span>
                                    @endif
                                    @if ($row['needsAddressReview'])
                                        <span class="badge badge-warning badge-soft badge-sm">{{ __('Check the address') }}</span>
                                    @endif
                                    @if ($row['isMinor'])
                                        <span class="badge badge-ghost badge-sm">{{ __('Minor') }}</span>
                                    @endif
                                </div>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <x-input wire:model="rows.{{ $line }}.lastName" class="input-sm"
                                        :label="__('Last name')" />
                                    <x-input wire:model="rows.{{ $line }}.firstName" class="input-sm"
                                        :label="__('First name')" />
                                </div>
                            </div>

                            {{-- What the file says --}}
                            <div class="space-y-1 text-sm lg:col-span-3">
                                <p class="opacity-70">
                                    {{ $row['birthdate'] ?? __('No birthdate') }} · {{ $row['ranking'] }}
                                    @if ($row['federationLicenceType'])
                                        · {{ $row['federationLicenceType'] }}
                                    @endif
                                </p>
                                <p class="truncate opacity-70">{{ $row['email'] ?? __('No address') }}</p>
                                <p class="truncate text-xs opacity-50">
                                    {{ collect([$row['street'], $row['cityCode'], $row['cityName']])->filter()->join(' · ') }}
                                </p>
                            </div>

                            {{-- What the roster answered --}}
                            <div class="space-y-1 text-sm lg:col-span-3">
                                @if ($row['outcome'] === 'new')
                                    <span class="badge badge-success badge-soft badge-sm">{{ __('Unknown to the club') }}</span>
                                @elseif ($row['outcome'] === 'matched')
                                    <span class="badge badge-info badge-soft badge-sm">{{ __('Already a member') }}</span>
                                @elseif ($row['outcome'] === 'suspect')
                                    <span class="badge badge-warning badge-sm">{{ __('Namesake, different birthdate') }}</span>
                                @else
                                    <span class="badge badge-warning badge-sm">{{ __('Archived member') }}</span>
                                @endif

                                @if ($row['existingLabel'])
                                    <p class="truncate opacity-70">{{ $row['existingLabel'] }}</p>
                                @endif

                                @foreach ($row['discrepancies'] as $discrepancy)
                                    <p class="text-xs text-warning">{{ $discrepancy }}</p>
                                @endforeach
                            </div>

                            {{-- What will be done about it --}}
                            <div class="lg:col-span-2">
                                <x-select wire:model.live="rows.{{ $line }}.action" class="select-sm"
                                    :label="__('Action')" :placeholder="__('To be decided')"
                                    :options="$this->actionOptions($row['outcome'])" />
                            </div>
                        </div>

                        {{-- The address of a child is a parent's, and the file rarely proves it --}}
                        @if ($row['isMinor'] && $row['action'] !== 'skip')
                            <div class="mt-3 border-t border-base-200 pt-3">
                                <x-checkbox wire:model.live="rows.{{ $line }}.guardianAddress"
                                    :label="__('This address belongs to a guardian')"
                                    :hint="__('The member is recorded without a login of their own and reached through their guardian.')" />

                                @if ($row['guardianAddress'] && $row['guardianLineNumber'] === null)
                                    <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:max-w-lg">
                                        <x-input wire:model="rows.{{ $line }}.guardianFirstName" class="input-sm"
                                            :label="__('Guardian first name')" />
                                        <x-input wire:model="rows.{{ $line }}.guardianLastName" class="input-sm"
                                            :label="__('Guardian last name')" />
                                    </div>
                                    <p class="mt-1 text-xs opacity-50">
                                        {{ __('Suggested from the address — correct it, nothing is recorded until you import.') }}
                                    </p>
                                @elseif ($row['guardianAddress'])
                                    <p class="mt-1 text-xs opacity-50">
                                        {{ __('Reached through the adult listed under the same address.') }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-3 border-t border-base-200 bg-base-100 py-3">
                <p class="text-sm opacity-70">
                    {{ __(':count affiliate(s) read from the file.', ['count' => count($rows)]) }}
                </p>
                <div class="flex items-center gap-2">
                    <x-button class="btn-ghost btn-sm" :label="__('Choose another file')"
                        wire:click="$set('step', 1)" />
                    <x-button class="btn-primary" icon="o-check" :label="__('Import')"
                        :disabled="$this->tally['undecided'] > 0" wire:click="import" spinner="import" />
                </div>
            </div>
        </div>
    @endif

    {{-- ── Step 3 · what was done ───────────────────────────────────────────── --}}
    @if ($step === 3 && $this->importRun)
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <x-admin.shared.stat-card :label="__('Created')" :value="(string) $this->importRun->new_count"
                    icon="o-user-plus" color="success" />
                <x-admin.shared.stat-card :label="__('Updated')" :value="(string) $this->importRun->updated_count"
                    icon="o-arrow-path" color="primary" />
                <x-admin.shared.stat-card :label="__('Ignored')" :value="(string) $this->importRun->skipped_count"
                    icon="o-no-symbol" />
                <x-admin.shared.stat-card :label="__('Errors')" :value="(string) $this->importRun->error_count"
                    icon="o-exclamation-triangle" :color="$this->importRun->error_count > 0 ? 'error' : 'neutral'" />
            </div>

            <x-alert icon="o-envelope" class="alert-info alert-soft">
                {{ __('Nobody has been told. Invite the new members from the members list, filtered on "not invited".') }}
                <x-slot:actions>
                    <x-button class="btn-primary btn-sm" :label="__('Go to the members list')"
                        link="{{ route('admin.users.index') }}" />
                </x-slot:actions>
            </x-alert>

            @if (count($failures) > 0)
                <x-card :title="__('Lines that could not be read')" separator>
                    <ul class="space-y-1 text-sm">
                        @foreach ($failures as $failure)
                            <li wire:key="failure-{{ $failure['line'] }}" class="opacity-70">
                                {{ __('Line :line', ['line' => $failure['line']]) }} — {{ $failure['reason'] }}
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @php
                $reported = collect($rows)->filter(fn (array $row): bool => $row['discrepancies'] !== []);
            @endphp

            @if ($reported->isNotEmpty())
                <x-card :title="__('Reported differences')"
                    :subtitle="__('What the federation says and the club did not. The licence number and the postal address were taken from the listing; the birthdate and the email address were left untouched.')" separator>
                    <ul class="space-y-2 text-sm">
                        @foreach ($reported as $line => $row)
                            <li wire:key="difference-{{ $line }}">
                                <span class="font-medium">{{ $row['firstName'] }} {{ $row['lastName'] }}</span>
                                <span class="opacity-70"> — {{ implode(' · ', $row['discrepancies']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-card :title="__('On the roster, absent from the listing')"
                :subtitle="__('Shown for information. Absence from the export does not prove a departure — a committee member who plays no interclub has never been in it.')"
                separator>
                @if ($this->absentees->isEmpty())
                    <x-admin.shared.empty :title="__('Every licensed member of the last two seasons is in the listing.')" />
                @else
                    <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($this->absentees as $member)
                            <li wire:key="absentee-{{ $member->id }}" class="text-sm">
                                <a class="link link-hover" href="{{ route('admin.users.edit', $member) }}">
                                    {{ $member->full_name }}
                                </a>
                                <span class="font-mono text-xs opacity-40"> #{{ $member->licence }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    @endif
</div>
