<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header :title="$room->name" :subtitle="$room->building_name" separator progress-indicator>
        <x-slot:actions>
            @can('update', $room)
                <x-button :label="__('Modify')" icon="o-pencil" class="btn-ghost btn-sm"
                    link="{{ route('admin.rooms.edit', $room) }}" />
            @endcan
            @can('create', \App\Domains\ClubAdmin\Club\Models\Table::class)
                <x-button :label="__('Create table')" icon="o-plus" class="btn-primary btn-sm"
                    link="{{ route('admin.tables.create') }}" />
            @endcan
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 mb-6 lg:grid-cols-3">
        <x-admin.shared.stat-card
            :label="__('Tables')"
            :value="$tables->count()"
            :hint="$tables->filter(fn ($table) => $table->state->isPlayable())->count() . ' ' . __('playable')"
            icon="o-squares-2x2"
            color="primary" />

        <x-admin.shared.stat-card
            :label="__('Training capacity')"
            :value="$room->capacity_for_trainings"
            :hint="__('tables')"
            icon="o-academic-cap" />

        <x-admin.shared.stat-card
            :label="__('Matches capacity')"
            :value="$room->capacity_for_interclubs"
            :hint="__('tables')"
            icon="o-trophy"
            class="col-span-2 lg:col-span-1" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Parc de tables --}}
        <div class="lg:col-span-2">
            <x-card class="shadow-sm">
                <x-slot:title>{{ __('Tables') }}</x-slot:title>

                @if ($tables->isEmpty())
                    <x-admin.shared.empty
                        icon="o-squares-2x2"
                        :title="__('No table in this room yet')"
                        :action="__('Create table')"
                        :href="route('admin.tables.create')" />
                @else
                    {{-- Desktop --}}
                    <div class="hidden overflow-x-auto md:block">
                        <x-table :headers="$headers" :rows="$tables" class="table-sm">
                            @scope('cell_name', $table)
                                <span class="font-bold">{{ $table->name }}</span>
                            @endscope

                            @scope('cell_equipment', $table)
                                @if ($table->brand || $table->model)
                                    <span>{{ trim($table->brand . ' ' . $table->model) }}</span>
                                @else
                                    <span class="text-xs text-muted">{{ __('Not specified') }}</span>
                                @endif
                            @endscope

                            @scope('cell_state', $table)
                                <x-badge :value="$table->state->getLabel()"
                                    :tooltip="$table->state_description"
                                    class="badge-sm {{ $table->state->isPlayable() ? 'badge-ghost' : 'badge-error badge-soft' }}" />
                            @endscope

                            @scope('actions', $table)
                                <x-admin.shared.row-menu
                                    :label="__('Edit')"
                                    icon="o-pencil"
                                    link="{{ route('admin.tables.edit', $table) }}">
                                    @can('update', $table)
                                        <x-menu-item icon="o-lock-open" wire:click="confirmUnlink({{ $table->id }})" spinner :title="__('Unlink')" />
                                    @endcan
                                    @can('delete', $table)
                                        <x-menu-item class="text-error" icon="o-trash" wire:click="confirmDelete({{ $table->id }})" :title="__('Delete')" />
                                    @endcan
                                </x-admin.shared.row-menu>
                            @endscope
                        </x-table>
                    </div>

                    {{-- Mobile --}}
                    <div class="divide-y divide-base-200 md:hidden">
                        @foreach ($tables as $table)
                            <x-list-item :item="$table" no-separator wire:key="table-{{ $table->id }}">
                                <x-slot:value>
                                    <span class="font-medium">{{ $table->name }}</span>
                                </x-slot:value>
                                <x-slot:sub-value>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                        <x-badge :value="$table->state->getLabel()"
                                            class="badge-xs {{ $table->state->isPlayable() ? 'badge-ghost' : 'badge-error badge-soft' }}" />
                                        @if ($table->brand || $table->model)
                                            <span class="text-xs text-base-content/50">
                                                {{ trim($table->brand . ' ' . $table->model) }}
                                            </span>
                                        @endif
                                    </div>
                                </x-slot:sub-value>
                                <x-slot:actions>
                                    <x-admin.shared.row-menu
                                        :label="__('Edit')"
                                        icon="o-pencil"
                                        link="{{ route('admin.tables.edit', $table) }}">
                                        @can('update', $table)
                                        @endcan
                                        @can('delete', $table)
                                            <x-menu-item class="text-error" icon="o-trash" wire:click="confirmDelete({{ $table->id }})" :title="__('Delete')" />
                                        @endcan
                                    </x-admin.shared.row-menu>
                                </x-slot:actions>
                            </x-list-item>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>

        {{-- Accès & agenda --}}
        <div class="space-y-6">
            <x-card class="shadow-sm">
                <x-slot:title>{{ __('Access') }}</x-slot:title>

                <div class="space-y-3 text-sm">
                    <div class="flex items-start gap-2">
                        <x-icon name="o-map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-base-content/40" />
                        <span>{{ $room->street }}, {{ $room->city_code }} {{ $room->city_name }}</span>
                    </div>

                    @if ($room->floor)
                        <div class="flex items-start gap-2">
                            <x-icon name="o-building-office-2" class="mt-0.5 h-4 w-4 shrink-0 text-base-content/40" />
                            <span>{{ __('Floor') }} {{ $room->floor }}</span>
                        </div>
                    @endif

                    @if ($room->access_description)
                        <div class="flex items-start gap-2">
                            <x-icon name="o-information-circle" class="mt-0.5 h-4 w-4 shrink-0 text-base-content/40" />
                            <span class="text-base-content/70">{{ $room->access_description }}</span>
                        </div>
                    @endif
                </div>
            </x-card>

            <x-card class="shadow-sm">
                <x-slot:title>{{ __('Upcoming') }}</x-slot:title>

                @php
                    $hasUpcoming = $room->trainings->isNotEmpty()
                        || $room->interclubs->isNotEmpty()
                        || $room->tournaments->isNotEmpty();
                @endphp

                @if (! $hasUpcoming)
                    <p class="text-sm italic text-muted">{{ __('Nothing planned in the next two weeks.') }}</p>
                @else
                    @foreach ($room->trainings as $training)
                        <x-admin.shared.compact-event-preview link="#"
                            :organizer="$training->trainer?->first_name . ' ' . $training->trainer?->last_name"
                            :name="$training->type" :startDateTime="$training->start" type="training" />
                    @endforeach

                    @foreach ($room->interclubs as $interclub)
                        <x-admin.shared.compact-event-preview link="#" :name="$interclub->week_number"
                            :startDateTime="$interclub->start_date_time" type="interclub" />
                    @endforeach

                    @foreach ($room->tournaments as $tournament)
                        <x-admin.shared.compact-event-preview link="#" :name="$tournament->name"
                            :startDateTime="$tournament->start_date" type="tournament" />
                    @endforeach
                @endif
            </x-card>
        </div>
    </div>

    <x-confirm-modal model="unlinkModal" :title="__('Confirm unlink')" :subtitle="__('Warning!')"
        :confirmLabel="__('Unlink')" confirmAction="unlink" :open="$unlinkModal">
        <p>{{ __('Are you sure you want to unlink the table from its room?') }}</p>
    </x-confirm-modal>

    <x-confirm-modal model="deleteModal" :title="__('Confirm deletion')" :subtitle="__('Warning!')"
        :confirmLabel="__('Delete')" confirmAction="delete" :open="$deleteModal">
        <p>{{ __('Are you sure you want to delete this table? This action is irreversible.') }}</p>
    </x-confirm-modal>
</div>
