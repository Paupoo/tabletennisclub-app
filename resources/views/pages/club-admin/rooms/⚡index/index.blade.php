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
                        <x-admin.shared.compact-event-preview link="#" :organizer="$training->trainer ? $training->trainer->first_name . ' ' . $training->trainer->last_name : null" :name="$training->type"
                            :startDateTime="$training->start" type="training" />
                    @endforeach
                    @foreach ($room->interclubs as $interclub)
                        <x-admin.shared.compact-event-preview link="#" :name="$interclub->week_number" :startDateTime="$interclub->start_date_time" type="interclub" />
                    @endforeach
                    @foreach ($room->tournaments as $tournament)
                        @php
                            $reg = $tournament->users->first()?->pivot;
                            $regStatus = $reg?->registration_status;
                            $isActive = in_array($regStatus, ['registered', 'confirmed', 'spot_offered']);
                            $isWaiting = $regStatus === 'waiting';
                            $isFull = $tournament->max_users > 0
                                && $tournament->active_registrations_count >= $tournament->max_users;
                            $remaining = $tournament->max_users > 0
                                ? max(0, $tournament->max_users - $tournament->active_registrations_count)
                                : null;
                        @endphp
                        <x-admin.shared.compact-event-preview link="#" :name="$tournament->name"
                            :startDateTime="$tournament->start_date" :remainingSlots="$remaining" type="tournament">
                            <x-slot:actions>
                                @if ($isActive)
                                    <x-badge class="badge-success badge-sm" value="{{ __('Registered') }}" />
                                    <x-button class="btn-ghost btn-xs text-error" icon="o-x-circle"
                                        label="{{ __('Cancel') }}"
                                        wire:click="cancelRegistration({{ $tournament->id }})"
                                        wire:confirm="{{ __('Cancel your registration?') }}" />
                                @elseif ($isWaiting)
                                    <x-badge class="badge-warning badge-sm" value="{{ __('Waitlisted') }}" />
                                    <x-button class="btn-ghost btn-xs text-error" icon="o-x-circle"
                                        label="{{ __('Leave') }}"
                                        wire:click="cancelRegistration({{ $tournament->id }})"
                                        wire:confirm="{{ __('Leave the waitlist?') }}" />
                                @elseif ($isFull)
                                    <x-badge class="badge-ghost badge-sm" value="{{ __('Full') }}" />
                                    <x-button class="btn-outline btn-xs btn-warning"
                                        label="{{ __('Waitlist') }}"
                                        wire:click="register({{ $tournament->id }})" />
                                @else
                                    <x-button class="btn-primary btn-outline btn-xs"
                                        label="{{ __('Register') }}"
                                        wire:click="register({{ $tournament->id }})" />
                                @endif
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
