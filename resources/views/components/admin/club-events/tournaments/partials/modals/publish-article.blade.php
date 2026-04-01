<x-modal wire:model="showPublishModal" title="{{ __('Publish article') }}" separator>
    <div class="space-y-4">
        {{-- Warning Alert --}}
        <div class="p-4 bg-warning/10 border border-warning/20 rounded-xl flex gap-4">
            <x-icon name="o-exclamation-triangle" class="w-6 h-6 text-warning" />
            <div class="text-sm">
                <p>
                    {{ __('The article will be visible on the public homepage.') }}
                </p>
                @if ($publicRegistration)
                <p class="mt-1 font-semibold text-warning">
                    {{ __('Visitors will be able to register directly via the "Registrate" button.') }}
                </p>
                @endif
            </div>
        </div>

        {{-- Publication Date --}}
        <x-datepicker label="{{ __('Schedule publication') }}" wire:model="publicationDate" icon="o-calendar"
            hint="{{ __('Leave empty for immediate publication') }}" />

        {{-- Tags Selection --}}
        <x-choices label="{{ __('Tags') }}" wire:model="selectedTags" :options="$tagOptions" />
    </div>

    <x-slot:actions>
        <x-button label="{{ __('Cancel') }}" @click="$wire.showPublishModal = false" />
        <x-button label="{{ __('Confirm publication') }}" icon="o-check" class="btn-primary"
            wire:click="publishArticle" spinner="publishArticle" />
    </x-slot:actions>
</x-modal>