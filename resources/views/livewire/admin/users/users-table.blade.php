    <div>

        <!-- Barre de filtres modernisée -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center space-y-4 lg:space-y-0 lg:space-x-4">
                <!-- Recherche -->
                <div class="flex-1 max-w-md">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input 
                            type="text" 
                            wire:model.live.debounce.500ms="search"
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-club-blue focus:border-club-blue text-sm"
                            placeholder="{{ __('Search users...') }}">
                    </div>
                </div>

                <!-- Filtres -->
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                    <!-- Type -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Type') }}</label>
                        <select wire:model.live="competitor" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-club-blue focus:border-club-blue">
                            <option value="">{{ __('All') }}</option>
                            <option value="1">{{ __('Competitor') }}</option>
                            <option value="0">{{ __('Casual') }}</option>
                        </select>
                    </div>

                    <!-- Gender -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Gender') }}</label>
                        <select wire:model.live="gender" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-club-blue focus:border-club-blue">
                            <option value="">{{ __('All') }}</option>
                            <option value="{{ \App\Enums\Gender::WOMEN->name }}">{{ __('Women') }}</option>
                            <option value="{{ \App\Enums\Gender::MEN->name }}">{{ __('Men') }}</option>
                            <option value="{{ \App\Enums\Gender::OTHER->name }}">{{ __('Others') }}</option>
                        </select>
                    </div>

                    <!-- Statut -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Status') }}</label>
                        <select wire:model.live="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-club-blue focus:border-club-blue">
                            <option value="">{{ __('All') }}</option>
                            <option value="active">{{ __('Active') }}</option>
                            <option value="inactive">{{ __('Inactive') }}</option>
                            <option value="paid">{{ __('Paid') }}</option>
                            <option value="unpaid">{{ __('Unpaid') }}</option>
                        </select>
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Per page') }}</label>
                        <select wire:model.live="perPage" class="border border-gray-300 rounded-lg px-3 py-2 pr-8 text-sm focus:ring-club-blue focus:border-club-blue">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table des utilisateurs -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- En-tête responsive -->
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between">
                <h3 class="text-lg font-medium text-gray-900">
                    {{ __('Users') }}
                    <span class="text-sm font-normal text-gray-500">({{ $users->total() }} {{ __('results') }})</span>
                </h3>
                
                @if(Auth()->user()->is_committee_member || Auth()->user()->is_active)
                    @if(count($selectedItems) > 0)
                        <div class="relative" x-data="{ open: false }">
                            <!-- Bouton principal du dropdown -->
                            <button @click="open = !open" 
                                    @click.outside="open = false"
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"/>
                                </svg>
                                {{ __('Actions') }} ({{ count($selectedItems) }})
                                <svg class="ml-2 -mr-1 w-4 h-4" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <!-- Menu dropdown -->
                            <div x-show="open" 
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute right-0 mt-2 w-64 rounded-md shadow-lg bg-white focus:outline-none z-50">
                                <div class="py-1" role="menu">
                                    

                                    <!-- Activate -->
                                    <button wire:click="bulkActivate"
                                            wire:confirm="Êtes-vous sûr de vouloir activer {{ count($selectedItems) }} membre(s) ?"
                                            @click="open = false"
                                            class="group flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-900">
                                        <div class="w-5 h-5 mr-3 text-blue-600">
                                            <x-ui.icon name="bolt"/>
                                        </div>
                                        {{ __('Activate') }}
                                    </button>

                                    <!-- Deactivate -->
                                    <button wire:click="bulkDeactivate"
                                            wire:confirm="Êtes-vous sûr de vouloir désactiver {{ count($selectedItems) }} membre(s) ?"
                                            @click="open = false"
                                            class="group flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900">
                                        <div class="w-5 h-5 mr-3 text-gray-600">
                                            <x-ui.icon name="leave"/>
                                        </div>
                                        {{ __('Deactivate') }}
                                    </button>

                                    <!-- Separator -->
                                    <div class="border-t border-gray-100"></div>

                                <!-- Mark Paid -->
                                    <button wire:click="bulkPaid"
                                            wire:confirm="Êtes-vous sûr de vouloir marquer {{ count($selectedItems) }} membre(s) en ordre de cotisation ?"
                                            @click="open = false"
                                            class="group flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-green-50 hover:text-green-900">
                                        <div class="w-5 h-5 mr-3 text-green-600">
                                            <x-ui.icon name="money"/>
                                        </div>
                                        {{ __('Mark as paid') }}
                                    </button>

                                    <!-- Mark Unpaid -->
                                    <button wire:click="bulkUnpaid"
                                            wire:confirm="Êtes-vous sûr de vouloir marquer {{ count($selectedItems) }} membre(s) en défaut de cotisation ?"
                                            @click="open = false"
                                            class="group flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-900">
                                        <div class="w-5 h-5 mr-3 text-orange-600">
                                            <x-ui.icon name="money"/>
                                        </div>
                                        {{ __('Mark as unpaid') }}
                                    </button>   

                                    <!-- Separator -->
                                    <div class="border-t border-gray-100"></div>

                                    <!-- Delete -->
                                    <button wire:click="bulkDelete"
                                            wire:confirm="Êtes-vous sûr de vouloir supprimer {{ count($selectedItems) }} membre(s) ?"
                                            @click="open = false"
                                            class="group flex items-center w-full px-4 py-2 text-sm text-red-700 hover:bg-red-50 hover:text-red-900">
                                        <div class="w-5 h-5 mr-3 text-red-600">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </div>
                                        {{ __('Delete') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            <!-- Version desktop -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            @if(Auth()->user()->is_committee_member || Auth()->user()->is_active)
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300 text-club-blue focus:ring-club-blue">
                                    <div class="flex flex-col">
                                        @if(count($selectedItems) > 0)
                                            <span class="text-club-blue font-medium text-xs normal-case">
                                                {{ count($selectedItems) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </th>
                            @endif
                            <th wire:click="sortBy('last_name')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors">
                                <div class="flex items-center space-x-1">
                                    <span>{{ __('User') }}</span>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
                                    </svg>
                                </div>
                            </th>
                            <th wire:click="sortBy('force_list')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors">
                                <div class="flex items-center space-x-1">
                                    <span>{{ __('Force Index') }}</span>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
                                    </svg>
                                </div>
                            </th>
                            <th wire:click="sortBy('ranking')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors">
                                <div class="flex items-center space-x-1">
                                    <span>{{ __('Ranking') }}</span>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
                                    </svg>
                                </div>
                            </th>
                            <th wire:click="sortBy('team_id')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors">
                                <div class="flex items-center space-x-1">
                                    <span>{{ __('Teams') }}</span>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
                                    </svg>
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <span>{{ __('Status') }}</span>
                            </th>
                            <th wire:click="sortBy('created_at')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors">
                                <div class="flex items-center space-x-1">
                                    <span>{{ __('Created at') }}</span>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
                                    </svg>
                                </div>
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <span>{{ __('Actions') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($users as $user)
                            <tr wire:key="{{ $user->id }}" class="hover:bg-gray-50 transition-colors duration-200 {{ in_array($user->id, $selectedItems) ? 'bg-blue-50' : '' }}">
                                @if(Auth()->user()->is_committee_member || Auth()->user()->is_active)
                                <td class="px-4 py-2">
                                    <input type="checkbox" wire:model.live="selectedItems" value="{{ $user->id }}">
                                </td>
                                @endif
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-club-blue flex items-center justify-center">
                                                <span class="text-sm font-medium text-white">
                                                    {{ strtoupper(substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1)) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <span class="{{ $user->gender_color }} mr-1">{{ $user->gender_display }}</span>
                                                {{ $user->first_name }} {{ $user->last_name }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ $user->is_competitor ? __('Competitor') : __('Casual') }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Force Index -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 font-medium">{{ $user->force_list }}</div>
                                </td>

                                <!-- Ranking -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 font-medium">{{ $user->ranking }}</div>
                                </td>

                                <!-- Équipes -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($user->teams->count() > 0)
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($user->teams->sortBy('name') as $team)
                                                <a href="{{ route('teams.show', $team) }}" 
                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors duration-200">
                                                    {{ $team->name }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-sm text-gray-500">{{ __('No team') }}</span>
                                    @endif
                                </td>

                                <!-- Statuts -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col space-y-1">
                                        <!-- Statut actif -->
                                        @if ($user->is_active)
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs justify-center font-medium bg-green-100 text-green-800">
                                                {{ __('Active') }}
                                            </span>
                                            @if(Auth()->user()->is_committee_member || Auth()->user()->is_admin )
                                                @if(!$user->has_paid)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs justify-center font-medium bg-red-100 text-red-800">
                                                        ✗ {{ __('Unpaid') }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs justify-center font-medium bg-green-100 text-green-800">
                                                        {{ __('Paid') }}
                                                    </span>
                                                @endif
                                            @endif
                                        @else
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs justify-center font-medium  bg-gray-100 text-gray-800">
                                                {{ __('Inactive') }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $user->created_at->format('d/m/Y') }}
                                    <div class="text-xs text-gray-400">{{ $user->created_at->timezone('Europe/Brussels')->format('H:i') }}</div>
                                </td>


                                <!-- Actions -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <a href="{{ route('users.show', $user->id) }}" 
                                        class="bg-club-blue hover:bg-club-blue-light text-white p-2 rounded-lg transition-colors duration-200"
                                        title="{{ __('Check details') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </a>
                                        
                                        @can('update', Auth()->user())
                                            <a href="{{ route('users.edit', $user) }}" 
                                            class="bg-yellow-600 hover:bg-yellow-700 text-white p-2 rounded-lg transition-colors duration-200"
                                            title="{{ __('Modify user details') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-.5a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"></path>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">{{ __('No users found') }}</h3>
                                    <p class="mt-1 text-sm text-gray-500">{{ __('Try modifying your search criteria.') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Version mobile -->
            <div class="lg:hidden">
                <div class="space-y-0">
                    @foreach ($users as $user)
                        <div class="border-b border-gray-200 p-4 hover:bg-gray-50 transition-colors duration-200">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-club-blue flex items-center justify-center">
                                            <span class="text-sm font-medium text-white">
                                                {{ strtoupper(substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1)) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $user->first_name }} {{ $user->last_name }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <ol>
                                                <li>{{ $user->is_competitor ? __('Competitor') : __('Casual') }}</li>
                                                <li>Force: {{ $user->force_list }}</li>
                                                <li>Ranking: {{ $user->ranking }}</li>
                                                @if ($user->teams->count() > 0)
                                                    <li>Teams :
                                                        @foreach ($user->teams->sortBy('name') as $team)
                                                        <a href="{{ route('teams.show', $team) }}" >
                                                            {{ $team->name }}
                                                        </a>
                                                        @if(!$user->teams->last)
                                                         - 
                                                        @endif
                                                        @endforeach
                                                    </li>
                                                @endif
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                                        
                            <div class="flex justify-end space-x-2">
                                <a href="{{ route('users.show', $user->id) }}" 
                                class="bg-club-blue hover:bg-club-blue-light text-white px-3 py-2 rounded-lg text-xs font-medium transition-colors duration-200">
                                    {{ __('Details') }}
                                </a>
                                @can('update', Auth()->user())
                                    <a href="{{ route('users.edit', $user) }}" 
                                    class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-2 rounded-lg text-xs font-medium transition-colors duration-200">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('delete', Auth()->user())
                                    <button 
                                        wire:click="$set('selectedUserId', {{ $user->id }})"
                                        @click="$dispatch('open-modal', 'confirm-delete-user')"
                                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-xs font-medium transition-colors duration-200">
                                        {{ __('Delete') }}
                                    </button>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Pagination -->
        @if($users->hasPages())
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mt-6">
                {{ $users->links() }}
            </div>
        @endif

        <!-- Légende -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mt-6">
            <h4 class="text-sm font-medium text-gray-900 mb-3">{{ __('Legend') }}</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="flex items-center space-x-2">
                    <div class="bg-club-blue text-white p-1 rounded">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <span class="text-sm text-gray-600">{{ __('Show details') }}</span>
                </div>
                
                @can('update', Auth()->user())
                    <div class="flex items-center space-x-2">
                        <div class="bg-yellow-600 text-white p-1 rounded">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </div>
                        <span class="text-sm text-gray-600">{{ __('Update user details') }}</span>
                    </div>

                    {{-- <div class="flex items-center space-x-2">
                        <div class="bg-green-600 text-white p-1 rounded">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                        <span class="text-sm text-gray-600">{{ __('Toggle payment') }}</span>
                    </div> --}}
                @endcan

                {{-- @can('delete', auth()->user())
                    <div class="flex items-center space-x-2">
                        <div class="bg-red-600 text-white p-1 rounded">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </div>
                        <span class="text-sm text-gray-600">{{ __('Delete user') }}</span>
                    </div>
                @endcan --}}
            </div>
        </div>

        <!-- Modal de confirmation de suppression amélioré -->
        <x-modal name="confirm-delete-user" focusable>
            <form wire:submit.prevent="destroy()" class="p-6" x-data="{ confirmText: '', isValid() { return this.confirmText === 'DELETE' } }">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 text-center mb-2">
                    {{ __('Are you sure you want to delete this user?') }}
                </h2>

                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-4">
                    {{ __('This action is irreversible. All associated data will be permanently deleted.') }}
                </p>

                <!-- Champ de confirmation -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ __('To confirm, type') }} <strong>"DELETE"</strong> {{ __('in the box below') }}:
                    </label>
                    <input 
                        type="text" 
                        x-model="confirmText"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        placeholder="DELETE"
                        autocomplete="off"
                    >
                </div>

                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                    <x-secondary-button @click="$dispatch('close')" class="flex-1">
                        {{ __('Cancel') }}
                    </x-secondary-button>

                    <x-danger-button 
                        class="flex-1" 
                        x-bind:disabled="!isValid()"
                        x-bind:class="{ 'opacity-50 cursor-not-allowed': !isValid() }"
                        type="submit"
                    >
                        {{ __('Delete permanently') }}
                    </x-danger-button>
                </div>
            </form>
        </x-modal>

    </div>

