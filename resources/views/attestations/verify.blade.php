<x-guest-layout :title="__('Verify an attestation')">
    <div class="mx-auto max-w-2xl px-4 py-16">
        @if ($attestation->isRevoked())
            <x-alert icon="o-x-circle" class="alert-error mb-6">
                {{ __('This attestation has been revoked and is no longer valid.') }}
            </x-alert>
        @else
            <x-alert icon="o-check-circle" class="alert-success mb-6">
                {{ __('This attestation was issued by the club and is valid.') }}
            </x-alert>
        @endif

        <x-card>
            <x-slot:title>{{ $attestation->reference }}</x-slot:title>

            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-base-content/60">{{ __('Member') }}</dt>
                    <dd class="font-medium">{{ $attestation->user->full_name }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-base-content/60">{{ __('Season') }}</dt>
                    <dd class="font-medium">{{ $attestation->season->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-base-content/60">{{ __('Period covered') }}</dt>
                    <dd class="font-medium">
                        {{ $attestation->period_from->format('d/m/Y') }} — {{ $attestation->period_to->format('d/m/Y') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-base-content/60">{{ __('Amount paid') }}</dt>
                    <dd class="font-medium">{{ number_format($attestation->amount_certified, 2, ',', ' ') }} €</dd>
                </div>
                <div>
                    <dt class="text-sm text-base-content/60">{{ __('Sport practised') }}</dt>
                    <dd class="font-medium">{{ $attestation->discipline }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-base-content/60">{{ __('Issued on') }}</dt>
                    <dd class="font-medium">{{ $attestation->issued_at->format('d/m/Y') }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm text-base-content/60">{{ __('Signed by') }}</dt>
                    <dd class="font-medium">{{ $attestation->signatory_name }}</dd>
                </div>
            </dl>
        </x-card>
    </div>
</x-guest-layout>
