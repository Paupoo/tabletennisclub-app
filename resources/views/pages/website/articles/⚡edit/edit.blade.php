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

            <x-card class="border-gray-200 shadow-sm" title="Identité">
                <div class="space-y-4">
                    <x-input label="Titre" wire:model.live.debounce.300ms="title"
                        placeholder="Titre de l'article" />
                    <x-input label="Slug" wire:model="slug"
                        placeholder="mon-article" />
                    <x-select label="Catégorie" :options="$categoryOptions"
                        wire:model="category" placeholder="Choisir…" />
                    <x-select label="Statut" :options="$statusOptions"
                        wire:model="status" />
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700">Visible publiquement</span>
                        <x-toggle wire:model="isPublic" />
                    </div>
                </div>
            </x-card>

            {{-- Image --}}
            <x-card class="border-gray-200 shadow-sm" title="Image à la une">
                @if ($existingImage)
                    <div class="mb-3">
                        <img src="{{ Storage::url($existingImage) }}"
                            alt="Image actuelle"
                            class="w-full rounded-lg object-cover" style="max-height:180px" />
                        <x-button class="btn-ghost btn-sm mt-2 text-error w-full"
                            icon="o-trash" label="Supprimer l'image"
                            wire:click="removeImage" />
                    </div>
                @endif
                <x-file wire:model="image" label="{{ $existingImage ? 'Remplacer' : 'Choisir une image' }}"
                    accept="image/*" hint="JPG, PNG, WebP — max 4 Mo" />
                @error('image')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </x-card>
        </div>

        {{-- ── Colonne droite : éditeur Markdown split ───────────────── --}}
        <x-card class="border-gray-200 shadow-sm lg:col-span-2" title="Contenu">
            <x-slot:subtitle>
                <span class="text-xs text-gray-400">Markdown — prévisualisation en direct</span>
            </x-slot:subtitle>

            {{-- Guide syntaxe --}}
            <div x-data="{ open: false }" class="mb-3">
                <button type="button"
                    class="flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-800"
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
                        <div><span class="text-blue-700">- item</span> → liste à puces</div>
                        <div><span class="text-blue-700">1. item</span> → liste numérotée</div>
                        <div><span class="text-blue-700">> citation</span> → blockquote</div>
                        <div><span class="text-blue-700">`code`</span> → <code>code</code></div>
                    </div>
                    <p class="mt-2 text-[11px] text-blue-600">⚠️ Les titres nécessitent un espace après les # : <code class="bg-blue-100 px-1">## Mon titre</code> et non <code class="bg-red-100 px-1">##Mon titre</code></p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2" style="min-height:420px">
                {{-- Éditeur --}}
                <div class="flex flex-col">
                    <label class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Édition</label>
                    <textarea
                        wire:model.live.debounce.400ms="content"
                        class="flex-1 resize-none rounded-lg border border-gray-200 bg-gray-50 p-3 font-mono text-sm text-gray-800 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400"
                        placeholder="## Mon titre&#10;&#10;Rédigez votre article en Markdown…&#10;&#10;- point 1&#10;- point 2"
                        style="min-height:380px"></textarea>
                    @error('content')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Prévisualisation --}}
                <div class="flex flex-col">
                    <label class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Aperçu</label>
                    <div class="prose prose-sm flex-1 overflow-y-auto rounded-lg border border-gray-200 bg-white p-4 text-gray-800"
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
