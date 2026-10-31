<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header :title="__('Automatic emails')" :subtitle="__('What the application sends by itself: when, to whom, and on what condition.')" separator progress-indicator />

    {{-- ── Scheduled ────────────────────────────────────────────────────────
    The tasks that run several times a day first: their next run says nothing,
    a deadline sends them. Then every other scheduled email once, at its next
    run, over the coming week; the rest under « Later ». --}}
    <section class="mt-6 space-y-4" wire:key="scheduled">
        <h2 class="text-lg font-semibold">{{ __('Next scheduled emails') }}</h2>

        <x-card class="bg-base-100 shadow-sm" :title="__('Continuously')" :subtitle="__('Checked several times a day, sent when a deadline falls.')">
            <x-admin.shared.automatic-email-columns />
            @foreach ($continuous as $row)
                <x-admin.shared.automatic-email-row :email="$row['email']" :cadence="$row['cadence']" wire:key="continuous-{{ $loop->index }}" />
            @endforeach
        </x-card>

        <x-card class="bg-base-100 shadow-sm" :title="__('In the coming week')">
            @if ($days !== [])
                <x-admin.shared.automatic-email-columns />
            @endif
            @forelse ($days as $day)
                <div class="border-t border-base-300 pt-3" wire:key="day-{{ $day['date']->toDateString() }}">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-muted">
                        @if ($day['date']->isToday())
                            {{ __('Today') }}
                        @elseif ($day['date']->isTomorrow())
                            {{ __('Tomorrow') }}
                        @else
                            {{ $day['date']->translatedFormat('l') }}
                        @endif
                        · {{ $day['date']->translatedFormat('j F') }}
                    </h3>
                    @foreach ($day['runs'] as $run)
                        <x-admin.shared.automatic-email-row :email="$run['email']" :cadence="$run['cadence']" :at="$run['at']->format('H\hi')" />
                    @endforeach
                </div>
            @empty
                <x-empty-state icon="o-calendar" :heading="__('Nothing planned this week')" :message="__('No scheduled email leaves in the next seven days.')" />
            @endforelse
        </x-card>

        @if ($later !== [])
            <x-card class="bg-base-100 shadow-sm" :title="__('Later')">
                <x-admin.shared.automatic-email-columns />
                @foreach ($later as $run)
                    <x-admin.shared.automatic-email-row
                        :email="$run['email']"
                        :cadence="$run['cadence']"
                        :next="$run['at']?->translatedFormat('l j F Y · H\hi')"
                        wire:key="later-{{ $loop->index }}" />
                @endforeach
            </x-card>
        @endif
    </section>

    {{-- ── Set off by an event or an action ─────────────────────────────── --}}
    <section class="mt-8 space-y-4" wire:key="triggered">
        <h2 class="text-lg font-semibold">{{ __('Sent when something happens') }}</h2>

        @foreach ($domains as $domain)
            <x-card class="bg-base-100 shadow-sm" wire:key="domain-{{ $loop->index }}">
                <x-slot:title>
                    <span class="flex flex-wrap items-center gap-2">
                        {{ $domain['name'] }}
                        @if ($domain['off'])
                            <x-badge :value="__('Domain switched off')" class="badge-error badge-sm" />
                        @endif
                    </span>
                </x-slot:title>

                <x-admin.shared.automatic-email-columns />
                @foreach ($domain['emails'] as $email)
                    <x-admin.shared.automatic-email-row :email="$email" />
                @endforeach
            </x-card>
        @endforeach
    </section>
</div>
