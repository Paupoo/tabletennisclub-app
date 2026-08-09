<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header :title="__('Help')" :subtitle="__('One page per task — only what concerns your role.')" separator>
        <x-slot:middle>
            <x-input class="w-full" clearable icon="o-magnifying-glass" :placeholder="__('Search help...')"
                wire:model.live.debounce.300ms="search" />
        </x-slot:middle>
    </x-header>

    @forelse ($articles as $article)
        <a href="{{ route('admin.help.show', $article->slug) }}" wire:navigate
            class="mb-3 flex items-start gap-4 rounded-2xl border border-base-300 p-5 transition hover:border-primary hover:bg-primary/5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                <x-icon name="o-book-open" class="size-5" />
            </span>
            <div class="min-w-0">
                <h2 class="font-bold">{{ $article->title }}</h2>
                @if ($article->summary !== '')
                    <p class="mt-1 text-sm text-base-content/70">{{ $article->summary }}</p>
                @endif
            </div>
            <x-icon name="o-chevron-right" class="ml-auto size-5 shrink-0 self-center opacity-30" />
        </a>
    @empty
        <x-card>
            <x-empty-state icon="o-book-open" :heading="__('Nothing here yet')"
                :message="__('No help page matches your search.')" />
        </x-card>
    @endforelse
</div>
