<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator title="Articles">
        <x-slot:middle>
            <x-input class="w-full" clearable icon="o-magnifying-glass"
                :placeholder="__('Search...')"
                wire:model.live.debounce.300ms="search" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button class="btn-ghost {{ $this->activeFiltersCount > 0 ? 'btn-active' : '' }}"
                wire:click="$toggle('showFilters')">
                <x-icon name="o-funnel" class="h-5 w-5" />
                {{ __('Filters') }}
                @if ($this->activeFiltersCount > 0)
                    <x-badge class="badge-sm badge-primary" value="{{ $this->activeFiltersCount }}" />
                @endif
            </x-button>
            <x-button class="btn-primary" icon="o-plus" label="Nouvel article"
                link="{{ route('admin.website.articles.create') }}" />
        </x-slot:actions>
    </x-header>

    {{-- ── Filtres ────────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-bar :active-filters-count="$this->activeFiltersCount" :show="$showFilters">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Category') }}
                </p>
                <x-select :options="$categoryOptions" :placeholder="__('All categories')"
                    wire:model.live="category" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Status') }}
                </p>
                <x-select :options="$statusOptions" :placeholder="__('All statuses')"
                    wire:model.live="status" class="w-full" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-bar>

    {{-- ── Cartes stats ──────────────────────────────────────────────── --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-card class="border-gray-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-base-200">
                    <x-icon name="o-document-text" class="h-5 w-5 text-base-content/60" />
                </div>
                <div>
                    <p class="text-2xl font-bold">{{ $stats->total ?? 0 }}</p>
                    <p class="text-xs text-base-content/40">Total</p>
                </div>
            </div>
        </x-card>
        <x-card class="border-gray-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-success/10">
                    <x-icon name="o-check-circle" class="h-5 w-5 text-success" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-success">{{ $stats->published ?? 0 }}</p>
                    <p class="text-xs text-base-content/40">{{ __('Published') }}</p>
                </div>
            </div>
        </x-card>
        <x-card class="border-gray-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-warning/10">
                    <x-icon name="o-pencil-square" class="h-5 w-5 text-warning" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-warning">{{ $stats->draft ?? 0 }}</p>
                    <p class="text-xs text-base-content/40">{{ __('Drafts') }}</p>
                </div>
            </div>
        </x-card>
        <x-card class="border-gray-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-base-200">
                    <x-icon name="o-archive-box" class="h-5 w-5 text-base-content/40" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-base-content/50">{{ $stats->archived ?? 0 }}</p>
                    <p class="text-xs text-base-content/40">{{ __('Archived') }}</p>
                </div>
            </div>
        </x-card>
    </div>

    {{-- ── Vue mobile (cards) ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 lg:hidden">
        @forelse ($articles as $article)
            <x-list-item :item="$article" class="bg-base-100 rounded-lg border" wire:key="mobile-article-{{ $article->id }}">
                <x-slot:value>
                    <span class="font-medium">{{ $article->title }}</span>
                </x-slot:value>
                <x-slot:sub-value>
                    <div class="flex items-center gap-2 mt-0.5">
                        @php
                            $badgeClass = match ($article->status) {
                                \App\Enums\NewsPostStatusEnum::PUBLISHED => 'badge-success badge-soft',
                                \App\Enums\NewsPostStatusEnum::DRAFT     => 'badge-warning badge-soft',
                                \App\Enums\NewsPostStatusEnum::ARCHIVED  => 'badge-ghost',
                            };
                        @endphp
                        <x-badge :value="$article->status->getLabel()" class="{{ $badgeClass }} badge-sm" />
                        <span class="text-xs text-base-content/40">
                            {{ $article->created_at->translatedFormat('d M Y') }}
                        </span>
                    </div>
                </x-slot:sub-value>
                <x-slot:actions>
                    <x-admin.shared.row-actions>
                        <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil"
                            :tooltip="__('Edit')"
                            link="{{ route('admin.website.articles.edit', $article->slug) }}" />
                        @if ($article->status !== \App\Enums\NewsPostStatusEnum::PUBLISHED)
                            <x-button class="btn-ghost btn-sm btn-circle text-success" icon="o-check-circle"
                                :tooltip="__('Publish')"
                                wire:click="publish({{ $article->id }})" />
                        @endif
                        @if ($article->status !== \App\Enums\NewsPostStatusEnum::ARCHIVED)
                            <x-button class="btn-ghost btn-sm btn-circle text-base-content/40" icon="o-archive-box"
                                :tooltip="__('Archive')"
                                wire:click="archive({{ $article->id }})" />
                        @endif
                        <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-trash"
                            :tooltip="__('Delete')"
                            wire:click="confirmDelete({{ $article->id }})" />
                    </x-admin.shared.row-actions>
                </x-slot:actions>
            </x-list-item>
        @empty
            <x-empty-state
                icon="o-document-text"
                :heading="__('No articles found')"
                :message="__('Try adjusting your search or filters.')" />
        @endforelse
    </div>

    {{-- ── Vue desktop (table) ────────────────────────────────────────── --}}
    <div class="hidden lg:block">
        <x-card>
            @if ($articles->isEmpty())
                <x-empty-state
                    icon="o-document-text"
                    :heading="__('No articles found')"
                    :message="__('Try adjusting your search or filters.')" />
            @else
                <x-table :headers="$headers" :rows="$articles" :sort-by="$sortBy">
                    @scope('cell_title', $article)
                        <div class="flex items-center gap-2">
                            @if (!$article->is_public)
                                <x-icon name="o-lock-closed" class="h-3.5 w-3.5 shrink-0 text-base-content/40" />
                            @endif
                            <span class="font-medium">{{ $article->title }}</span>
                        </div>
                    @endscope
                    @scope('cell_category_label', $article)
                        @if ($article->category)
                            <x-badge :value="$article->category->getLabel()" class="badge-soft badge-primary badge-sm" />
                        @endif
                    @endscope
                    @scope('cell_author_name', $article)
                        <span class="text-sm text-base-content/60">
                            {{ $article->user?->first_name }} {{ $article->user?->last_name }}
                        </span>
                    @endscope
                    @scope('cell_status', $article)
                        @php
                            $badgeClass = match ($article->status) {
                                \App\Enums\NewsPostStatusEnum::PUBLISHED => 'badge-success badge-soft',
                                \App\Enums\NewsPostStatusEnum::DRAFT     => 'badge-warning badge-soft',
                                \App\Enums\NewsPostStatusEnum::ARCHIVED  => 'badge-ghost',
                            };
                        @endphp
                        <x-badge :value="$article->status->getLabel()" class="{{ $badgeClass }}" />
                    @endscope
                    @scope('cell_created_at', $article)
                        <span class="text-xs text-base-content/40">
                            {{ $article->created_at->translatedFormat('d M Y') }}
                        </span>
                    @endscope
                    @scope('actions', $article)
                        <x-admin.shared.row-actions>
                            <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil"
                                :tooltip="__('Edit')"
                                link="{{ route('admin.website.articles.edit', $article->slug) }}" />
                            @if ($article->status !== \App\Enums\NewsPostStatusEnum::PUBLISHED)
                                <x-button class="btn-ghost btn-sm btn-circle text-success" icon="o-check-circle"
                                    :tooltip="__('Publish')"
                                    wire:click="publish({{ $article->id }})" />
                            @endif
                            @if ($article->status !== \App\Enums\NewsPostStatusEnum::ARCHIVED)
                                <x-button class="btn-ghost btn-sm btn-circle text-base-content/40" icon="o-archive-box"
                                    :tooltip="__('Archive')"
                                    wire:click="archive({{ $article->id }})" />
                            @endif
                            <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-trash"
                                :tooltip="__('Delete')"
                                wire:click="confirmDelete({{ $article->id }})" />
                        </x-admin.shared.row-actions>
                    @endscope
                </x-table>
                <div class="mt-4">
                    {{ $articles->links() }}
                </div>
            @endif
        </x-card>
    </div>

    {{-- ── Modal suppression ─────────────────────────────────────────── --}}
    <x-confirm-modal model="deleteModal" :title="__('Delete article?')"
        :confirmLabel="__('Delete')" confirmAction="delete">
        <p>{{ __('This action is irreversible.') }}</p>
    </x-confirm-modal>
</div>
