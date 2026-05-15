<x-modal wire:model="showPublishModal" title="{{ __('Save article') }}" separator>
    <div class="space-y-4">
        <div class="p-4 bg-info/10 border border-info/20 rounded-xl flex gap-4">
            <x-icon name="o-information-circle" class="w-6 h-6 text-info shrink-0" />
            <div class="text-sm">
                <p>
                    {{ __('Save as a draft to include a link in invitation emails, or publish it immediately on the website.') }}
                </p>
            </div>
        </div>

        <x-choices label="{{ __('Tags') }}" wire:model="selectedTags" :options="$tagOptions" />
    </div>

    <x-slot:actions>
        <x-button label="{{ __('Cancel') }}" @click="$wire.showPublishModal = false" />
        <x-button label="{{ __('Save as draft') }}" icon="o-document-text" class="btn-ghost"
            wire:click="publishArticle('draft')" spinner="publishArticle" />
        <x-button label="{{ __('Publish now') }}" icon="o-globe-alt" class="btn-primary"
            wire:click="publishArticle('published')" spinner="publishArticle" />
    </x-slot:actions>
</x-modal>
