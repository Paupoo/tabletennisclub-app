<article
    {{ $attributes->merge([
        'class' => 'grid grid-rows-flow bg-white rounded-lg shadow-lg p-4 hover:outline hover:outline-blue-800',
    ]) }}>
    {{ $slot }}
</article>
