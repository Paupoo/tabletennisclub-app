{{-- format-ignore-start --}}
@props([
    'image' => asset('images/empty-state.svg'),
    'size' => '40',
    'heading' => null,
    'message' => null,
    'buttonText' => null,
    'href' => null,
])

<div class="flex flex-col items-center justify-center text-center p-6 bg-white dark:bg-dark-800 rounded-xl shadow-lg max-w-md mx-auto my-8">
        <img src="{{ $image }}" class="object-contains mx-auto mb-3"/>
        <div class="text-slate-700 dark:text-dark-400 text-xl pt-4 pb-2 px-4 font-light">{{ $heading }}</div>
        <div class="text-slate-600/70 dark:text-dark-500 px-6">{{ $message }}</div>
        
        <x-button>{{ $buttonText }}</x-button>
   
</div>
