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

        {{-- ── Colonne droite : éditeur ──────────────────────────────── --}}
        <x-card class="shadow-sm lg:col-span-2" title="Contenu">
            <x-markdown-editor model="content" :label="__('Article body')"
                image-model="contentImage" image-action="storeContentImage" />
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
