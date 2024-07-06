<p {{ $attributes->merge([
    'class' => 'font-thin text-left text-sm w-fit px-2 py-1 ',
]) }}>
    {{ $slot }}
</p>
