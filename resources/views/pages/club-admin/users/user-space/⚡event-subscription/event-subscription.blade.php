<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header separator subtitle="{{ __('Tournaments, dinners, and club meetings') }}"
        title="{{ __('Events and Activities') }}" />

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">

        <div class="space-y-4">
            <x-card class="bg-primary/5 border-primary/10 border" shadow title="{{ __('Filters') }}">
                <x-choices :options="[['id' => 1, 'name' => 'Tournaments'], ['id' => 2, 'name' => 'Social']]" label="{{ __('Category') }}" />
                <x-checkbox checked class="mt-4" label="{{ __('Only upcoming') }}" tight />
                <x-checkbox label="{{ __('My registrations') }}" tight />
            </x-card>

            <x-card class="bg-primary/5 border-primary/10 border">
                <div class="mb-2 text-xs font-bold uppercase opacity-50">{{ __('Next Big Event') }}</div>
                <div class="text-primary font-black">Annual Club Dinner</div>
                <div class="text-xs opacity-70">March 15, 2026</div>
                <x-button class="btn-primary btn-xs mt-3" label="{{ __('Quick Join') }}" />
            </x-card>
        </div>

        <div class="space-y-6 lg:col-span-3">

            {{-- Section: À venir --}}
            <x-card icon="o-calendar-days" separator shadow title="{{ __('Upcoming Events') }}">
                @php
                    $events = [
                        [
                            'id' => 101,
                            'title' => 'Club Tournament - Singles',
                            'startDateTime' => 'Feb 28',
                            'type' => 'Competition',
                            'dot' => 'bg-info',
                            'price' => '10€',
                            'status' => 'Open',
                            'registered' => false,
                            'location' => 'Blocry G3',
                        ],
                        [
                            'id' => 102,
                            'title' => 'Italian Night (Pasta Dinner)',
                            'startDateTime' => 'March 15',
                            'type' => 'Social',
                            'dot' => 'bg-success',
                            'price' => '25€',
                            'status' => 'Already registered',
                            'registered' => true,
                            'location' => 'Demeester 0',
                        ],
                        [
                            'id' => 103,
                            'title' => 'General Assembly',
                            'startDateTime' => Carbon\Carbon::now()
                                ->addDays(45)
                                ->setHour(19)
                                ->setMinute(0)
                                ->format('Y-m-d H:i'),
                            'type' => 'Meeting',
                            'dot' => 'bg-warning',
                            'price' => 'Free',
                            'status' => 'Mandatory',
                            'registered' => false,
                            'location' => 'Demeester 0',
                        ],
                    ];
                @endphp

                @foreach ($events as $event)
                    <x-admin.shared.compact-event-preview :location="$event['location']" :startDateTime="$event['startDateTime']" link="#"
                        name="{{ $event['title'] }}" type="{{ strtolower($event['type']) }}">
                        <x-slot:actions>
                            @if ($event['registered'])
                                <x-button class="btn-ghost btn-sm text-error" icon="o-x-circle"
                                    label="{{ __('Cancel') }}" />
                            @else
                                <x-button class="btn-outline btn-primary btn-sm px-6" label="{{ __('Register') }}" />
                            @endif
                            <x-button class="btn-ghost btn-circle btn-sm" icon="o-information-circle" />
                        </x-slot:actions>
                    </x-admin.shared.compact-event-preview>
                @endforeach
            </x-card>

            {{-- Section: Historique (plus discret) --}}
            <x-collapse>
                <x-slot:heading>
                    <div class="text-sm font-bold opacity-40">{{ __('Past Events') }}</div>
                </x-slot:heading>
                <x-slot:content>
                    <div class="space-y-2 opacity-60">
                        <div class="flex justify-between border-b border-dashed py-2 text-sm">
                            <span>New Year Drinks</span>
                            <span>Jan 10, 2026</span>
                        </div>
                        <div class="flex justify-between border-b border-dashed py-2 text-sm">
                            <span>Christmas Tournament</span>
                            <span>Dec 20, 2025</span>
                        </div>
                    </div>
                </x-slot:content>
            </x-collapse>
        </div>

    </div>
</div>