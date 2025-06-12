@props(['tournament'])
<div x-data="{ show: @entangle('showModal') }" x-init="$watch('show', value => { if (value) $nextTick(() => $refs.search.focus()) })">
    <!-- Bouton pour ouvrir le modal -->
    <button wire:click="openModal" 
            class="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
        <x-ui.icon name="plus" class="mr-2" />
        {{ __('Register a user') }}
    </button>

    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
                
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Registrer a user') }}</h3>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Messages d'erreur globaux -->
                @if (session()->has('message'))
                    <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded">
                        {{ session('message') }}
                    </div>
                @endif

                <!-- Formulaire -->
                <form wire:submit="registerPlayer" class="space-y-4">
                    <div class="relative">
                        <label for="player-search" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('Search for a player') }}
                        </label>

                        <!-- Input de recherche -->
                        <div class="relative">
                            <input 
                                x-ref="search"
                                type="text" 
                                id="player-search" 
                                wire:model.live.debounce.300ms="searchQuery"
                                wire:focus="$set('showDropdown', true)"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 @error('selectedPlayerId') border-red-500 @enderror"
                                placeholder="{{ __('Type the name of a player') }}..." 
                                autocomplete="off">

                            <!-- Icône de recherche -->
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                        </div>

                        <!-- Message d'erreur pour le champ -->
                        @error('selectedPlayerId')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <!-- Liste déroulante des résultats -->
                        @if($showDropdown && $filteredPlayers->count() > 0)
                            <div class="absolute z-10 mt-1 w-full bg-white rounded-md shadow-lg border border-gray-200 max-h-60 overflow-auto">
                                @foreach($filteredPlayers as $player)
                                    <div wire:click="selectPlayer({{ $player->id }})" 
                                         class="px-4 py-2 cursor-pointer hover:bg-gray-50 flex items-center justify-between">
                                        <div>
                                            <div class="font-medium">{{ $player->first_name }} {{ $player->last_name }}</div>
                                            <div class="text-sm text-gray-500">{{ $player->email }}</div>
                                        </div>
                                        @if($player->level ?? false)
                                            <div class="text-xs text-gray-400">Niveau: {{ $player->level }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Message si aucun résultat -->
                        @if($showDropdown && !empty(trim($searchQuery)) && $filteredPlayers->count() === 0)
                            <div class="absolute z-10 mt-1 w-full bg-white rounded-md shadow-lg border border-gray-200 px-4 py-3 text-sm text-gray-500">
                                Aucun joueur trouvé pour "{{ $searchQuery }}"
                            </div>
                        @endif
                    </div>

                    <!-- Joueur sélectionné -->
                    @if($selectedPlayer)
                        <div class="bg-green-50 border border-green-200 rounded-md p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-medium text-green-800">{{ $selectedPlayer->first_name }} {{ $selectedPlayer->last_name }}</div>
                                    <div class="text-sm text-green-600">{{ $selectedPlayer->email }}</div>
                                </div>
                                <button wire:click="clearSelection" type="button" class="text-green-400 hover:text-green-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endif

                    <!-- Boutons d'action -->
                    <div class="flex justify-end space-x-3 pt-4">
                        <button wire:click="closeModal" type="button" 
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" 
                                @if(!$selectedPlayer) disabled @endif
                                class="px-4 py-2 text-sm font-medium text-white rounded-md 
                                       @if($selectedPlayer) bg-blue-600 hover:bg-blue-700 @else bg-gray-300 cursor-not-allowed @endif">
                            <span wire:loading.remove wire:target="registerPlayer">{{ __('Register') }}</span>
                            <span wire:loading wire:target="registerPlayer">{{ __('Registration') }}...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>