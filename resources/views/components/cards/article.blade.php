<article
    {{ $attributes->merge([
        'class' => 'grid grid-rows-flow bg-white rounded-lg shadow-lg p-4 hover:outline hover:outline-blue-800',
    ]) }}>
    {{ $slot }}
    <h2 class="text-lg font-semibold text-center">{{ fake()->words(4, true) }}</h2>
    <p class="mt-2 text-justify indent-3">{{ fake()->realText() }} </p>
    <div class="grid items-end grid-cols-4 gap-2">
        <x-published-date-indicator class="col-start-1 col-end-3">{{ __('Published:') }}
            {{ fake()->date() }}</x-published-date-indicator>
        <x-button type="button"
            class="col-start-4 col-end-5 px-4 py-2 mt-2 text-sm font-medium text-blue-900 bg-indigo-300 place-self-end w-36">{{ __('Read more') }}</x-button>
    </div>
</article>
