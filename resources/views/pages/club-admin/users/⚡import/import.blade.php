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

                    <x-file wire:model="importFile" :label="__('Affiliate listing')"
                        :aria-label="__('Affiliate listing')" accept=".csv,.txt"
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

            {{-- What the reviewer has to look at, and what they only have to know about --}}
            @if (count($this->linesToReview) > 0)
                <x-section-accordion :label="__('Needs your attention')" :count="count($this->linesToReview)"
                    color="amber" :open="true">
                    <div class="space-y-3">
                        @foreach ($this->linesToReview as $line => $row)
                            @include('pages::club-admin.users.⚡import._line-card', ['line' => $line, 'row' => $row])
                        @endforeach
                    </div>
                </x-section-accordion>
            @endif

            @if (count($this->linesReadToImport) > 0)
                <x-section-accordion :label="__('Nothing to report')" :count="count($this->linesReadToImport)"
                    color="gray" :open="false">
                    <div class="space-y-3">
                        @foreach ($this->linesReadToImport as $line => $row)
                            @include('pages::club-admin.users.⚡import._line-card', ['line' => $line, 'row' => $row])
                        @endforeach
                    </div>
                </x-section-accordion>
            @endif

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
