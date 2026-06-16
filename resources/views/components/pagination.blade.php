@props(['pagination'])

<nav class="flex items-center justify-between">
    <div class="flex-1 flex justify-between sm:hidden">
        @if($pagination['current_page'] > 1)
            <a href="{{ $pagination['prev_url'] }}" 
               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Précédent
            </a>
        @endif
        
        @if($pagination['current_page'] < $pagination['total_pages'])
            <a href="{{ $pagination['next_url'] }}" 
               class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Suivant
            </a>
        @endif
    </div>
    
    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-700">
                Affichage de 
                <span class="font-medium">{{ $pagination['from'] }}</span>
                à 
                <span class="font-medium">{{ $pagination['to'] }}</span>
                sur 
                <span class="font-medium">{{ $pagination['total'] }}</span>
                résultats
            </p>
        </div>
        
        <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-xs -space-x-px" aria-label="Pagination">
                @if($pagination['current_page'] > 1)
                    <a href="{{ $pagination['prev_url'] }}"
                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <x-icon name="o-chevron-left" class="h-5 w-5" />
                    </a>
                @endif
                
                @foreach($pagination['pages'] as $page)
                    @if($page == $pagination['current_page'])
                        <span class="relative inline-flex items-center px-4 py-2 border border-club-blue bg-club-blue text-sm font-medium text-white">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $pagination['page_urls'][$page] }}" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach
                
                @if($pagination['current_page'] < $pagination['total_pages'])
                    <a href="{{ $pagination['next_url'] }}"
                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <x-icon name="o-chevron-right" class="h-5 w-5" />
                    </a>
                @endif
            </nav>
        </div>
    </div>
</nav>
