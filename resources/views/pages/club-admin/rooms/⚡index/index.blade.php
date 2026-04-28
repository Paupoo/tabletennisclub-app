<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <!-- HEADER -->
    <x-header title="{{ __('Rooms') }}" separator progress-indicator>

        <x-slot:actions>
            @can('create', \App\Models\ClubAdmin\Club\Room::class)
                <x-button label="{{ __('Create') }}" class="btn-primary btn-sm" responsive link="{{ route('admin.rooms.create') }}" />
            @endcan
        </x-slot:actions>
    </x-header>

    <div class="mt-6 grid lg:grid-cols-2 xl:grid-cols-3 gap-6">
        @foreach ($rooms as $room)
            <x-card title="{{ $room->name }}" shadow class="relative">

                <x-slot:figure class="hidden lg:block">
                    <img src="https://picsum.photos/200/300?random={{ $loop->iteration }}" alt=""
                        class="w-full h-48 object-cover">
                </x-slot:figure>

                <div class="mb-2">
                    <div class="flex items-center gap-2 mb-4">
                        <x-admin.shared.tables-counter :total_tables="$room->tables()->count()" />
                        <x-admin.shared.tables-capacity-counter :training_capacity="$room?->capacity_for_trainings" :interclub_capacity="$room?->capacity_for_interclubs" />
                    </div>
                    <div class="flex items-center gap-1 text-sm text-gray-500 mb-6">
                        <x-icon name="o-map-pin" class="w-4 h-4" />
                        <span>{{ $room->street }}, {{ $room->city_code }} {{ $room->city_name }}</span>
                    </div>
                    @foreach ($room->trainings as $training)
                        <x-admin.shared.compact-event-preview link="#" :organizer="$training->trainer->first_name . ' ' . $training->trainer->last_name" :name="$training->type"
                            :startDateTime="$training->start" type="training" />
                    @endforeach
                    @foreach ($room->interclubs as $interclub)
                        <x-admin.shared.compact-event-preview link="#" :name="$interclub->week_number" :startDateTime="$interclub->start_date_time" type="interclub" />
                    @endforeach
                    @foreach ($room->tournaments as $tournament)
                        <x-admin.shared.compact-event-preview link="#" :name="$tournament->name" :startDateTime="$tournament->start_date"
                            :remainingSlots="$tournament->max_users - $tournament->users()->count()" type="tournament">
                            <x-slot:actions>
                                <x-button class="btn-outline btn-xs" label="{{ __('Register') }}" link="" />
                            </x-slot:actions>
                        </x-admin.shared.compact-event-preview>
                    @endforeach
                </div>
                <x-slot:actions>
                    @can('update', $room)
                        <x-button class="btn-primary btn-outline btn-sm" label="{{ __('Modify') }}"
                            link="{{ route('admin.rooms.edit', $room) }}" />
                    @endcan

                    @can('delete', $room)
                        <x-button class="btn-error btn-outline btn-sm" label="{{ __('Delete') }}"
                            wire:click="delete({{ $room->id }})" wire:confirm="{{ __('Are you sure?') }}" />
                    @endcan
                </x-slot:actions>
            </x-card>
        @endforeach
    </div>
</div>
