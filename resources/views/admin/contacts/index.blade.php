<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <x-layout.page-header title="{{ __('Contact messages') }}" description="{{ __('Manage the contacts from our website  here.') }}" />

        <!-- Barre de filtres -->
        <div class="flex flex-row justify-start mb-6">
            <x-forms.search-input placeholder="{{ __('Search contacts...') }}" wire:model.live.debounce.500ms="search" />

            <div class="flex items-center gap-3 ml-auto">

                <label class="flex flex-row text-xs">
                    <p class="my-auto mr-2">{{ ('Type') }}</p>
                    <x-forms.select-input wire:model.live="type">
                        <option value="">{{ __('All') }}</option>
                        <option value="">{{ __('Discovering') }}</option>
                        <option value="">{{ __('Interclub') }}</option>
                        <option value="">{{ __('Sponsorship') }}</option>
                        <option value="">{{ __('Subscription') }}</option>
                    </x-forms.select-input>
                </label>
                <label class="flex flex-row text-xs">
                    <p class="my-auto mr-2">{{ ('Pagination') }}</p>
                    <x-forms.select-input wire:model.live="perPage">
                        <option value="25">25 par page</option>
                        <option value="50">50 par page</option>
                        <option value="100">100 par page</option>
                    </x-forms.select-input>
                </label>
            </div>
        </div>

        <x-table.container>
            <x-table.header>
                <x-table.header-cell>
                    {{ __('First Name') }}
                </x-table.header-cell>
                <x-table.header-cell>
                    {{ __('Last Name') }}
                </x-table.header-cell>
                <x-table.header-cell>
                    {{ __('Email') }}
                </x-table.header-cell>
                <x-table.header-cell>
                    {{ __('Subject') }}
                </x-table.header-cell>
                <x-table.header-cell>
                    {{ __('Date') }}
                </x-table.header-cell>
                <x-table.header-cell>
                    {{ __('Status') }}
                </x-table.header-cell>
                <x-table.header-cell>
                    {{ __('Actions') }}
                </x-table.header-cell>
            </x-table.header>
            <x-table.body>
                @foreach ($contacts as $contact)
                    <x-table.row>
                        <x-table.cell>
                            {{ $contact->first_name }}
                        </x-table.cell>
                        <x-table.cell>
                            {{ $contact->last_name }}
                        </x-table.cell>
                        <x-table.cell>
                            {{ $contact->email }}
                        </x-table.cell>
                        <x-table.cell>
                            {{ $contact->interest }}
                        </x-table.cell>
                        <x-table.cell>
                            {{ $contact->created_at->format('d M Y H:i') }}
                        </x-table.cell>
                        <x-table.cell>
                            {{ $contact->status }}
                        </x-table.cell>
                        <x-table.cell>
                             <x-table.actions-in-cell>
                                <x-ui.action-button 
                                    variant="default" 
                                    icon="inspect" 
                                    tooltip="{{ __('Check details') }}"
                                    onclick="window.location.href='{{ route('admin.contacts.show', $contact) }}'">
                                </x-ui.action-button>
                                @can('delete', Auth()->user())
                                    <x-ui.action-button
                                        type="button"
                                        variant="danger"
                                        icon="delete"
                                        tooltip="{{ __('Delete user') }}"
                                        @click="$wire.selectedUserId = {{ $contact->id }}; $dispatch('open-modal', 'confirm-delete-user')">                                    {{-- wire:click="destroy({{ $user }})"
                                        wire:confirm.prompt="Are you sure you want to delete {{ $contact->first_name }} {{ $contact->last_name }}? Type DELETE if you are sure.|DELETE"> --}}
                                    </x-ui.action-button>
                                @endcan
                            </x-table.actions-in-cell>
                        </x-table.cell>
                    </x-table-row>
                @endforeach
            </x-table.body>
        </x-table.container>


{{ $contacts->links() }}    
    </x-admin-block>    

</x-app-layout>