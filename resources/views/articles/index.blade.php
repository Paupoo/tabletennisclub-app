<div>

    <!-- En-tête de page -->
    <x-layout.page-header title="{{ __('Articles') }}" description="{{ __('Manage the articles from here.') }}" />

    @if($articles->count() > 0)
    <!-- Barre de filtres -->
    <div class="flex flex-row justify-start mb-6">
        <x-forms.search-input placeholder="{{ __('Search for articles...') }}" wire:model.live.debounce.500ms="search" />

        {{-- <div class="flex items-center gap-3 ml-auto">
            <label class="flex flex-row text-xs">
                <p class="my-auto mr-2">{{ 'Type' }}</p>
                <x-forms.select-input wire:model.live="competitor">
                    <option value="">{{ __('All') }}</option>
                    <option value="1">{{ __('Competitor') }}</option>
                    <option value="0">{{ __('Casual') }}</option>
                </x-forms.select-input>
            </label>

            <label class="flex flex-row text-xs">
                <p class="my-auto mr-2">{{ 'Sex' }}</p>
                <x-forms.select-input wire:model.live="sex">
                    <option value="">{{ __('All') }}</option>
                    <option value="{{ \App\Enums\Sex::WOMEN->name }}">{{ __('Women') }}</option>
                    <option value="{{ \App\Enums\Sex::MEN->name }}">{{ __('Men') }}</option>
                    <option value="{{ \App\Enums\Sex::OTHER->name }}">{{ __('Others') }}</option>
                </x-forms.select-input>
            </label>
            <label class="flex flex-row text-xs">
                <p class="my-auto mr-2">{{ 'Pagination' }}</p>
                <x-forms.select-input wire:model.live="perPage">
                    <option value="25">25 par page</option>
                    <option value="50">50 par page</option>
                    <option value="100">100 par page</option>
                </x-forms.select-input>
            </label>
        </div> --}}
    </div>

    <x-table.container>
        <x-table.header>
            <x-table.row>
                <x-table.header-cell>{{ __('Title') }}</x-table.header-cell>
                <x-table.header-cell>{{ __('Content') }}</x-table.header-cell>
                <x-table.header-cell>{{ __('Author') }}</x-table.header-cell>
                <x-table.header-cell>{{ __('Published at') }}</x-table.header-cell>
            </x-table.row>
        </x-table.header>
        <x-table.body>
            @foreach ($articles as $article)
                <x-table.row>
                    <x-table.cell>{{ $article->title }}</x-table.cell>
                    <x-table.cell>{{ Str::of($article->content)->limit(50) }}</x-table.cell>
                    <x-table.cell>{{ $article->author }}</x-table.cell>
                    <x-table.cell>{{ $article->created_at->format('d/m/Y \a\t H:i') }}</x-table.cell>
                </x-table.row>
            @endforeach
        </x-table.body>
    </x-table.container>
    @else
    <x-empty-state
        image="{{ asset('images/empty-state.svg') }}"
        message="There are no articles yet, start with creating a first one !"
        buttonText="Create a first article"
        buttonLink="{{ route('articles.create') }}">
    </x-empty-state>
    @endif
</div>