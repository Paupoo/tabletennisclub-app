<!-- Update subscription as a user -->
                @can('updateSubscriptionAsUser', $tournament)
                    @if (!$tournament->users->contains(auth()->user()->id))
                        <x-menus.action-menu-item :href="route('tournament.register', [$tournament->id, auth()->user()->id])"
                            :icon="'join'"
                            :text="__('Register')" />
                    @elseif($tournament->users->contains(auth()->user()->id))
                        <x-menus.action-menu-item :href="route('tournament.register', [$tournament->id, auth()->user()->id])"
                            :icon="'leave'"
                            :text="__('Unregister')"/>
                            
                    @endif
                @endcan
                {{-- Updates --}}
                @can('update', $tournament)
                    <!-- Composant Livewire pour l'inscription de joueur -->
                    <livewire:tournament.player-registration :tournament="$tournament" />
                    <x-menus.separator />
                    <x-menus.action-menu-item :href="route('tournament.edit', $tournament)"
                            :icon="'modify'"
                            :text="__('Modify')"/>
                    <x-menus.action-menu-item :icon="'duplicate'"
                            :text="__('Duplicate (TO DO)')"/>
                    @if (in_array(\App\Enums\TournamentStatusEnum::DRAFT, $statusesAllowed))
                        <x-menus.action-menu-item :href="route('tournamentSetStatus', [$tournament, \App\Enums\TournamentStatusEnum::DRAFT])"
                                :icon="'pen'"
                                :text="__('Draft')"/>    
                    @endif
                    @if (in_array(\App\Enums\TournamentStatusEnum::LOCKED, $statusesAllowed))
                        <x-menus.action-menu-item :href="route('tournamentSetStatus', [$tournament, \App\Enums\TournamentStatusEnum::LOCKED])"
                            :icon="'locked'"
                            :text="__('Lock')" />
                        </a>
                    @endif
                    @if (in_array(\App\Enums\TournamentStatusEnum::PUBLISHED, $statusesAllowed))
                        <x-menus.action-menu-item :href="route('tournamentSetStatus', [$tournament, \App\Enums\TournamentStatusEnum::PUBLISHED])"
                            :icon="'unlocked'"
                            :text="__('Publish/Unlock')"/>
                    @endif
                    @if (in_array(\App\Enums\TournamentStatusEnum::PENDING, $statusesAllowed))
                        <x-menus.action-menu-item :href="route('tournamentSetStatus', [$tournament, \App\Enums\TournamentStatusEnum::PENDING])"
                            :icon="'rocket-launch'"
                            :text="__('Start')" />
                        </a>
                    @endif
                    @if (in_array(\App\Enums\TournamentStatusEnum::CLOSED, $statusesAllowed))
                        <x-menus.action-menu-item :href="route('tournamentSetStatus', [$tournament, \App\Enums\TournamentStatusEnum::CLOSED])"
                            :icon="'flag'"
                            :text="__('Close')" />
                    @endif
                    @if (in_array(\App\Enums\TournamentStatusEnum::CANCELLED, $statusesAllowed))
                        <x-menus.action-menu-item :href="route('tournamentSetStatus', [$tournament, \App\Enums\TournamentStatusEnum::CANCELLED])"
                            :icon="'cancel'"
                            :textColor="'text-red-600'"
                            :textHover="'text-red-700'"
                            :text="__('Cancel')" />
                        </a>
                    @endif
                @endcan

                <!-- Deletion -->
                @can('delete', $tournament)
                    <x-menus.separator />
                    <button @click="$dispatch('open-modal', 'confirm-tournament-deletion')"
                        class="w-full flex items-center px-4 py-2 text-sm text-red-600 hover:bg-gray-100 hover:text-red-700">
                        <x-ui.icon name="delete" class="mr-2" />
                        {{ __('Delete') }}
                    </button>
                @endcan