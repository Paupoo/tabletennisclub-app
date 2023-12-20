<button {{ $attributes->merge(['type' => 'submit', 'class' => 'px-2 py-1 rounded-lg duration-300 ease-in-out transition hover:bg-blue-800 hover:text-blue-300']) }}>
    {{ $slot }}
</button>