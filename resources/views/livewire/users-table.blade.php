<div>
    <!-- En-tête de page -->
    <x-layout.page-header title="{{ __('Users list') }}" description="{{ __('Manage the users from here.') }}" />

    <!-- Barre de filtres -->
    <div class="flex flex-row justify-start mb-6">
        <x-forms.search-input placeholder="{{ __('Search users...') }}" wire:model.live.debounce.500ms="search" />

        <div class="flex items-center gap-3 ml-auto">
            <label class="flex flex-row text-xs">
                <p class="my-auto mr-2">{{ ('Type') }}</p>
                <x-forms.select-input wire:model.live="competitor">
                    <option value="">{{ __('All') }}</option>
                    <option value="1">{{ __('Competitor') }}</option>
                    <option value="0">{{ __('Casual') }}</option>
                </x-forms.select-input>
            </label>

            <label class="flex flex-row text-xs">
                <p class="my-auto mr-2">{{ ('Sex') }}</p>
                <x-forms.select-input wire:model.live="sex">
                    <option value="">{{ __('All') }}</option>
                    <option value="{{ \App\Enums\Sex::WOMEN->name }}">{{ __('Women') }}</option>
                    <option value="{{ \App\Enums\Sex::MEN->name }}">{{ __('Men') }}</option>
                    <option value="{{ \App\Enums\Sex::OTHER->name }}">{{ __('Others') }}</option>
                </x-forms.select-input>
            </label>
            <label class="flex flex-row text-xs">
                <p class="my-auto mr-2">{{ ('Pagination') }}</p>
                <x-forms.select-input wire:model.live="perPage">
                    <option value="10">10 par page</option>
                    <option value="20">20 par page</option>
                    <option value="50">50 par page</option>
                    <option value="0">Tous</option>
                </x-forms.select-input>
            </label>
        </div>
    </div>

    {{-- Tableau des utilisateurs --}}
    <x-table.container>
        <x-table.header>
            <x-table.row>
                <x-table.header-cell wire:click="sortBy('last_name')">{{ __('Last Name') }}</x-table.header-cell>
                <x-table.header-cell wire:click="sortBy('first_name')">{{ __('First Name') }}</x-table.header-cell>
                <x-table.header-cell wire:click="sortBy('force_list')">{{ __('Force Index') }}</x-table.header-cell>
                <x-table.header-cell wire:click="sortBy('ranking')">{{ __('Ranking') }}</x-table.header-cell>
                <x-table.header-cell wire:click="sortBy('team_id')">{{ __('Teams') }}</x-table.header-cell>
                <x-table.header-cell wire:click="sortBy('is_active')">{{ __('Active') }}</x-table.header-cell>
                <x-table.header-cell
                    wire:click="sortBy('is_competitor')">{{ __('Competitor') }}</x-table.header-cell>
                <x-table.header-cell wire:click="sortBy('has_debt')">{{ __('Has Debts') }}</x-table.header-cell>
                <x-table.header-cell>{{ __('Actions') }}</x-table.header-cell>
            </x-table.row>
        </x-table.header>
        <x-table.body>
            @foreach ($users as $user)
                <x-table.row wire:key="{{ $user->id }}"
                    class="border-b dark:border-neutral-500">
                    <x-table.cell>
                        {{-- @if ($user->sex === \App\Enums\Sex::MEN->name)
                            &#9794;
                        @elseif ($user->sex === \App\Enums\Sex::WOMEN->name)
                            &#9792;
                        @else
                            &#9892;
                        @endif --}}
                        {{ $user->last_name }}
                    </x-table.cell>
                    <x-table.cell>{{ $user->first_name }}</x-table.cell>
                    <x-table.cell>{{ $user->force_list }}</x-table.cell>
                    <x-table.cell>{{ $user->ranking }}</x-table.cell>
                    <x-table.cell>
                        @if ($user->teams->count() > 0)
                            @foreach ($user->teams->sortBy('name') as $team)
                                <a href="{{ route('teams.show', $team) }}">{{ $team->name }}</a>
                                @if (!$loop->last)
                                    {{-- This is not the last iteration --}}
                                    {{ ' | ' }}
                                @endif
                            @endforeach
                        @else
                            {{ __('No team') }}
                        @endif
                    </x-table.cell>
                    <x-table.cell>
                        @if ($user->is_active == false)
                            {{ __('Inactive') }}
                        @else
                            {{ __('Active') }}
                        @endif
                    </x-table.cell>
                    <x-table.cell>
                        @if ($user->is_competitor == false)
                            {{ __('Casual') }}
                        @else
                            {{ __('Competitor') }}
                        @endif
                    </x-table.cell>
                    <x-table.cell>
                        @if ($user->has_debt == false)
                            {{ __('No') }}
                        @else
                            {{ __('Yes') }}
                        @endif
                    </x-table.cell>
                    <x-table.cell>
                        <x-table.actions-in-cell>
                            <x-ui.action-button 
                                variant="default" 
                                icon="inspect" 
                                tooltip="{{ __('Check details') }}"
                                onclick="window.location.href='{{ route('users.show', $user->id) }}'">
                            </x-ui.action-button>
                            @can('update', $user_model)
                            <x-ui.action-button 
                                variant="default" 
                                icon="modify" 
                                tooltip="{{ __('Modify user details') }}"
                                onclick="window.location.href='{{ route('users.edit', $user) }}'">
                            </x-ui.action-button>
                            @endcan
                            @can('delete', $user_model)
                                <x-ui.action-button
                                    type="button"
                                    variant="danger"
                                    icon="delete"
                                    tooltip="{{ __('Delete user') }}"
                                    @click="$dispatch('open-modal', 'confirm-delete-user')">
                                    {{-- wire:click="destroy({{ $user }})"
                                    wire:confirm.prompt="Are you sure you want to delete {{ $user->first_name }} {{ $user->last_name }}? Type DELETE if you are sure.|DELETE"> --}}
                                </x-ui.action-button>
                            @endcan
                        </x-table.actions-in-cell>    
                    </x-table.cell>
                </x-table.row>
            @endforeach
        </x-table.body>
    </x-table.container>

    {{-- Pagination --}}
    <div name="pagination" class="mt-4">
        {{ $users->links('custom-paginate') }}
    </div>

    <!-- Légende -->
    <x-ui.legend>
        
        <x-ui.legend-item icon="draft" icon-class="text-gray-400">
            Mettre en brouillon
        </x-ui.legend-item>

        <x-ui.legend-item icon="edit" icon-class="text-indigo-600">
            Modifier
        </x-ui.legend-item>

        <x-ui.legend-item icon="delete" icon-class="text-red-600">
            Supprimer
        </x-ui.legend-item>
    </x-ui.legend>

    <!-- Modal to confirm reset force index -->
    <x-modal name="confirm-delete-user" focusable>
        <form method="get" wire:submit="destroy({{ $user }})" class="p-6" x-data="{ confirmText: '', isValid() { return this.confirmText === 'DELETE' } }">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Are you sure you want to delete ' . $user->first_name . ' ' . $user->last_name) }}
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('This action is irreversible. All associated data will be permanently deleted.') }}
            </p>

            <!-- Champ de confirmation -->
            <div class="mt-4">
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

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>
                
                <x-danger-button 
                    class="ms-3" 
                    x-bind:disabled="!isValid()"
                    x-bind:class="{ 'opacity-50 cursor-not-allowed': !isValid() }"
                >
                    {{ __('Delete') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</div>
