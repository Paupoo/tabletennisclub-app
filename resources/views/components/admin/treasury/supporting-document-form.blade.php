@props([
    'editing' => null,
    'files' => [],
    'removedFileIds' => [],
    'disabled' => false,
])

{{--
    Les champs d'une pièce justificative, pour les trois écrans qui en classent
    une : la liste des pièces, le tiroir « Justifier » d'une ligne de banque et
    un mouvement de caisse. L'état vit dans le composant Livewire hôte, via le
    trait EditsSupportingDocument.

    Pas de sens à choisir : il se déduit de la catégorie (Dépense — … /
    Recette — …). Les cotisations et les entraînements n'y figurent pas, le
    site les compte déjà.
--}}
<div class="space-y-4">
    <x-select wire:model="documentCategory" :label="__('Category')" :placeholder="__('Choose…')"
        :options="\App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument::categoryOptions()"
        :disabled="$disabled" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-input wire:model="documentDate" :label="__('Date of the document')" type="date" :disabled="$disabled" />
        <x-input wire:model="documentAmount" :label="__('Amount')" inputmode="decimal" suffix="€"
            :hint="__('VAT included, as on the document')" :disabled="$disabled" />
    </div>

    <x-input wire:model="documentCounterparty" :label="__('Counterparty')" maxlength="255"
        :placeholder="__('e.g. AFTT, Colruyt, the municipality')" :disabled="$disabled" />

    <x-input wire:model="documentLabel" :label="__('Label')" maxlength="255"
        :placeholder="__('e.g. hall rental, third quarter')" :disabled="$disabled" />

    @unless ($disabled)
        <div>
            <x-document-upload>
                <x-file wire:model="documentFiles" :label="__('Files')" multiple accept=".pdf,.jpg,.jpeg,.png,.webp"
                    :hint="__('The invoice, the ticket, the letter — or a screenshot of the statement for bank fees. PDF, JPG, PNG or WebP, :size each.', ['size' => \App\Support\UploadLimits::documentLabel()])" />
            </x-document-upload>
            @error('documentFiles.*')
                <p class="mt-1 text-sm text-error">{{ $message }}</p>
            @enderror

            @if ($editing)
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($editing->files->whereNotIn('id', $removedFileIds) as $file)
                        <li wire:key="existing-document-file-{{ $file->id }}" class="flex items-center justify-between gap-2">
                            <span class="truncate">{{ $file->original_name }}</span>
                            <x-button icon="o-x-mark" class="btn-ghost btn-xs" :title="__('Remove')" :aria-label="__('Remove')"
                                wire:click="removeExistingDocumentFile({{ $file->id }})" />
                        </li>
                    @endforeach
                </ul>
            @endif
            @if (count($files) > 0)
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($files as $index => $upload)
                        <li wire:key="new-document-file-{{ $index }}" class="flex items-center justify-between gap-2">
                            <span class="truncate">{{ $upload->getClientOriginalName() }}</span>
                            <x-button icon="o-x-mark" class="btn-ghost btn-xs" :title="__('Remove')" :aria-label="__('Remove')"
                                wire:click="removeNewDocumentFile({{ $index }})" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endunless
</div>
