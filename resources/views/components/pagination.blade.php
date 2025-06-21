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
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                @if($pagination['current_page'] > 1)
                    <a href="{{ $pagination['prev_url'] }}" 
                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
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
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @endif
            </nav>
        </div>
    </div>
</nav>
