<div class="py-6">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50 dark:bg-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ $title }}
                    <span class="text-sm font-normal text-gray-500">({{ $teams->total() }} {{ __('teams') }})</span>
                </h3>
            </div>

            <!-- Desktop table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 hidden lg:table">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Name') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Season') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Category') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('League') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Captain') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200">
                        @forelse ($teams as $team)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                                <!-- Nom -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-club-blue flex items-center justify-center text-white font-medium">
                                            {{ strtoupper(substr($team->name, 0, 2)) }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $team->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $team->users->count() }} {{ __('players') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <!-- Saison -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">
                                        {{ $team->season?->name ?? __('No season') }}
                                    </span>
                                </td>
                                <!-- Catégorie -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">
                                        {{ $team->league?->category?->getLabel() ?? __('No category') }}
                                    </span>
                                </td>
                                <!-- Ligue -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">
                                        @if($team->league)
                                            {{ $team->league->level?->getLabel() }} {{ $team->league->division }}
                                        @else
                                            {{ __('No league') }}
                                        @endif
                                    </span>
                                </td>
                                <!-- Capitaine -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">
                                        {{ $team->captain ? $team->captain->first_name . ' ' . $team->captain->last_name : __('No captain') }}
                                    </span>
                                </td>
                                <!-- Actions -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <!-- Voir -->
                                        <a href="{{ route('teams.show', $team->id) }}" 
                                           class="bg-club-blue hover:bg-club-blue-light text-white p-2 rounded-lg"
                                           title="{{ __('Check details') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        <!-- Edit -->
                                        @can('update', $teamModel)
                                            <a href="{{ route('teams.edit', $team->id) }}" 
                                               class="bg-yellow-600 hover:bg-yellow-700 text-white p-2 rounded-lg"
                                               title="{{ __('Edit') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </a>
                                        @endcan
                                        <!-- Delete -->
                                        @can('delete', $teamModel)
                                            <form action="{{ route('teams.destroy', $team->id) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-lg" title="{{ __('Delete') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-9 0h10"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">
                                    {{ __('No teams') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <!-- Mobile cards -->
                <div class="grid grid-cols-1 gap-4 p-4 lg:hidden">
                    @foreach($teams as $team)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm bg-white dark:bg-gray-800">
                            <div class="flex items-center space-x-3 mb-2">
                                <div class="h-10 w-10 rounded-full bg-club-blue flex items-center justify-center text-white font-medium">
                                    {{ strtoupper(substr($team->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $team->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $team->users->count() }} {{ __('players') }}</div>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2 mb-3">
                                @if($team->season)
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ $team->season->name }}</span>
                                @endif
                                @if($team->league?->category)
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">{{ $team->league->category->getLabel() }}</span>
                                @endif
                            </div>
                            <div class="flex justify-end space-x-2">
                                <a href="{{ route('teams.show', $team->id) }}" class="bg-club-blue hover:bg-club-blue-light text-white p-2 rounded-lg" title="{{ __('Check details') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                @can('update', $teamModel)
                                    <a href="{{ route('teams.edit', $team->id) }}" class="bg-yellow-600 hover:bg-yellow-700 text-white p-2 rounded-lg" title="{{ __('Edit') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                @endcan
                                @can('delete', $teamModel)
                                    <form action="{{ route('teams.destroy', $team->id) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-lg" title="{{ __('Delete') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-9 0h10"/></svg>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Pagination -->
            @if($teams->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $teams->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
