<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Mutual attestations')"
        :subtitle="__('How the club signs them, what they are laid over, and what has been issued.')" />

    @if (! $ready)
        <x-alert icon="o-exclamation-triangle" class="alert-warning mb-6">
            {{ __('Members cannot ask for an attestation yet. Still missing: :items.', ['items' => implode(', ', $missing)]) }}
        </x-alert>
    @endif

    <x-tabs wire:model="tab">
        {{-- Settings ---------------------------------------------------------}}
        <x-tab name="settings" :label="__('Signature and seal')" icon="o-identification">
            <x-form wire:submit="saveSettings">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <x-select :label="__('Signing officer')" wire:model="signatoryUserId" :options="$signatories"
                        option-label="full_name" :placeholder="__('Choose a member')" />
                    <x-input :label="__('Club phone number')" wire:model="clubPhone"
                        :hint="__('Printed on the Solidaris form.')" />
                    <x-input :label="__('Federation or league')" wire:model="federationName"
                        :hint="__('Printed on the Partenamut form.')" />
                    <x-input :label="__('Sport practised')" wire:model="discipline" />
                </div>

                <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <x-card :title="__('Club seal')" shadow separator>
                        @if ($settings->seal_path)
                            <img class="mb-3 max-h-32" alt="{{ __('Club seal') }}"
                                src="data:image/png;base64,{{ base64_encode(file_get_contents($settings->seal_path)) }}">
                        @else
                            <x-alert icon="o-photo" class="alert-info mb-3">{{ __('No seal uploaded yet.') }}</x-alert>
                        @endif

                        <x-file wire:model="sealUpload" accept="image/png"
                            :label="__('Upload a transparent PNG')"
                            :hint="__('Saved as soon as you pick it.')" />
                        @error('sealUpload') <p class="mt-2 text-sm text-error">{{ $message }}</p> @enderror

                        <x-input class="mt-3" type="number" :label="__('Width on the document (mm)')"
                            wire:model="sealWidth" min="10" max="80" />

                    </x-card>

                    <x-card :title="__('Signature')" shadow separator>
                        @if ($settings->signature_path)
                            <img class="mb-3 max-h-32" alt="{{ __('Signature') }}"
                                src="data:image/png;base64,{{ base64_encode(file_get_contents($settings->signature_path)) }}">
                        @else
                            <x-alert icon="o-photo" class="alert-info mb-3">{{ __('No signature uploaded yet.') }}</x-alert>
                        @endif

                        <x-file wire:model="signatureUpload" accept="image/png"
                            :label="__('Upload a transparent PNG')"
                            :hint="__('Saved as soon as you pick it.')" />
                        @error('signatureUpload') <p class="mt-2 text-sm text-error">{{ $message }}</p> @enderror

                        <x-input class="mt-3" type="number" :label="__('Width on the document (mm)')"
                            wire:model="signatureWidth" min="10" max="90" />

                    </x-card>
                </div>

                <x-slot:actions>
                    <x-button class="btn-primary" type="submit" icon="o-check" :label="__('Save')" spinner="saveSettings" />
                </x-slot:actions>
            </x-form>
        </x-tab>

        {{-- Templates --------------------------------------------------------}}
        <x-tab name="templates" :label="__('Insurer forms')" icon="o-document-text">
            <x-alert icon="o-information-circle" class="alert-info mb-4">
                {{ __('Field positions are worked out from each form\'s own wording, so a revised form usually just works. Any label that could not be found is listed below — an insurer with one is not offered to members.') }}
            </x-alert>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Mutual insurer') }}</th>
                            <th>{{ __('Form held') }}</th>
                            <th>{{ __('Unresolved labels') }}</th>
                            <th>{{ __('Offered to members') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (\App\Domains\Shared\Enums\Mutuality::withOfficialForm() as $mutuality)
                            <tr wire:key="template-{{ $mutuality->value }}">
                                <td class="font-medium">{{ $mutuality->label() }}</td>
                                <td>
                                    @if ($templates->has($mutuality->value))
                                        {{ $templates[$mutuality->value]->original_name }}
                                        <span class="text-base-content/60">
                                            · {{ $templates[$mutuality->value]->updated_at->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="text-base-content/60">{{ __('None') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($templates->has($mutuality->value) && ! $templates[$mutuality->value]->isUsable())
                                        <x-badge class="badge-error"
                                            :value="implode(', ', $templates[$mutuality->value]->unresolved_fields)" />
                                    @elseif ($templates->has($mutuality->value))
                                        <x-badge class="badge-success" :value="__('All found')" />
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if (in_array($mutuality, $offered, true))
                                        <x-icon name="o-check-circle" class="h-5 w-5 text-success" />
                                    @else
                                        <x-icon name="o-x-circle" class="h-5 w-5 text-base-content/40" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-form class="mt-6" wire:submit="uploadTemplate">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <x-select :label="__('Mutual insurer')" wire:model="templateFor"
                        :options="collect(\App\Domains\Shared\Enums\Mutuality::withOfficialForm())->map(fn ($m) => ['id' => $m->value, 'name' => $m->label()])->values()->all()"
                        :placeholder="__('Choose a mutual insurer')" />
                    <x-file wire:model="templateUpload" accept="application/pdf" :label="__('The form, as the insurer publishes it')" />
                </div>

                <x-slot:actions>
                    <x-button class="btn-primary" type="submit" icon="o-arrow-up-tray"
                        :label="__('Install this form')" spinner="uploadTemplate" />
                </x-slot:actions>
            </x-form>
        </x-tab>

        {{-- History ----------------------------------------------------------}}
        <x-tab name="history" :label="__('Issued')" icon="o-clock">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Reference') }}</th>
                            <th>{{ __('Member') }}</th>
                            <th>{{ __('Mutual insurer') }}</th>
                            <th>{{ __('Season') }}</th>
                            <th>{{ __('Issued on') }}</th>
                            <th>{{ __('Amount paid') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $attestation)
                            <tr wire:key="attestation-{{ $attestation->id }}"
                                @class(['opacity-60' => $attestation->isRevoked()])>
                                <td class="font-mono">{{ $attestation->reference }}</td>
                                <td>{{ $attestation->user->full_name }}</td>
                                <td>{{ $attestation->mutuality->label() }}</td>
                                <td>{{ $attestation->season->name }}</td>
                                <td>{{ $attestation->issued_at->format('d/m/Y') }}</td>
                                <td>{{ number_format($attestation->amount_certified, 2, ',', ' ') }} €</td>
                                <td class="text-right">
                                    @if ($attestation->isRevoked())
                                        <x-badge class="badge-error" :value="__('Revoked')" />
                                    @else
                                        @if ($attestation->isDownloadable())
                                            <x-button class="btn-ghost btn-sm" icon="o-arrow-down-tray"
                                                :tooltip="__('Download')"
                                                link="{{ route('admin.user.attestation.download', $attestation) }}" external />
                                        @endif
                                        @can('revoke', $attestation)
                                            <x-button class="btn-ghost btn-sm text-error" icon="o-x-circle"
                                                :tooltip="__('Revoke')"
                                                wire:click="$set('revoking', {{ $attestation->id }})" />
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty-state :title="__('Nothing issued yet')" icon="o-document-check" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $history->links() }}</div>
        </x-tab>
    </x-tabs>

    {{-- Revoking asks for a reason: an attestation withdrawn without one leaves
         nobody able to answer the member who asks why. --}}
    <x-app-modal wire:model="revoking" :open="$revoking" :title="__('Revoke this attestation')" separator>
        <p class="mb-4 text-base-content/70">
            {{ __('The document stops being valid and its file is deleted. The member may then ask for a new one.') }}
        </p>

        <x-input :label="__('Reason')" wire:model="revocationReason"
            :placeholder="__('Wrong mutual insurer, amount changed, …')" />

        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('revoking', null)" />
            <x-button class="btn-error" icon="o-x-circle" :label="__('Revoke')" wire:click="revoke" spinner />
        </x-slot:actions>
    </x-app-modal>
</div>
