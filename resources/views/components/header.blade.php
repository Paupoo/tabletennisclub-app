@props([
    'pageTitle' => __('Page title'),
    'tournamentStatus' => null,
])

<header class="bg-white shadow dark:bg-gray-800">
    <div class="px-4 py-6 mx-auto max-w-7xl sm:px-6 lg:px-8 flex flex-row gap-2 items-center">
        {{-- <x-admin.title :pageTitle="$pageTitle"/> --}}
        {{-- <x-tournament.status-badge :status="$badgeStatus" /> --}}
        



        <!-- Breadcrumb -->
        <x-breadcrumbs :pageTitle="$pageTitle" :tournamentStatus="$tournamentStatus"/>


        @stack('header-actions')
        
    </div>
</header>