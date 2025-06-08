<!-- Menu d'actions -->
<div class="ml-auto mt-4 md:mt-0 flex flex-wrap gap-3" x-data="{ showMenu: false }">
    <!-- Bouton principal avec dropdown -->
    <div class="relative">
        <x-primary-button @click="showMenu = !showMenu"
            class="flex items-center justify-between bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md transition duration-150 ease-in-out"
            type="button">
                <span class="mr-2">{{ __('Actions') }}</span>
                <x-ui.icon name="arrow-down" />
        </x-primary-button>
        <!-- Menu Dropdown -->
        <div x-show="showMenu" @click.away="showMenu = false"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-50">
            <div class="py-1">
                <!-- Update subscription as a user -->
                @can('updateSubscriptionAsUser', $tournament)
                @if(!$tournament->users->contains(auth()->user()->id))
                <a href="/admin/tournament/register/{{$tournament->id}}/{{auth()->user()->id}}"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="join" class="mr-2" />
                    {{ __('Register') }}
                </a>
                @elseif($tournament->users->contains(auth()->user()->id))
                <a href="/admin/tournament/unregister/{{$tournament->id}}/{{auth()->user()->id}}"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="leave" class="mr-2" />
                    {{ __('Unregister') }}
                </a>
                @endif
                @endcan
                {{-- Updates --}}
                @can('update', $tournament)
                <!-- Composant Livewire pour l'inscription de joueur -->
                <livewire:tournament.player-registration :tournament="$tournament" />
                

                <a href="{{ route('tournament.edit', $tournament) }}"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="modify" class="mr-2" />
                    {{ __('Modify') }}
                </a>
                <a href="#"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="duplicate" class="mr-2" />
                    {{ __('Duplicate (TO DO)') }}
                </a>
                <div class="border-t border-gray-200"></div>
                @if(in_array(\App\Enums\TournamentStatusEnum::DRAFT, $statusesAllowed))
                <a href="{{ route('tournamentSetStatus', [$tournament, \App\Enums\TournamentStatusEnum::DRAFT]) }}"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="pen" class="mr-2" />
                    {{ __('Draft') }}
                </a>
                @endif
                @if(in_array(\App\Enums\TournamentStatusEnum::LOCKED, $statusesAllowed))
                <a href="{{ route('tournamentSetStatus', [$tournament, \App\Enums\TournamentStatusEnum::LOCKED]) }}"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="locked" class="mr-2" />
                    {{ __('Lock') }}
                </a>
                @endif
                @if(in_array(\App\Enums\TournamentStatusEnum::PUBLISHED, $statusesAllowed))
                <a href="{{ route('tournamentSetStatus', [$tournament, \App\Enums\TournamentStatusEnum::PUBLISHED]) }}"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="unlocked" class="mr-2" />
                    {{ __('Publish/Unlock') }}
                </a>
                @endif
                @if(in_array(\App\Enums\TournamentStatusEnum::PENDING, $statusesAllowed))
                <a href="{{ route('tournamentSetStatus', [$tournament, \App\Enums\TournamentStatusEnum::PENDING]) }}"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="rocket-launch" class="mr-2" />
                    {{ __('Start') }}
                </a>
                @endif
                @if(in_array(\App\Enums\TournamentStatusEnum::CLOSED, $statusesAllowed))
                <a href="{{ route('tournamentSetStatus', [$tournament, \App\Enums\TournamentStatusEnum::CLOSED]) }}"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="flag" class="mr-2" />
                    {{ __('Close') }}
                </a>
                @endif
                @if(in_array(\App\Enums\TournamentStatusEnum::CANCELLED, $statusesAllowed))
                <a href="{{ route('tournamentSetStatus', [$tournament, \App\Enums\TournamentStatusEnum::CANCELLED]) }}"
                    class="flex items-center px-4 py-2 text-sm text-red-700 hover:bg-gray-100 hover:text-red-900">
                    <x-ui.icon name="cancel" class="mr-2" />
                    {{ __('Cancel') }}
                </a>
                @endif
                @endcan

                <!-- Deletion -->
                @can('delete', $tournament)
                <div class="border-t border-gray-200"></div>
                    
                <button @click="$dispatch('open-modal', 'confirm-tournament-deletion')"
                    class="w-full flex items-center px-4 py-2 text-sm text-red-600 hover:bg-gray-100 hover:text-red-700">
                    <x-ui.icon name="delete" class="mr-2" />
                    {{ __('Delete') }}
                </button>
                @endcan
            
            <!-- Modal to confirm delete tournament -->
                <x-modal name="confirm-tournament-deletion" focusable>
                    <form method="get" action="{{ route('deleteTournament', $tournament) }}" class="p-6">
                        @csrf

                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('Are you sure you want to delete this tournament?') }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('This action is irreversible. All associated data will be permanently removed.') }}
                        </p>

                        <div class="mt-6 flex justify-end">
                            <x-secondary-button x-on:click="$dispatch('close')">
                                {{ __('Cancel') }}
                            </x-secondary-button>

                            <x-danger-button class="ms-3">
                                {{ __('Delete') }}
                            </x-danger-button>
                        </div>
                    </form>
                </x-modal>

            </div>
        </div>
    </div>
    <!-- Boutons d'accès rapide -->
    <a href="{{ route('tournamentsIndex') }}">
        <x-primary-button type="button"
            >
                <span class="mr-2">{{ __('Tournaments list') }}</span>
                <x-ui.icon name="arrow-left"/>
        </x-primary-button>
    </a>
</div>
