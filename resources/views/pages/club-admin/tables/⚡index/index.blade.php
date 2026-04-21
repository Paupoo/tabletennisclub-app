<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header title="Gestion des Tables" subtitle="Liste de tout le matériel" separator progress-indicator>
        <x-slot:middle class="justify-end">
            <x-input placeholder="Rechercher..." wire:model.live.debounce.300ms="search" icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            @can('create', \App\Models\ClubAdmin\Club\Table::class)
            <x-button label="{{ __('Create') }}" icon="o-plus" class="btn-primary btn-sm" link="{{ route('admin.tables.create') }}" />
            <x-button x-on:click="$wire.$refresh()" label="{{ __('Refresh') }}" class="btn-outline btn-sm"/>
            @endcan
        </x-slot:actions>
    </x-header>

    <div class="space-y-4 mt-6">
        @forelse ($groupedTables as $group)
            @php
                $room = $group['room'];
                $roomDisplay = $group['room_display'];
                $tablesInRoom = $group['tables'];
            @endphp

            <x-collapse class="bg-base-100 border border-base-300 shadow-sm">
                <x-slot:heading>
                    <div class="flex items-center justify-between w-full pr-4">
                        <div class="flex items-center gap-3">
                            <x-icon name="o-map-pin" class="w-5 h-5 text-primary" />
                            <div>
                                <h3 class="font-bold text-lg leading-none mb-2">{{ $roomDisplay }}</h3>
                                <div class="flex items-center gap-2">
                                    {{-- Badge : Tables prêtes pour la compétition --}}
                                    @if ($room)
                                    <x-admin.shared.tables-counter
                                        :total_tables="$tablesInRoom->count()" />
                                    <x-admin.shared.tables-capacity-counter
                                        :training_capacity="$room?->capacity_for_trainings"
                                        :interclub_capacity="$room?->capacity_for_interclubs" />
                                    @else
                                    <x-admin.shared.tables-counter :total_tables="$tablesInRoom->count()" />
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </x-slot:heading>

                <x-slot:content>
                    {{-- --- VUE DESKTOP : Table classique --- --}}

                    <div class="hidden md:block overflow-x-auto">
                        <x-table :headers="$headers" :rows="$tablesInRoom" class="table-sm">
                            @scope('cell_name', $table)
                                <span class="font-bold">{{ $table->name }}</span>
                            @endscope

                            {{-- On affiche la date d'achat au lieu de la marque/modèle --}}
                            @scope('cell_purchased_on', $table)
                                @if ($table->purchased_on)
                                    <span class="font-medium">{{ $table->purchased_on->format('d/m/Y') }}</span>
                                @else
                                    <span class="text-xs opacity-50">{{ __('Non renseigné') }}</span>
                                @endif
                            @endscope

                            @scope('cell_state', $table)
                                <x-badge :value="$table->state" class="badge-neutral w-30 text-xs" />
                            @endscope

                            @scope('cell_is_competition_ready', $table)
                                @if ($table->is_competition_ready)
                                    <x-badge value="{{ __('Yes') }}" class="badge-success text-xs" />
                                @else
                                    <x-badge value="{{ __('No') }}" class="badge-error text-xs" />
                                @endif
                            @endscope

                            @scope('actions', $table)
                                @canany(['edit', 'delete'], $table)
                                <x-admin.shared.row-actions>
                                    @can('edit', $table)
                                        <x-menu-item icon="o-pencil" link="{{ route('admin.tables.edit', $table) }}"
                                            title="{{ __('Edit') }}" class="text-xs" />
                                        @if ($table->room)
                                            <x-menu-item icon="o-lock-open" wire:click="confirmUnlink({{ $table }})" spinner
                                                title="{{ __('Unlink') }}" class="text-xs" />
                                        @endif
                                    @endcan
                                   
                                    @can('delete', $table)
                                        <x-menu-separator />
                                        <x-menu-item class="text-error text-xs" icon="o-trash" wire:click="confirmDelete({{ $table }})" title="{{ __('Delete') }}" />
                                    @endcan
                                </x-admin.shared.row-actions>
                                @endcanany
                            @endscope
                        </x-table>
                    </div>

                    {{-- --- VUE MOBILE : Liste de cartes --- --}}
                    <div class="md:hidden divide-y divide-base-200">
                        @foreach ($tablesInRoom as $table)
                            <div class="py-4 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div>
                                        <div class="font-bold text-sm text-primary">{{ $table->name }}</div>
                                        <div class="text-xs font-medium">
                                            {{ __('Purchased on:') }}
                                            {{ $table->purchased_on ? $table->purchased_on->format('d/m/Y') : 'N/A' }}
                                        </div>
                                        <div class="text-xs font-medium">
                                            {{ __('Competition ready:') }}
                                            {{ $table->is_competition_ready ? __('Yes') : __('No') }}
                                        </div>
                                        <div class="text-[10px] uppercase tracking-wider opacity-60 mt-1">
                                            {{ __('State:') }} {{ $table->state ?? 'N/A' }}</div>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <x-button icon="o-pencil" link="{{ route('admin.tables.edit', $table) }}"
                                        class="btn-sm btn-circle btn-ghost border border-base-300" />
                                    <x-button icon="o-trash"
                                        class="btn-sm btn-circle btn-ghost text-error border border-base-300" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-slot:content>
            </x-collapse>
        @empty
            <div class="text-center py-10 opacity-50">
                {{ __('Aucune table trouvée.') }}
            </div>
        @endforelse
    </div>

    {{-- Modals --}}
    <x-modal subtitle="{{ __('Warning!') }}" title="{{ __('Confirm unlink') }}" wire:model="unlinkModal">
        <p>{{ __('Are you sure you want to unlink the table from its room?') }}</p>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" wire:click="$set('unlinkModal', false)" />
            <x-button class="btn-error" label="{{ __('Delete') }}" spinner wire:click="unlink" />
        </x-slot:actions>
    </x-modal>

    <x-modal subtitle="{{ __('Warning!') }}" title="{{ __('Confirm deletion') }}" wire:model="deleteModal">
        <p>{{ __('Are you sure you want to delete this table? This action is irreversible.') }}</p>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" wire:click="$set('deleteModal', false)" />
            <x-button class="btn-error" label="{{ __('Delete') }}" spinner wire:click="delete" />
        </x-slot:actions>
    </x-modal>

</div>
