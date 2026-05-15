<div class="mt-8 space-y-6 animate-in fade-in duration-500">

    <x-card title="{{ __('Public article') }}" separator class="shadow-sm">
        <x-slot:menu>
            @if ($publishedArticleId)
                @if ($articleSavedStatus === 'published')
                    <x-badge value="{{ __('Published') }}" class="badge-success badge-sm" icon="o-globe-alt" />
                @else
                    <x-badge value="{{ __('Draft') }}" class="badge-warning badge-sm" icon="o-document-text" />
                @endif
            @endif
            <x-button label="{{ $publishedArticleId ? __('Update article') : __('Save article') }}" icon="o-document-text" class="btn-primary btn-sm"
                @click="$wire.showPublishModal = true" />
        </x-slot:menu>

        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="{{ __('Title') }}" placeholder="Ex: Grand Tournoi de Printemps 2026"
                    wire:model="articleTitle" />
                <div class="space-y-1">
                    @if ($articleExistingImage)
                        <div class="relative">
                            <img src="{{ Storage::url($articleExistingImage) }}"
                                alt="Image de couverture"
                                class="w-full rounded-xl object-cover max-h-32" />
                            <x-button icon="o-trash" class="btn-circle btn-xs btn-error absolute top-2 right-2"
                                wire:click="$set('articleExistingImage', null)" />
                        </div>
                    @else
                        <x-file wire:model="articleImage" label="{{ __('Cover image') }}"
                            accept="image/*" hint="JPG, PNG, WebP — max 4 Mo" />
                        @error('articleImage')
                            <p class="text-xs text-error">{{ $message }}</p>
                        @enderror
                    @endif
                </div>
            </div>

            {{-- Markdown editor --}}
            <div x-data="{ open: false }">
                <button type="button"
                    class="flex items-center gap-1.5 text-xs text-primary hover:opacity-80 mb-2"
                    @click="open = !open">
                    <x-icon name="o-question-mark-circle" class="h-3.5 w-3.5" />
                    <span x-text="open ? '{{ __('Hide Markdown help') }}' : '{{ __('Markdown help') }}'"></span>
                </button>
                <div x-show="open" x-transition class="mb-3 rounded-lg border border-primary/20 bg-primary/5 p-3">
                    <div class="grid grid-cols-2 gap-x-6 gap-y-1 font-mono text-xs text-base-content/70">
                        <div><span class="text-primary"># Titre 1</span></div>
                        <div><span class="text-primary">**gras**</span> → <strong>gras</strong></div>
                        <div><span class="text-primary">## Titre 2</span></div>
                        <div><span class="text-primary">*italique*</span> → <em>italique</em></div>
                        <div><span class="text-primary">- item</span> → liste à puces</div>
                        <div><span class="text-primary">[lien](https://…)</span></div>
                        <div><span class="text-primary">1. item</span> → liste numérotée</div>
                        <div><span class="text-primary">`code`</span> → <code>code</code></div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2" style="min-height: 320px">
                <div class="flex flex-col">
                    <label class="mb-1 text-xs font-semibold uppercase tracking-wide opacity-50">{{ __('Edit') }}</label>
                    <textarea
                        wire:model.live.debounce.400ms="articleContent"
                        class="flex-1 resize-none rounded-lg border border-base-300 bg-base-200/50 p-3 font-mono text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        placeholder="## Mon titre&#10;&#10;Décrivez le tournoi en Markdown…"
                        style="min-height: 280px"></textarea>
                    @error('articleContent')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col">
                    <label class="mb-1 text-xs font-semibold uppercase tracking-wide opacity-50">{{ __('Preview') }}</label>
                    <div class="prose prose-sm flex-1 overflow-y-auto rounded-lg border border-base-300 bg-base-100 p-4"
                        style="min-height: 280px; max-height: 480px">
                        {!! $markdownPreview !!}
                    </div>
                </div>
            </div>
        </div>
    </x-card>

    <div class="flex items-center justify-between">
        <x-button
            label="{{ __('Skip — no article') }}"
            icon="o-forward"
            class="btn-ghost btn-sm"
            wire:click="$set('step', '3')" />

        <x-button
            label="{{ __('Next: Validate') }}"
            icon="o-arrow-right"
            class="btn-primary btn-sm"
            wire:click="$set('step', '3')" />
    </div>

</div>
