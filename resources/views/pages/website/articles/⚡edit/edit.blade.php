<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator
        :title="$newsPostId ? 'Modifier l\'article' : 'Nouvel article'">
        <x-slot:actions>
            <x-button class="btn-ghost" icon="o-arrow-left" label="Annuler"
                link="{{ route('admin.website.articles.index') }}" />
        </x-slot:actions>
    </x-header>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- ── Colonne gauche : métadonnées ──────────────────────────── --}}
        <div class="space-y-5 lg:col-span-1">

            <x-card class="shadow-sm" :title="__('Identity')">
                <div class="space-y-4">
                    <x-input label="Titre" wire:model.live.debounce.300ms="title"
                        placeholder="Titre de l'article" />
                    <x-input label="Slug" wire:model="slug"
                        placeholder="mon-article" />
                    <x-select :label="__('Category')" :options="$categoryOptions"
                        wire:model="category" placeholder="Choisir…" />
                    <x-select label="Statut" :options="$statusOptions"
                        wire:model="status" />
                </div>
            </x-card>

            {{-- Image --}}
            <x-card class="shadow-sm" :title="__('Featured image')">
                {{-- Re-key on the stored path so removing the image resets the picker. --}}
                <div wire:key="featured-image-{{ $existingImage ?? 'none' }}">
                    <x-image-focal-picker
                        :preview="($image && $image->isPreviewable() ? $image->temporaryUrl() : null)
                            ?? ($existingImage ? Storage::url($existingImage) : null)"
                        :focal-x="$imageFocalX" :focal-y="$imageFocalY">
                        <x-slot:delete>
                            @if ($existingImage)
                                <x-button class="btn-ghost btn-sm text-error"
                                    icon="o-trash" :label="__('Delete the image')"
                                    wire:click="removeImage" />
                            @endif
                        </x-slot:delete>
                    </x-image-focal-picker>
                </div>
                @error('image')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
                @error('imageFocalY')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </x-card>
        </div>

        {{-- ── Colonne droite : éditeur Markdown split ───────────────── --}}
        <x-card class="shadow-sm lg:col-span-2" title="Contenu">
            <x-slot:subtitle>
                <span class="text-xs text-gray-400">{{ __('Markdown — live preview') }}</span>
            </x-slot:subtitle>

            {{-- Guide syntaxe --}}
            <div x-data="{ open: false }" class="mb-3">
                {{-- py-1 : même plancher de 24px que partout ailleurs. --}}
                <button type="button"
                    class="flex items-center gap-1.5 py-1 text-xs text-blue-600 hover:text-blue-800"
                    @click="open = !open">
                    <x-heroicon-o-question-mark-circle class="h-3.5 w-3.5" />
                    <span x-text="open ? 'Masquer l\'aide Markdown' : 'Aide Markdown'"></span>
                </button>
                <div x-show="open" x-transition class="mt-2 rounded-lg border border-blue-100 bg-blue-50 p-3">
                    <div class="grid grid-cols-2 gap-x-6 gap-y-1 font-mono text-xs text-gray-700">
                        <div><span class="text-blue-700"># Titre 1</span> <span class="text-gray-400">← espace obligatoire</span></div>
                        <div><span class="text-blue-700">**gras**</span> → <strong>gras</strong></div>
                        <div><span class="text-blue-700">## Titre 2</span></div>
                        <div><span class="text-blue-700">*italique*</span> → <em>italique</em></div>
                        <div><span class="text-blue-700">### Titre 3</span></div>
                        <div><span class="text-blue-700">[lien](https://…)</span></div>
                        <div><span class="text-blue-700">- item</span>{{ __('→ bullet list') }}</div>
                        <div><span class="text-blue-700">1. item</span>{{ __('→ numbered list') }}</div>
                        <div><span class="text-blue-700">> citation</span> → blockquote</div>
                        <div><span class="text-blue-700">`code`</span> → <code>code</code></div>
                    </div>
                    <p class="mt-2 text-xs text-blue-600"><x-icon name="o-exclamation-triangle" class="mb-0.5 inline h-3.5 w-3.5" /> {{ __('Headings require a space after the # :') }}<code class="bg-blue-100 px-1">## Mon titre</code> et non <code class="bg-red-100 px-1">##Mon titre</code></p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2" style="min-height:420px">
                {{-- Éditeur --}}
                <div class="flex flex-col">
                    <label class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Edit') }}</label>
                    <textarea
                        wire:model.live.debounce.400ms="content"
                        class="flex-1 resize-none rounded-lg border border-base-300 bg-gray-50 p-3 font-mono text-sm text-gray-800 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400"
                        placeholder="## Mon titre&#10;&#10;Rédigez votre article en Markdown…&#10;&#10;- point 1&#10;- point 2"
                        style="min-height:380px"></textarea>
                    @error('content')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Prévisualisation --}}
                <div class="flex flex-col">
                    <label class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Preview') }}</label>
                    <div class="prose prose-sm flex-1 overflow-y-auto rounded-lg border border-base-300 bg-white p-4 text-gray-800"
                        style="min-height:380px; max-height:580px">
                        {!! $markdownPreview !!}
                    </div>
                </div>
            </div>
        </x-card>
    </div>

    {{-- ── Actions ──────────────────────────────────────────────────────── --}}
    <div class="mt-6 flex justify-end gap-3">
        <x-button class="btn-ghost" icon="o-arrow-left" label="Annuler"
            link="{{ route('admin.website.articles.index') }}" />
        <x-button class="btn-primary" icon="o-check" label="Enregistrer"
            wire:click="save" wire:loading.attr="disabled" />
    </div>
</div>
