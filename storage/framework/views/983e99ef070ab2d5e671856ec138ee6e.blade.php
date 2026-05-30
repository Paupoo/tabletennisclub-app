       
    <a href="{{ route('home') }}" wire:navigate>
        <!-- Hidden when collapsed -->
        <div {{ $attributes->class(["hidden-when-collapsed"]) }}>
            <div class="flex flex-col items-center shrink-0 gap-2">
                <x-logo class="block w-auto text-primary fill-current h-9" />
                <span class="hidden sm:block ml-4 text-lg font-bold text-primary">
                    {{ config('app.name', 'Club') }}
                </span>
            </div>
        </div>

        <!-- Display when collapsed -->
        <div class="display-when-collapsed hidden mx-5 mt-5 mb-1 h-[28px]">
            <x-logo class="block w-auto text-primary fill-current h-9" />
        </div>
    </a>
