<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header title="{{ __('Create a new room') }}" />
    <x-form wire:submit='save'>
        <div class="grid grid-cols-5 gap-6">
            <div class="col-span-2">
                <x-header title="{{ __('Basic') }}" subtitle="{{ __('Name and location') }}" size="text-xl" />
            </div>

            <div class="col-span-3 space-y-4">
                <div class="grid lg:grid-cols-2 gap-4">
                    <x-input label="{{ __('Room Name*') }}" wire:model.live.debounce.750ms="name"
                        hint="{{ __('This will be used to identify the room') }}" />
                    <x-input label="{{ __('Floor') }}" wire:model.live.debounce.750ms="floor"
                        hint="{{ __('Specify the floor if any') }}" />
                    <x-input label="{{ __('Building Name*') }}" class="col-span-2"
                        wire:model.live.debounce.750ms="building_name" />
                    <x-input label="{{ __('Street') }}" class="col-span-2" wire:model.live.debounce.750ms="street" />
                    <x-input label="{{ __('city Code*') }}" wire:model.live.debounce.750ms="city_code" type="number"
                        inputmode="numeric" pattern="[0-9]*" autocomplete="city-code" min="1000" max="9999" />
                    <x-input label="{{ __('City*') }}" wire:model.live.debounce.750ms="city_name" />
                </div>
                <x-textarea label="{{ __('Access Descriptions*') }}"
                    wire:model.live.debounce.750ms="access_description" hint="{{ __('Max 255 characters') }}"
                    rows="3" />
            </div>

            <div class="col-span-5">
                <x-menu-separator class="my-4" />
            </div>

            <div class="col-span-2">
                <x-header title="{{ __('Capacity') }}"
                    subtitle="{{ __('Define capacity for matches and trainings to avoid overbooking') }}"
                    size="text-xl" />
            </div>
            <div class="grid lg:grid-cols-1 gap-4">
                <x-input icon="o-academic-cap" label="{{ __('Training Capacity') }}"
                    wire:model.live="capacity_for_trainings"
                    hint="{{ __('This is used mainly to manage training subscriptions') }}" decimal required />
                <x-input icon="o-trophy" label="{{ __('Matches Capacity') }}" wire:model.live="capacity_for_interclubs"
                    hint="{{ __('This is used mainly to manage interclubs and tournament') }}" decimal required />
            </div>
            <div class="col-span-5">
                <x-menu-separator class="my-4" />
            </div>

            <div class="col-span-2">
                <x-header title="{{ __('Inventory') }}" subtitle="{{ __('Assign specific tables to this room') }}"
                    size="text-xl" />
            </div>

            <div class="col-span-3">
                <div class="flex items-end gap-2 mb-2">
                    <div class="grow">
                        <x-choices label="{{ __('Assign Tables') }}" wire:model="selectedTables" :options="$filteredTables"
                            placeholder="{{ __('Search for a table...') }}" icon="o-magnifying-glass"
                            search-function="searchTables" searchable>
                            {{-- Personnalisation de l'affichage dans la liste déroulante --}}
                            @scope('item', $table)
                                <x-list-item :item="$table">
                                    <x-slot:avatar>
                                        <x-icon name="o-squares-2x2" class="w-5 h-5 text-primary" />
                                    </x-slot:avatar>

                                    <x-slot:sub-value>
                                        {{ __('Purchased') }}: {{ $table['purchased_on'] }} -- {{ __('State') }}:
                                        {{ $table['state'] }}
                                    </x-slot:sub-value>


                                </x-list-item>
                            @endscope
                        </x-choices>
                    </div>
                    <x-button icon="o-plus" class="btn-primary" @click="$wire.showTableModal = true" />
                </div>

            </div>
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Reset') }}" type="button" wire:click.stop="clearForm()" />
            <x-button label="{{ $this->room->exists ? __('Update') : __('Create') }}" type="submit"
                class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-form>

    {{-- Le Modal de création de table --}}
    <x-modal wire:model="showTableModal" title="{{ __('Create a new table') }}" separator>
        <div class="space-y-4">
            <x-input label="{{ __('Table Name') }}" wire:model.live.debounce="newTableName"
                placeholder="Ex: Table 16" required />
            <x-input label="{{ __('Brand') }}" wire:model.live.debounce="newTableBrand"
                placeholder="Ex: Stiga" />
            <x-input label="{{ __('Model') }}" wire:model.live.debounce="newTableModel"
                placeholder="Ex: 2000 S Pro" />

            <x-select label="{{ __('State') }}" wire:model.live.debounce="newTableState" :options="[
                ['id' => 'new', 'name' => 'New'],
                ['id' => 'used', 'name' => 'Used'],
                ['id' => 'damaged', 'name' => 'Damaged'],
            ]" required />

            <x-datepicker label="{{ __('Purchased on') }}" wire:model.live.debounce="newTablePurchasedOn" />
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" @click="$wire.showTableModal = false" />
            <x-button label="{{ __('Add to list') }}" class="btn-primary" wire:click="addTableToList" spinner />
        </x-slot:actions>
    </x-modal>
</div>
