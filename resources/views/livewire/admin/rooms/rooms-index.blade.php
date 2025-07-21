<div>
    <x-admin-block>
        <x-layout.page-header title="{{ __('Rooms list') }}" description="{{ __('Manage the rooms from here.') }}" />

        <!-- Barre de filtres -->
        <div class="flex flex-row justify-start mb-6">
            <x-forms.search-input placeholder="{{ __('Search rooms...') }}" wire:model.live.debounce.500ms="search" />

            <div class="flex items-center gap-3 ml-auto">
                <label class="flex flex-row text-xs">
                    <p class="my-auto mr-2">{{ ('Building') }}</p>
                    <x-forms.select-input wire:model.live="building">
                        <option value="" selected>{{ __('All rooms') }}</option>
                        @foreach ($buildings as $building)
                            <option value="{{ $building }}">{{ $building }}</option>
                        @endforeach
                    </x-forms.select-input>
                </label>
            </div>
        </div>

        <!-- Tableau des salles -->
        @foreach ($rooms as $room)
        <div class="border border-gray-200 rounded-lg p-3 sm:p-4 mb-4">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-3 space-y-2 sm:space-y-0">
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-800 text-sm sm:text-base">{{ $room->name }}</h3>
                    <p class="text-xs sm:text-sm text-gray-600">{{ $room->building_name }}</p>
                    <p class="text-xs sm:text-sm text-gray-600">{{ $room->address }}</p>
                    <p class="text-xs sm:text-sm text-gray-400">{{ __('Training: ' . $room->capacity_for_trainings . ' tables') }}</p>
                    <p class="text-xs sm:text-sm text-gray-400">{{ __('Competition: ' . $room->capacity_for_interclubs . ' tables') }}</p>
                </div>
                
                <!-- Badges des capacités -->
                <div class="flex flex-wrap gap-2 self-start">
                    @can('update', Auth()->user())
                    <x-ui.action-button 
                        variant="default"
                        icon="edit"
                        tooltip="{{ __('Edit room') }}"
                        onclick="window.location.href='{{ route('rooms.edit', $room) }}'">
                    </x-ui.action-button>
                    @endcan
                    @can('delete', Auth()->user())
                    <x-ui.action-button 
                        variant="danger"
                        icon="delete"
                        tooltip="{{ __('Delete room') }}"
                        onclick="window.location.href='{{ route('rooms.destroy', $room) }}'">
                    </x-ui.action-button>
                    @endcan
                </div>
            </div>
        </div>
        @endforeach
     
    </x-admin-block>
