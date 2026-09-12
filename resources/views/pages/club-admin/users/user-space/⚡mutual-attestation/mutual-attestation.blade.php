<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Mutual attestation')"
        :subtitle="__('Your club certifies your affiliation so your mutual insurer can reimburse part of it.')" />

    @if (! $ready)
        <x-alert icon="o-clock" class="alert-info">
            {{ __('The club is still setting this up. Come back shortly, or contact the secretary.') }}
        </x-alert>
    @elseif (! $verdict->allowed && $verdict->refusal !== \App\Domains\Shared\Enums\AttestationRefusal::AlreadyIssued)
        <x-alert icon="o-exclamation-triangle" class="alert-warning">
            <div>
                <p>{{ $verdict->refusal->message() }}</p>
                @if ($verdict->balanceDue > 0)
                    <p class="mt-1">
                        {{ __('There is still :amount € outstanding on your affiliation.', ['amount' => number_format($verdict->balanceDue, 2, ',', ' ')]) }}
                        <a class="link" href="{{ route('admin.user.payments', $user) }}">{{ __('My payments') }}</a>
                    </p>
                @endif
            </div>
        </x-alert>
    @else
        <x-card>
            {{-- Step 1 — which insurer --------------------------------------}}
            @if ($step === 1)
                <div wire:key="attestation-step-mutuality">
                    <p class="mb-4 text-base-content/70">
                        {{ __('Choose your mutual insurer. The club fills in everything it knows; you complete the rest.') }}
                    </p>

                    <x-radio :label="__('My mutual insurer')" wire:model="mutuality"
                        :options="collect($offered)->map(fn ($m) => ['id' => $m->value, 'name' => $m->label()])->values()->all()" />

                    @error('mutuality')
                        <p class="mt-2 text-sm text-error">{{ $message }}</p>
                    @enderror

                    <x-slot:actions>
                        <x-button class="btn-primary" icon="o-arrow-right" :label="__('Continue')"
                            wire:click="chooseMutuality" spinner />
                    </x-slot:actions>
                </div>
            @endif

            {{-- Step 2 — what will be printed, and what is missing ----------}}
            @if ($step === 2)
                <div wire:key="attestation-step-review">
                    <h3 class="mb-3 font-semibold">{{ __('What the club will certify') }}</h3>

                    <dl class="mb-6 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm text-base-content/60">{{ __('Member') }}</dt>
                            <dd class="font-medium">{{ $preview->memberFullName }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-base-content/60">{{ __('Address') }}</dt>
                            <dd class="font-medium">{{ $preview->memberAddress }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-base-content/60">{{ __('Period covered') }}</dt>
                            <dd class="font-medium">
                                {{ $preview->periodFrom->format('d/m/Y') }} — {{ $preview->periodTo->format('d/m/Y') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-base-content/60">{{ __('Amount paid') }}</dt>
                            <dd class="font-medium">{{ number_format($preview->amountPaid, 2, ',', ' ') }} €</dd>
                        </div>
                    </dl>

                    <x-alert icon="o-shield-check" class="alert-info mb-4">
                        {{ __('Your national register number goes straight onto the document. The club does not keep it.') }}
                    </x-alert>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input :label="__('National register number')" wire:model.blur="nationalRegisterNumber"
                            placeholder="00.00.00-000.00" />
                        <x-input :label="__('Mutual membership number')" wire:model.blur="mutualMembershipNumber"
                            :hint="__('Optional — on your mutual insurer\'s card.')" />
                    </div>

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
                        {{ __('Send it to :insurer, by post or through their app. One attestation is issued per season — you can download this one again whenever you need it.', ['insurer' => $attestation->mutuality->label()]) }}
                    </p>

                    @if ($attestation->mutuality->url())
                        <p class="mb-4 text-sm">
                            <x-icon name="o-link" class="h-4 w-4" />
                            <a class="link" href="{{ $attestation->mutuality->url() }}" target="_blank" rel="noopener">
                                {{ $attestation->mutuality->url() }}
                            </a>
                        </p>
                    @endif

                    <x-slot:actions>
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
