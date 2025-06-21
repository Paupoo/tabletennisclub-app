@props([
    'breadcrumbs' => [],
])

<header class="bg-white shadow-sm dark:bg-gray-800">
    <div class="px-4 py-6 mx-auto max-w-7xl sm:px-6 lg:px-8 flex flex-row gap-2 items-center">
        
        <!-- Breadcrumb -->
        <x-breadcrumbs :breadcrumbs="$breadcrumbs"/>
       

        @stack('header-actions')
        
    </div>
</header>