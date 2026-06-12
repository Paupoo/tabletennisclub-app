{{-- Bottom action sheet (mobile only). Requires x-data="{ mobileActionsOpen: false }" on a parent. --}}

<div x-show="mobileActionsOpen"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="mobileActionsOpen = false"
    class="fixed inset-0 bg-black/40 z-40 lg:hidden"
    style="display:none"></div>

<div x-show="mobileActionsOpen"
    x-transition:enter="transition ease-out duration-250"
    x-transition:enter-start="transform translate-y-full"
    x-transition:enter-end="transform translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="transform translate-y-0"
    x-transition:leave-end="transform translate-y-full"
    class="fixed bottom-0 left-0 right-0 bg-base-100 rounded-t-2xl z-50 shadow-2xl lg:hidden"
    style="display:none">

    <div class="flex justify-center pt-3 pb-1">
        <div class="w-10 h-1 rounded-full bg-base-300"></div>
    </div>

    <div class="px-4 pt-2 pb-2">
        {{ $slot }}
    </div>
    <div class="pb-6"></div>
</div>
