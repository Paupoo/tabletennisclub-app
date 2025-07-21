@props([
    'breadcrumbs' => [],
])
<header class="bg-white shadow-sm dark:bg-gray-800">
    <div class="px-4 py-4 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:py-6">
        <!-- Layout responsive pour breadcrumbs et actions -->
        <div class="flex flex-col space-y-3 sm:flex-row sm:items-center sm:justify-between sm:space-y-0 sm:gap-4">
            
            <!-- Breadcrumb Section -->
            <div class="flex-1 min-w-0">
                <x-breadcrumbs :breadcrumbs="$breadcrumbs" class="truncate"/>
            </div>
            
            <!-- Header Actions Section -->
            <div class="flex-shrink-0">
                @stack('header-actions')
            </div>
            
        </div>
    </div>
</header>