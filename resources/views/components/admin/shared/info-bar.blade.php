@props([
    'title' => __('Information'),
    'description' => __('This is an important message for all users.'),
])

<div
    class="from-primary/10 border-primary mb-8 flex items-center justify-between rounded-r-2xl border-l-4 bg-gradient-to-r to-transparent p-4">
    <div class="flex items-center gap-4">
        <div class="bg-primary text-primary-content rounded-lg p-2">
            <x-icon class="h-6 w-6" name="o-information-circle" />
        </div>
        <div>
            <h4 class="text-sm font-black uppercase tracking-tight">{{ $title }}</h4>
            <p class="text-xs italic opacity-70">{{ $description }}</p>
        </div>
    </div>
    
    @isset($action)
        <div class="flex-shrink-0 ml-4">
            {{ $action }}
        </div>
    @endisset

</div>
