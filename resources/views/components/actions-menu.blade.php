<!-- Menu d'actions -->
<div class="ml-auto mt-4 md:mt-0 flex flex-wrap gap-3" 
     x-data="{ showMenu: false }" 
     x-init="console.log('Alpine initialized', $data)">
     
    <!-- Bouton principal avec dropdown -->
    <div class="relative">
        <x-primary-button @click="showMenu = !showMenu"
            class="flex items-center justify-between bg-club-blue hover:bg-club-yellow text-white px-4 py-2 rounded-md transition duration-150 ease-in-out"
            type="button">
            <span class="mr-2">{{ __('Actions') }}</span>
            <x-ui.icon name="arrow-down" />
        </x-primary-button>
        
        <!-- Menu Dropdown avec positionnement adaptatif -->
        <div x-show="showMenu" 
             @click.away="showMenu = false"
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="transform opacity-0 scale-95"
             x-transition:enter-end="transform opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="transform opacity-100 scale-100"
             x-transition:leave-end="transform opacity-0 scale-95"
             class="absolute mt-2 w-48 bg-white rounded-md shadow-lg z-50
                    left-0 sm:right-0 sm:left-auto
                    origin-top-left sm:origin-top-right">
            <div class="py-1">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>