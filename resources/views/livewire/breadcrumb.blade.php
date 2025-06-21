<!-- resources/views/livewire/breadcrumb.blade.php -->
<nav class="flex px-5 py-3 text-gray-700 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
        @foreach($breadcrumbs as $index => $item)
            <li @if($index === count($items) - 1) aria-current="page" @endif class="inline-flex items-center">
                @if($index > 0)
                    <svg class="rtl:rotate-180 w-3 h-3 mx-1 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                    </svg>
                @endif
                
                @if($item['url'] && $index !== count($items) - 1)
                    <a href="{{ $item['url'] }}" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">
                        @if($index === 0 && isset($item['icon']))
                            {!! $item['icon'] !!}
                        @endif
                        {{ $item['title'] }}
                    </a>
                @else
                    <span class="ms-1 text-sm font-medium {{ $index === count($items) - 1 ? 'text-gray-500' : 'text-gray-700' }} md:ms-2 dark:text-gray-400">
                        {{ $item['title'] }}
                    </span>
                @endif
                
                @if($index === count($items) - 1 && $tournamentStatus)
                    <x-tournament.status-badge :status="$tournamentStatus" />
                @endif
            </li>
        @endforeach
    </ol>
</nav>