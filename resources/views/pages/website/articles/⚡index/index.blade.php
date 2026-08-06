<div x-data="{ mobileSearchOpen: false, mobileActionsOpen: false }">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator title="Articles">
        <x-slot:middle>
            <div class="hidden w-full lg:block">
                <x-input class="w-full" clearable icon="o-magnifying-glass"
                    :placeholder="__('Search...')"
                    wire:model.live.debounce.300ms="search" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            {{-- Mobile: 🔍 · filter · ☰ --}}
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" />
            {{-- Desktop: full buttons --}}
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
                <x-button class="btn-primary btn-sm" icon="o-plus" :label="__('New article')"
                    link="{{ route('admin.website.articles.create') }}" />
            </div>
        </x-slot:actions>
    </x-header>

    {{-- Mobile search bar --}}
    <div class="border-b border-base-200 lg:hidden" x-show="mobileSearchOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        style="display:none">
        <div class="flex items-center gap-2 px-4 py-2.5">
            <div class="flex flex-1 items-center gap-2 rounded-xl bg-base-200 px-3 py-2">
                <x-icon name="o-magnifying-glass" class="h-4 w-4 shrink-0 text-base-content/40" />
                <input wire:model.live.debounce.300ms="search"
                    class="flex-1 bg-transparent text-sm outline-none placeholder:text-base-content/40"
                    placeholder="{{ __('Search...') }}" />
            </div>
            <button @click="mobileSearchOpen = false" class="btn btn-ghost btn-circle btn-sm">
                <x-icon name="o-x-mark" class="h-5 w-5" />
            </button>
        </div>
    </div>

    {{-- ── Active filter chips ──────────────────────────────────────────────── --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

    {{-- ── Cartes stats ──────────────────────────────────────────────── --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-card class="shadow-sm">
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
        <x-card class="shadow-sm">
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
        <x-card class="shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-warning/10">
                    <x-icon name="o-pencil-square" class="h-5 w-5 text-warning-content" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-warning-content">{{ $stats->draft ?? 0 }}</p>
                    <p class="text-xs text-base-content/40">{{ __('Drafts') }}</p>
                </div>
            </div>
        </x-card>
        <x-card class="shadow-sm">
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

    {{-- ── Vue mobile ───────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 lg:hidden">
        @forelse ($articles as $article)
            <x-list-item :item="$article" class="bg-base-100 rounded-lg border" wire:key="mobile-article-{{ $article->id }}">
                <x-slot:avatar>
                    @if ($selectionModeActive)
                        <input type="checkbox"
                            class="checkbox checkbox-primary checkbox-sm"
                            value="{{ $article->id }}"
                            wire:model.live="selected" />
                    @endif
                </x-slot:avatar>
                <x-slot:value>
                    <span class="font-medium">{{ $article->title }}</span>
                </x-slot:value>
                <x-slot:sub-value>
                    <div class="flex items-center gap-2 mt-0.5">
                        @php
                            $badgeClass = match ($article->status) {
                                \App\Domains\Shared\Enums\NewsPostStatusEnum::PUBLISHED => 'badge-success badge-soft',
                                \App\Domains\Shared\Enums\NewsPostStatusEnum::DRAFT     => 'badge-warning badge-soft',
                                \App\Domains\Shared\Enums\NewsPostStatusEnum::ARCHIVED  => 'badge-ghost',
                            };
                        @endphp
                        <x-badge :value="$article->status->getLabel()" class="{{ $badgeClass }} badge-sm" />
                        <span class="text-xs text-base-content/40">
                            {{ $article->created_at->translatedFormat('d M Y') }}
                        </span>
                    </div>
                </x-slot:sub-value>
                <x-slot:actions>
                    @if (! $selectionModeActive)
                        <x-admin.shared.row-menu
                            :label="__('Edit')"
                            icon="o-pencil"
                            link="{{ route('admin.website.articles.edit', $article->slug) }}">
                            @if ($article->status !== \App\Domains\Shared\Enums\NewsPostStatusEnum::PUBLISHED)
                                <x-menu-item class="text-success" icon="o-check-circle" wire:click="publish({{ $article->id }})" :title="__('Publish')" />
                            @endif
                            @if ($article->status !== \App\Domains\Shared\Enums\NewsPostStatusEnum::ARCHIVED)
                                <x-menu-item icon="o-archive-box" wire:click="archive({{ $article->id }})" :title="__('Archive')" />
                            @endif
                            <x-menu-item class="text-error" icon="o-trash" wire:click="confirmDelete({{ $article->id }})" :title="__('Delete')" />
                        </x-admin.shared.row-menu>
                    @endif
                </x-slot:actions>
            </x-list-item>
        @empty
            <x-admin.shared.list-empty-state
                icon="o-document-text"
                :filtered="filled($search) || count($filterChips) > 0"
                :heading="__('No articles found')"
                :create-label="__('Write an article')"
                :create-href="auth()->user()->can('news_posts.manage') ? route('admin.website.articles.create') : null" />
        @endforelse

        @if ($articles->hasPages())
            <div class="mt-2">
                {{ $articles->links() }}
            </div>
        @endif
    </div>

    {{-- ── Vue desktop ────────────────────────────────────────────────── --}}
    <div class="hidden lg:block">
        <x-card>
            @if ($articles->isEmpty())
                <x-admin.shared.list-empty-state
                    icon="o-document-text"
                    :filtered="filled($search) || count($filterChips) > 0"
                    :heading="__('No articles found')"
                    :create-label="__('Write an article')"
                    :create-href="auth()->user()->can('news_posts.manage') ? route('admin.website.articles.create') : null" />
            @else
                <x-table :headers="$headers" :rows="$articles" :sort-by="$sortBy"
                    selectable wire:model.live="selected">
                    @scope('cell_title', $article)
                        <span class="font-medium">{{ $article->title }}</span>
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
                                \App\Domains\Shared\Enums\NewsPostStatusEnum::PUBLISHED => 'badge-success badge-soft',
                                \App\Domains\Shared\Enums\NewsPostStatusEnum::DRAFT     => 'badge-warning badge-soft',
                                \App\Domains\Shared\Enums\NewsPostStatusEnum::ARCHIVED  => 'badge-ghost',
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
                        <x-admin.shared.row-menu
                            :label="__('Edit')"
                            icon="o-pencil"
                            link="{{ route('admin.website.articles.edit', $article->slug) }}">
                            @if ($article->status !== \App\Domains\Shared\Enums\NewsPostStatusEnum::PUBLISHED)
                                <x-menu-item class="text-success" icon="o-check-circle" wire:click="publish({{ $article->id }})" :title="__('Publish')" />
                            @endif
                            @if ($article->status !== \App\Domains\Shared\Enums\NewsPostStatusEnum::ARCHIVED)
                                <x-menu-item icon="o-archive-box" wire:click="archive({{ $article->id }})" :title="__('Archive')" />
                            @endif
                            <x-menu-item class="text-error" icon="o-trash" wire:click="confirmDelete({{ $article->id }})" :title="__('Delete')" />
                        </x-admin.shared.row-menu>
                    @endscope
                </x-table>
                <div class="mt-4">
                    {{ $articles->links() }}
                </div>
            @endif
        </x-card>
    </div>

    {{-- ── Floating Pill — bulk actions ───────────────────────────────── --}}
    <x-admin.shared.selection-pill
        :selected="$selected"
        :total="$this->getTotalMatchingCount()"
        :selecting-all-results="$selectingAllResults"
        :select-all="$selectAll">
        <x-slot:actions>
            <x-button class="btn-ghost btn-sm" icon="o-check-circle" :label="__('Publish')"
                wire:click="bulkPublish" spinner="bulkPublish" />
            <span class="text-base-content/20">|</span>
            <x-button class="btn-ghost btn-sm text-warning-content" icon="o-archive-box" :label="__('Archive')"
                wire:click="confirmBulkArchive" />
        </x-slot:actions>
    </x-admin.shared.selection-pill>

    {{-- ── Filter drawer ────────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
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
    </x-admin.shared.filter-drawer>

    {{-- ── Modal suppression unitaire ───────────────────────────────── --}}
    <x-confirm-modal model="deleteModal" :title="__('Delete article?')"
        :confirmLabel="__('Delete')" confirmAction="delete">
        <p>{{ __('This action is irreversible.') }}</p>
    </x-confirm-modal>

    {{-- ── Modal archivage bulk ──────────────────────────────────────── --}}
    <x-confirm-modal model="confirmBulkArchiveModal" :title="__('Archive selected articles?')"
        :confirmLabel="__('Archive')" confirmAction="bulkArchive">
        <p>
            {{ trans_choice('selectedCount', count($selected), ['count' => count($selected)]) }}
            {{ __('will be archived.') }}
        </p>
    </x-confirm-modal>

    {{-- ── Mobile action sheet ─────────────────────────────────────────── --}}
    <x-admin.shared.mobile-actions>
        <x-admin.shared.mobile-action-item
            icon="o-plus" color="primary"
            :label="__('New article')"
            :description="__('Write and publish a new article')"
            @click="mobileActionsOpen = false; window.location.href = '{{ route('admin.website.articles.create') }}'" />
        <div class="my-1 h-px bg-base-200"></div>
        <x-admin.shared.mobile-action-item
            icon="o-check-circle" color="base"
            :label="__('Select')"
            :description="__('Bulk actions on multiple articles')"
            @click="mobileActionsOpen = false; $wire.call('toggleSelectionMode')" />
    </x-admin.shared.mobile-actions>
</div>
