<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Weekly selections')"
        :subtitle="__('From :from to :to', ['from' => now()->translatedFormat('l j F'), 'to' => $until->translatedFormat('l j F')])" />

    @if ($this->days->isEmpty())
        <x-empty-state icon="o-calendar-days" :heading="__('No interclub match in the next seven days')" />
    @else
        <div class="space-y-8">
            @foreach ($this->days as $day => $fixtures)
                <section wire:key="day-{{ $day }}" class="space-y-3">
                    <h2 class="text-primary text-xs font-bold uppercase tracking-widest">
                        {{ ucfirst(\Illuminate\Support\Carbon::parse($day)->translatedFormat('l j F')) }}
                    </h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($fixtures as $fixture)
                            <x-admin.club-events.interclubs.weekly-tile :fixture="$fixture" :viewer-id="$viewerId" wire:key="fixture-{{ $fixture->id }}" />
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
