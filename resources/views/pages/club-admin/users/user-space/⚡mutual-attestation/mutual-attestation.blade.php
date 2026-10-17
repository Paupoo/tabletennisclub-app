<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Mutual attestations')"
        :subtitle="$hasChoice
            ? __('Your club certifies your affiliation and your training camps so your mutual insurer can reimburse part of them.')
            : __('Your club certifies your affiliation so your mutual insurer can reimburse part of it.')" />

    @if (! $ready)
        <x-alert icon="o-clock" class="alert-info">
            {{ __('The club is still setting this up. Come back shortly, or contact the secretary.') }}
        </x-alert>
    @elseif ($document === '')
        {{-- Step 0 — which document -------------------------------------------}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2" wire:key="attestation-documents">
            @foreach ($cards as $card)
                @php
                    $cardVerdict = $card['verdict'];
                @endphp
                <x-card wire:key="attestation-card-{{ $card['key'] }}" :title="$card['title']" :subtitle="$card['subtitle']" shadow separator>
                    @if ($card['attestation'])
                        <p class="text-sm text-base-content/70">
                            {{ __('Issued on :date · reference :reference', ['date' => $card['attestation']->issued_at->format('d/m/Y'), 'reference' => $card['attestation']->reference]) }}
                        </p>
                    @elseif (! $cardVerdict->allowed)
                        <p class="text-sm text-base-content/70">
                            @if ($cardVerdict->refusal === \App\Domains\Shared\Enums\AttestationRefusal::CampNotOver)
                                {{ __('Available from :date.', ['date' => $cardVerdict->availableOn->format('d/m/Y')]) }}
                            @else
                                {{ $cardVerdict->refusal->message() }}
                                @if ($cardVerdict->balanceDue > 0)
                                    {{ __('Still to pay: :amount €.', ['amount' => number_format($cardVerdict->balanceDue, 2, ',', ' ')]) }}
                                @endif
                            @endif
                        </p>
                    @else
                        <p class="text-sm text-base-content/70">{{ __('Available.') }}</p>
                    @endif

                    <x-slot:actions>
                        @if ($card['attestation'])
                            <x-button class="btn-ghost" icon="o-eye" :label="__('See')"
                                wire:click="chooseDocument('{{ $card['key'] }}')" spinner />
                        @elseif ($cardVerdict->allowed)
                            <x-button class="btn-primary" icon="o-arrow-right" :label="__('Get this attestation')"
                                wire:click="chooseDocument('{{ $card['key'] }}')" spinner />
                        @elseif ($cardVerdict->balanceDue > 0)
                            <x-button class="btn-ghost" icon="o-credit-card" :label="__('My payments')"
                                link="{{ route('admin.user.payments', $user) }}" />
                        @endif
                    </x-slot:actions>
                </x-card>
            @endforeach
        </div>
    @elseif (! $verdict->allowed && $verdict->refusal !== \App\Domains\Shared\Enums\AttestationRefusal::AlreadyIssued && ! $attestation)
        <x-alert icon="o-exclamation-triangle" class="alert-warning">
            <div>
                <p>{{ $verdict->refusal->message() }}</p>
                @if ($verdict->balanceDue > 0)
                    <p class="mt-1">
                        {{ $verdict->refusal === \App\Domains\Shared\Enums\AttestationRefusal::CampBalanceDue
                            ? __('There is still :amount € outstanding on this training camp.', ['amount' => number_format($verdict->balanceDue, 2, ',', ' ')])
                            : __('There is still :amount € outstanding on your affiliation.', ['amount' => number_format($verdict->balanceDue, 2, ',', ' ')]) }}
                        <a class="link" href="{{ route('admin.user.payments', $user) }}">{{ __('My payments') }}</a>
                    </p>
                @endif
            </div>
        </x-alert>

        @if ($hasChoice)
            <div class="mt-4">
                <x-button class="btn-ghost" icon="o-arrow-left" :label="__('Back')" wire:click="backToDocuments" />
            </div>
        @endif
    @else
        <x-card>
            {{-- Step 1 — which insurer --------------------------------------}}
            @if ($step === 1)
                <div wire:key="attestation-step-mutuality">
                    <p class="mb-4 text-base-content/70">
                        {{ __('Choose your mutual insurer. The club fills in everything it knows; you complete the rest.') }}
                    </p>

                    <x-radio :label="__('My mutual insurer')" wire:model.live="mutuality"
                        :options="collect($offered)->map(fn ($m) => ['id' => $m->value, 'name' => $m->labelFor($kind)])->values()->all()" />

                    {{-- What each insurer pays for a camp, told rather than
                         enforced: the statutes change without notice. --}}
                    @if ($isCamp && ($conditions = \App\Domains\Shared\Enums\Mutuality::tryFrom($mutuality)?->campConditions()))
                        <p class="mt-3 text-sm text-base-content/70">
                            <x-icon name="o-information-circle" class="h-4 w-4" /> {{ $conditions }}
                        </p>
                    @endif

                    @error('mutuality')
                        <p class="mt-2 text-sm text-error">{{ $message }}</p>
                    @enderror

                    <x-slot:actions>
                        @if ($hasChoice)
                            <x-button class="btn-ghost" icon="o-arrow-left" :label="__('Back')" wire:click="back" />
                        @endif
                        <x-button class="btn-primary" icon="o-arrow-right" :label="__('Continue')"
                            wire:click="chooseMutuality" spinner />
                    </x-slot:actions>
                </div>
            @endif

            {{-- Step 2 — what will be printed, and what is missing ----------}}
            @if ($step === 2 && $preview)
                <div wire:key="attestation-step-review">
                    <h3 class="mb-3 font-semibold">{{ __('What the club will certify') }}</h3>

                    <dl class="mb-6 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm text-base-content/60">{{ $isCamp ? __('Participant') : __('Member') }}</dt>
                            <dd class="font-medium">{{ $preview->memberFullName }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-base-content/60">{{ __('Address') }}</dt>
                            <dd class="font-medium">{{ $preview->memberAddress }}</dd>
                        </div>
                        @if ($isCamp)
                            <div>
                                <dt class="text-sm text-base-content/60">{{ __('Training camp') }}</dt>
                                <dd class="font-medium">{{ $preview->campName }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm text-base-content/60">{{ __('Number of days') }}</dt>
                                <dd class="font-medium">{{ $preview->campDays->label() }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm text-base-content/60">{{ $isCamp ? __('Period') : __('Period covered') }}</dt>
                            <dd class="font-medium">
                                {{ $preview->periodFrom->format('d/m/Y') }} — {{ $preview->periodTo->format('d/m/Y') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-base-content/60">{{ __('Amount paid') }}</dt>
                            <dd class="font-medium">{{ number_format($preview->amountPaid, 2, ',', ' ') }} €</dd>
                        </div>
                    </dl>

                    @if ($needsNationalRegisterNumber || $needsMutualNumber || $needsDisability)
                        <x-alert icon="o-shield-check" class="alert-info mb-4">
                            {{ __('What you type here goes straight onto the document. The club does not keep it.') }}
                        </x-alert>

                        {{-- Each question is shown only where the chosen document
                             has a box for it, and required wherever it does. --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            @if ($needsNationalRegisterNumber)
                                <x-input :label="__('National register number')" wire:model.blur="nationalRegisterNumber"
                                    placeholder="00.00.00-000.00" :required="! $nationalRegisterNumberIsOptional"
                                    :hint="$nationalRegisterNumberIsOptional ? __('Optional. Only if the organisation you send it to asks for it.') : null" />
                            @endif

                            @if ($needsMutualNumber)
                                <x-input :label="__('Mutual membership number')" wire:model.blur="mutualMembershipNumber"
                                    :hint="__('On your mutual insurer\'s card.')" required />
                            @endif

                            @if ($needsDisability)
                                <x-radio :label="__('Is the participant recognised as disabled?')" wire:model="recognisedDisabled"
                                    :options="[['id' => 'yes', 'name' => __('Yes')], ['id' => 'no', 'name' => __('No')]]" />
                                @error('recognisedDisabled')
                                    <p class="text-sm text-error sm:col-span-2">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>
                    @endif

                    <x-slot:actions>
                        <x-button class="btn-ghost" icon="o-arrow-left" :label="__('Back')" wire:click="back" />
                        <x-button class="btn-primary" icon="o-document-check" :label="__('Generate my attestation')"
                            wire:click="generate" spinner />
                    </x-slot:actions>
                </div>
            @endif

            {{-- Step 3 — the document ---------------------------------------}}
            @if ($step === 3 && $attestation)
                <div wire:key="attestation-step-result">
                    <x-alert icon="o-check-circle" class="alert-success mb-4">
                        {{ __('Your attestation is ready. Reference :reference.', ['reference' => $attestation->reference]) }}
                    </x-alert>

                    <p class="mb-4 text-base-content/70">
                        @if ($isCamp && $attestation->mutuality === \App\Domains\Shared\Enums\Mutuality::Other)
                            {{ __('Send it to your mutual insurer, or to the organisation that reimburses the camp. One attestation is issued per training camp — you can download this one again whenever you need it.') }}
                        @elseif ($isCamp)
                            {{ __('Send it to :insurer, by post or through their app. One attestation is issued per training camp — you can download this one again whenever you need it.', ['insurer' => $attestation->mutuality->label()]) }}
                        @else
                            {{ __('Send it to :insurer, by post or through their app. One attestation is issued per season — you can download this one again whenever you need it.', ['insurer' => $attestation->mutuality->label()]) }}
                        @endif
                    </p>

                    @if ($attestation->mutuality->url($kind))
                        <p class="mb-4 text-sm">
                            <x-icon name="o-link" class="h-4 w-4" />
                            <a class="link" href="{{ $attestation->mutuality->url($kind) }}" target="_blank" rel="noopener">
                                {{ $attestation->mutuality->url($kind) }}
                            </a>
                        </p>
                    @endif

                    <x-slot:actions>
                        @if ($hasChoice)
                            <x-button class="btn-ghost" icon="o-arrow-left" :label="__('Other documents')" wire:click="backToDocuments" />
                        @endif
                        @if ($attestation->isDownloadable())
                            <x-button class="btn-primary" icon="o-arrow-down-tray" :label="__('Download')"
                                link="{{ route('admin.user.attestation.download', $attestation) }}" external />
                        @else
                            <x-button class="btn-ghost" icon="o-archive-box" :label="__('No longer available')" disabled />
                        @endif
                    </x-slot:actions>
                </div>
            @endif
        </x-card>
    @endif
</div>
