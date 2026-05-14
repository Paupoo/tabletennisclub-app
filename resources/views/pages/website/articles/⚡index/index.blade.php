<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator title="Articles">
        <x-slot:actions>
            <x-button class="btn-primary" icon="o-plus" label="Nouvel article"
                link="{{ route('admin.website.articles.create') }}" />
        </x-slot:actions>
    </x-header>

    {{-- ── Cartes stats ──────────────────────────────────────────────── --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-card class="border-gray-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100">
                    <x-heroicon-o-document-text class="h-5 w-5 text-gray-600" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats->total ?? 0 }}</p>
                    <p class="text-xs text-gray-400">Total</p>
                </div>
            </div>
        </x-card>
        <x-card class="border-gray-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-green-100">
                    <x-heroicon-o-check-circle class="h-5 w-5 text-green-600" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-green-700">{{ $stats->published ?? 0 }}</p>
                    <p class="text-xs text-gray-400">Publiés</p>
                </div>
            </div>
        </x-card>
        <x-card class="border-gray-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-yellow-100">
                    <x-heroicon-o-pencil-square class="h-5 w-5 text-yellow-600" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-yellow-700">{{ $stats->draft ?? 0 }}</p>
                    <p class="text-xs text-gray-400">Brouillons</p>
                </div>
            </div>
        </x-card>
        <x-card class="border-gray-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100">
                    <x-heroicon-o-archive-box class="h-5 w-5 text-gray-500" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-600">{{ $stats->archived ?? 0 }}</p>
                    <p class="text-xs text-gray-400">Archivés</p>
                </div>
            </div>
        </x-card>
    </div>

    {{-- ── Filtres ────────────────────────────────────────────────────── --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-input class="flex-1" clearable icon="o-magnifying-glass"
            placeholder="Rechercher un article…"
            wire:model.live.debounce.250ms="search" />
        <x-select :options="$categoryOptions" placeholder="Toutes catégories"
            wire:model.live="category" class="w-44" />
        <x-select :options="$statusOptions" placeholder="Tous statuts"
            wire:model.live="status" class="w-36" />
    </div>

    {{-- ── Tableau ────────────────────────────────────────────────────── --}}
    <x-card class="border-gray-200 shadow-sm">
        @if ($articles->isEmpty())
            <p class="py-10 text-center text-sm text-gray-400 italic">Aucun article trouvé.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                            <th class="pb-2 pr-4">Titre</th>
                            <th class="pb-2 pr-4 hidden md:table-cell">Catégorie</th>
                            <th class="pb-2 pr-4 hidden lg:table-cell">Auteur</th>
                            <th class="pb-2 pr-4">Statut</th>
                            <th class="pb-2 pr-4 hidden sm:table-cell">Date</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($articles as $article)
                            <tr class="group" wire:key="article-{{ $article->id }}">
                                <td class="py-3 pr-4">
                                    <div class="flex items-center gap-2">
                                        @if (!$article->is_public)
                                            <x-heroicon-o-lock-closed class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                        @endif
                                        <span class="font-medium text-gray-900">{{ $article->title }}</span>
                                    </div>
                                </td>
                                <td class="py-3 pr-4 hidden md:table-cell">
                                    @if ($article->category)
                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">
                                            {{ $article->category->getLabel() }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 hidden lg:table-cell text-gray-600">
                                    {{ $article->user?->first_name }} {{ $article->user?->last_name }}
                                </td>
                                <td class="py-3 pr-4">
                                    @php
                                        $badgeClass = match ($article->status) {
                                            \App\Enums\NewsPostStatusEnum::PUBLISHED => 'bg-green-100 text-green-700',
                                            \App\Enums\NewsPostStatusEnum::DRAFT     => 'bg-yellow-100 text-yellow-700',
                                            \App\Enums\NewsPostStatusEnum::ARCHIVED  => 'bg-gray-100 text-gray-600',
                                        };
                                    @endphp
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $badgeClass }}">
                                        {{ $article->status->getLabel() }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4 hidden sm:table-cell text-xs text-gray-400">
                                    {{ $article->created_at->translatedFormat('d M Y') }}
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-button class="btn-ghost btn-xs" icon="o-pencil"
                                            link="{{ route('admin.website.articles.edit', $article->slug) }}" />
                                        @if ($article->status !== \App\Enums\NewsPostStatusEnum::PUBLISHED)
                                            <x-button class="btn-ghost btn-xs text-green-600"
                                                icon="o-check-circle" tooltip="Publier"
                                                wire:click="publish({{ $article->id }})" />
                                        @endif
                                        @if ($article->status !== \App\Enums\NewsPostStatusEnum::ARCHIVED)
                                            <x-button class="btn-ghost btn-xs text-gray-400"
                                                icon="o-archive-box" tooltip="Archiver"
                                                wire:click="archive({{ $article->id }})" />
                                        @endif
                                        <x-button class="btn-ghost btn-xs text-error"
                                            icon="o-trash"
                                            wire:click="confirmDelete({{ $article->id }})" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $articles->links() }}
            </div>
        @endif
    </x-card>

    {{-- ── Modal suppression ─────────────────────────────────────────── --}}
    <x-modal wire:model="deleteModal" title="Supprimer l'article ?" class="backdrop-blur">
        <p class="text-sm text-gray-600">Cette action est irréversible.</p>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('deleteModal', false)" />
            <x-button class="btn-error" label="Supprimer" wire:click="delete" />
        </x-slot:actions>
    </x-modal>
</div>
