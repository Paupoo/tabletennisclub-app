<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header separator subtitle="{{ __('Upcoming club activities') }}" title="{{ __('Calendar') }}">
        <x-slot:actions>
            <x-button class="btn-outline btn-sm" icon="o-arrow-path" label="{{ __('Sync to Google/iCal') }}" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">

        <div class="space-y-4">
            <x-card class="bg-primary/5 border-primary/20 border" shadow title="{{ __('Fitlers') }}">
                <div class="space-y-4">
                    <div>
                        <label class="label">
                            <span class="label-text font-semibold">{{ __('Month') }}</span>
                        </label>
                        <x-choices :options="collect($months)" placeholder="{{ __('Select a month') }}" single
                            wire:model="selectedMonth" />
                    </div>
                    <div>
                        <label class="label">
                            <span class="label-text font-semibold">{{ __('Category') }}</span>
                        </label>
                        <x-choices :options="collect($categories)" placeholder="{{ __('Select a category') }}" single
                            wire:model="selectedCategory" />
                    </div>
                </div>
            </x-card>
        </div>

        <div class="space-y-8 lg:col-span-3">
            @foreach ($calendar as $month => $events)
                <x-card :title="$month" class="mb-8" shadow>
                    @foreach ($events as $event)
                        <x-admin.shared.compact-event-preview :name="$event['title']" :startDateTime="$event['startDateTime']" :type="$event['type']"
                            link="#" location="Club House">
                            {{-- Injection des actions à droite --}}
                            <x-slot:actions>
                                @if ($event['type'] == 'interclub')
                                    <x-badge class="badge-primary badge-outline badge-xs font-bold" value="Selected" />
                                @endif

                                {{-- On utilise une simple icône au lieu d'un bouton pour respecter le HTML dans la balise <a> --}}
                                <x-icon class="h-5 w-5 opacity-20 transition-opacity group-hover:opacity-100"
                                    name="o-chevron-right" />
                            </x-slot:actions>
                        </x-admin.shared.compact-event-preview>
                    @endforeach
                </x-card>
            @endforeach
        </div>
    </div>
</div>