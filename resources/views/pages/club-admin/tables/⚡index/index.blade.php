<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header title="Gestion des Tables" subtitle="Liste de tout le matériel" separator progress-indicator>
        <x-slot:middle class="justify-end">
            <x-input placeholder="Rechercher..." wire:model.live.debounce.300ms="search" icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            @can('create', \App\Domains\ClubAdmin\Club\Models\Table::class)
            <x-button :label="__('Create')" icon="o-plus" class="btn-primary btn-sm" link="{{ route('admin.tables.create') }}" />
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
                                <x-badge :value="$table->state->getLabel()" class="badge-neutral w-30 text-xs" />
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
                                        <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil"
                                            :tooltip="__('Edit')" link="{{ route('admin.tables.edit', $table) }}" />
                                        @if ($table->room)
                                            <x-button class="btn-ghost btn-sm btn-circle" icon="o-lock-open"
                                                :tooltip="__('Unlink')" wire:click="confirmUnlink({{ $table }})" spinner />
                                        @endif
                                    @endcan
                                    @can('delete', $table)
                                        <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-trash"
                                            :tooltip="__('Delete')" wire:click="confirmDelete({{ $table }})" />
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
                                            {{ __('State:') }} {{ $table->state->getLabel() }}</div>
                                    </div>
                                </div>

                                <x-admin.shared.row-actions>
                                    @can('edit', $table)
                                        <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil"
                                            :tooltip="__('Edit')" link="{{ route('admin.tables.edit', $table) }}" />
                                    @endcan
                                    @can('delete', $table)
                                        <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-trash"
                                            :tooltip="__('Delete')" wire:click="confirmDelete({{ $table->id }})" />
                                    @endcan
                                </x-admin.shared.row-actions>
                            </div>
                        @endforeach
                    </div>
                </x-slot:content>
            </x-collapse>
        @empty
            <x-empty-state
                icon="o-table-cells"
                :heading="__('No tables found')"
                :message="__('Try adjusting your search or create the first table.')"
                :buttonText="__('Create table')"
                href="{{ route('admin.tables.create') }}" />
        @endforelse
    </div>

    {{-- Modals --}}
    <x-confirm-modal model="unlinkModal" :title="__('Confirm unlink')" :subtitle="__('Warning!')"
        :confirmLabel="__('Delete')" confirmAction="unlink">
        <p>{{ __('Are you sure you want to unlink the table from its room?') }}</p>
    </x-confirm-modal>

    <x-confirm-modal model="deleteModal" :title="__('Confirm deletion')" :subtitle="__('Warning!')"
        :confirmLabel="__('Delete')" confirmAction="delete">
        <p>{{ __('Are you sure you want to delete this table? This action is irreversible.') }}</p>
    </x-confirm-modal>

</div>
