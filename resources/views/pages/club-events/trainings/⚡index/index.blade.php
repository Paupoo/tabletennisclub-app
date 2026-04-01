<div>
    @if (!$selectedId)

        <x-header separator subtitle="{{ __('Manage the trainings') }}" title="{{ __('Trainings') }}">
            <x-slot:actions>
                <x-input clearable icon="o-magnifying-glass" placeholder="{{ __('Search') }}" wire:model="search" />
                <x-button class="btn-ghost icon="o-filter" label="{{ __('Filter') }}" />
                <x-button @click="$wire.showModal = true" class="btn-primary" label="{{ __('New training') }}" />
            </x-slot:actions>
        </x-header>

        @php
            $grouped = collect($trainings)->groupBy('category');
        @endphp

        <div class="space-y-8">

            @foreach ($grouped as $category => $items)
                <section>

                    {{-- Header catégorie --}}
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                            {{ $category }}
                        </h2>

                        <span class="text-xs text-gray-400">
                            {{ count($items) }} {{ __('sessions') }}
                        </span>
                    </div>

                    {{-- Grid responsive --}}
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($items as $training)
                            <x-admin.club-events.trainings.training-card :training="$training" />
                        @endforeach
                    </div>

                </section>
            @endforeach

        </div>
    @else
        {{-- =====================================================
         DETAIL / ATTENDANCE VIEW
    ====================================================== --}}
        <x-header separator
            subtitle="{{ \Carbon\Carbon::parse($this->selectedTraining['start_date'])->format('d/m/Y H:i') }}"
            title="{{ __('Session: :title', ['title' => $this->selectedTraining['title']]) }}">
            <x-slot:actions>
                <x-button class="btn-ghost" icon="o-arrow-left" label="{{ __('Back') }}" wire:click="backToList" />
                <x-button class="btn-primary btn-sm" icon="o-arrow-down-tray" label="{{ __('Export') }}" />
            </x-slot:actions>
        </x-header>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            {{-- Stats --}}
            <div class="space-y-4 lg:col-span-1">
                <x-stat color="text-primary" icon="o-users" title="{{ __('Registered') }}"
                    value="{{ $this->selectedTraining['current_spots'] }}" />
                <x-stat icon="o-user" title="{{ __('Coach') }}"
                    value="{{ $this->selectedTraining['coach_name'] }}" />
                <x-stat icon="o-ticket" title="{{ __('Max spots') }}"
                    value="{{ $this->selectedTraining['max_spots'] }}" />
            </div>

            {{-- Attendance list --}}
            <div class="lg:col-span-2">
                <x-card title="{{ __('Attendance list') }}">
                    @forelse(['Jean Dupont', 'Marie Curie', 'Lucas Silva'] as $member)
                        <div class="flex items-center justify-between border-b py-3 last:border-0">
                            <div class="flex items-center gap-3">
                                <x-avatar class="h-10 w-10" placeholder="{{ substr($member, 0, 1) }}" />
                                <div>
                                    <p class="font-medium">{{ $member }}</p>
                                    <p class="text-xs italic text-gray-500">{{ __('Active member') }}</p>
                                </div>
                            </div>
                            <x-checkbox label="{{ __('Present') }}" tight />
                        </div>
                    @empty
                        <div class="py-10 text-center">
                            <x-icon class="mx-auto h-10 w-10 text-gray-400" name="o-face-frown" />
                            <p class="mt-2 text-gray-500">{{ __('No registrations yet.') }}</p>
                        </div>
                    @endforelse
                </x-card>
            </div>
        </div>
    @endif

    {{-- =====================================================
         EDIT / CREATE MODAL
    ====================================================== --}}
    <x-modal separator title="{{ __('Training details') }}" wire:model="showModal">
        <div class="grid gap-4">
            <x-input label="{{ __('Title') }}" placeholder="{{ __('E.g. Easter camp') }}" wire:model="form.title" />

            <div class="grid grid-cols-2 gap-4">
                <x-select :options="$categories" label="{{ __('Category') }}" wire:model="form.category" />
                <x-input label="{{ __('Price (€)') }}" type="number" wire:model="form.price" />
            </div>

            <x-datepicker icon="o-calendar" label="{{ __('Start date') }}" wire:model="form.start_date" />

            <x-select :options="$recurrenceOptions ?? []" label="{{ __('Recurrence') }}" wire:model="formRecurrence" />

            <x-input label="{{ __('Maximum spots') }}" type="number" wire:model="form.max_spots" />
        </div>

        <x-slot:actions>
            <x-button @click="$wire.showModal = false" label="{{ __('Cancel') }}" />
            <x-button class="btn-primary" label="{{ __('Save') }}" wire:click="save" />
        </x-slot:actions>
    </x-modal>
</div>