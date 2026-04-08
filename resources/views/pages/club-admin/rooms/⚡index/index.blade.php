<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <!-- HEADER -->
    <x-header title="{{ __('Rooms') }}" separator progress-indicator>

        <x-slot:actions>
            <x-button label="Create" class="btn-primary btn-sm" responsive link="{{ route('admin.rooms.create') }}" />
        </x-slot:actions>
    </x-header>

    <div class="mt-6 grid lg:grid-cols-2 xl:grid-cols-3 gap-6">
        @foreach($rooms as $room)
        <x-card title="{{ $room->name }}" shadow class="relative">

            <x-slot:figure class="hidden lg:block">
                <img src="https://picsum.photos/200/300?random={{ $loop->iteration }}" alt="" class="w-full h-48 object-cover">
            </x-slot:figure>

            <div class="mb-2">
                <div class="flex items-center gap-2 mb-4">
                    <div class="badge badge-outline badge-sm gap-2 py-3 px-3">
                        <x-icon name="o-square-3-stack-3d" class="w-3.5 h-3.5" />
                        <span class="font-medium text-xs">{{ $room->tables()->where('is_competition_ready', true)->count() }} / {{ $room->tables()->count() }}
                            {{ __('tables') }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-1 text-sm text-gray-500 mb-6">
                    <x-icon name="o-map-pin" class="w-4 h-4" />
                    <span>{{ $room->street }}, {{ $room->city_code }} {{ $room->city_name }}</span>
                </div>
                @foreach ($room->trainings as $training)
                <x-admin.shared.compact-event-preview link="#" :organizer="$training->trainer->first_name . ' ' . $training->trainer->last_name"
                    :name="$training->type" :startDateTime="$training->start" type="training" />  
                @endforeach
                @foreach ($room->tournaments as $tournament)
                <x-admin.shared.compact-event-preview link="#"
                    :name="$tournament->name" :startDateTime="$tournament->start_date" :remainingSlots="$tournament->max_users - $tournament->users()->count()" type="tournament">  
                    <x-slot:actions>
                        <x-button class="btn-outline btn-xs" label="{{ __('Register') }}" link="" />
                    </x-slot:actions>
                </x-admin.shared.compact-event-preview>
                @endforeach
                
                <x-admin.shared.compact-event-preview link="https://www.perdu.com" location="Demeester -0"
                    name="CTTOB A vs Auderghem F" startDateTime="2026-02-13 19:45" type="interclub" />
            </div>
            <x-slot:actions>
                <x-button class="btn-primary btn-outline btn-sm" label="{{ __('Modify') }}" link="{{ route('admin.rooms.edit', $room) }}" />
                <x-button class="btn-error btn-outline btn-sm" label="{{ __('Delete') }}" wire:click="destroy({{ $room->id }})" wire:confirm="{{ __('Are you sure you want to delete this room? This action cannot be undone.')}}" />
            </x-slot:actions>
        </x-card>
        @endforeach
    </div>
</div>