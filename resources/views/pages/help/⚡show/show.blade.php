<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header :title="$this->article->title" :subtitle="$this->article->summary" separator>
        <x-slot:actions>
            <x-button :label="__('Back to help')" icon="o-arrow-left" class="btn-ghost btn-sm"
                :link="route('admin.help.index')" wire:navigate />
        </x-slot:actions>
    </x-header>

    {{-- Trusted content: these files ship with the app, they are not user input. --}}
    <article class="prose prose-sm max-w-3xl dark:prose-invert prose-headings:font-bold prose-a:text-primary">
        {!! $this->article->html() !!}
    </article>
</div>
