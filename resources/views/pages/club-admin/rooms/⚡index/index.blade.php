<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header :title="__('Rooms')" :subtitle="__('Rooms and their table stock')" separator progress-indicator>
        <x-slot:actions>
            @can('create', \App\Domains\ClubAdmin\Club\Models\Room::class)
                <x-button :label="__('Create')" icon="o-plus" class="btn-primary btn-sm"
                    link="{{ route('admin.rooms.create') }}" />
            @endcan
        </x-slot:actions>
    </x-header>

    <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($rooms as $room)
            <x-card class="shadow-sm transition-colors hover:border-primary" wire:key="room-{{ $room->id }}">
                <a href="{{ route('admin.rooms.show', $room) }}" class="block space-y-4">
                    <div>
                        <h3 class="text-lg font-bold">{{ $room->name }}</h3>
                        @if ($room->building_name)
                            <p class="text-xs text-base-content/50">{{ $room->building_name }}</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <x-admin.shared.tables-counter :count="$room->tables_count" />
                        <x-admin.shared.tables-capacity-counter
                            :training_capacity="$room->capacity_for_trainings"
                            :interclub_capacity="$room->capacity_for_interclubs" />
                    </div>

                    <div class="flex items-center gap-1 text-sm text-base-content/60">
                        <x-icon name="o-map-pin" class="h-4 w-4 shrink-0" />
                        <span>{{ $room->street }}, {{ $room->city_code }} {{ $room->city_name }}</span>
                    </div>

                    @php
                        $upcoming = $room->trainings_count + $room->interclubs_count + $room->tournaments_count;
                    @endphp
                    <div class="flex items-center gap-1 text-xs text-base-content/50">
                        <x-icon name="o-calendar-days" class="h-3.5 w-3.5 shrink-0" />
                        <span>
                            {{ $upcoming > 0
                                ? trans_choice(':count event in the next two weeks|:count events in the next two weeks', $upcoming)
                                : __('Nothing planned in the next two weeks.') }}
                        </span>
                    </div>
                </a>

                <x-slot:actions>
                    @can('update', $room)
                        <x-button class="btn-ghost btn-sm" :label="__('Modify')"
                            link="{{ route('admin.rooms.edit', $room) }}" />
                    @endcan

                    @can('delete', $room)
                        <x-button class="btn-ghost btn-sm text-error" :label="__('Delete')"
                            wire:click="confirmDeleteRoom({{ $room->id }})" />
                    @endcan
                </x-slot:actions>
            </x-card>
        @empty
            <div class="col-span-full">
                <x-empty-state
                    icon="o-home"
                    :heading="__('No rooms yet')"
                    :message="__('Create the first room to start organizing your equipment.')"
                    :buttonText="__('Create room')"
                    href="{{ route('admin.rooms.create') }}" />
            </div>
        @endforelse

    </div>

    {{-- Tables sans salle. `room_id` est nullable, donc elles doivent rester
         joignables — mais une orpheline n'est pas un lieu, elle n'a donc pas sa
         place dans la grille des salles. Section absente tant qu'il n'y en a pas. --}}
    @if ($unassignedTables->isNotEmpty())
        <div class="mt-10">
            <div class="mb-3 flex items-center gap-3">
                <h2 class="text-xs font-bold uppercase tracking-widest text-muted">{{ __('Unassigned') }}</h2>
                <div class="h-px flex-1 bg-base-300"></div>
            </div>

            <x-card class="shadow-sm">
                <p class="text-xs opacity-60">{{ __('Tables not linked to any room') }}</p>

                <div class="mt-3 divide-y divide-base-200">
                    @foreach ($unassignedTables as $table)
                        <div class="flex items-center justify-between gap-3 py-2"
                            wire:key="unassigned-{{ $table->id }}">
                            <div>
                                <div class="text-sm font-medium">{{ $table->name }}</div>
                                <div class="text-xs text-base-content/50">{{ $table->state->getLabel() }}</div>
                            </div>
                            @can('update', $table)
                                <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil"
                                    :tooltip="__('Edit')" link="{{ route('admin.tables.edit', $table) }}" :aria-label="__('Edit')" />
                            @endcan
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>
    @endif

    @can('create', \App\Domains\ClubAdmin\Club\Models\Room::class)
        <x-confirm-modal model="deleteRoomModal" :title="__('Delete this room?')" :subtitle="__('Warning!')"
            :confirmLabel="__('Delete')" confirmAction="deleteRoom" :open="$deleteRoomModal">
            <p>{{ __('Are you sure you want to delete this room? This action is irreversible.') }}</p>
        </x-confirm-modal>
    @endcan
</div>
