<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator
        :subtitle="__('What each general assembly decided, as sent to the members')"
        :title="__('General assembly minutes')" />

    <x-card>
        @if ($assemblies->isEmpty())
            <x-empty-state icon="o-document-text" :heading="__('No minutes published yet.')"
                :message="__('The minutes of a general assembly appear here once the committee has sent them to all members.')" />
        @else
            <ul class="divide-y divide-base-300">
                @foreach ($assemblies as $assembly)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2.5" wire:key="assembly-minutes-{{ $assembly->id }}">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">{{ $assembly->title }}</p>
                            <p class="text-xs text-muted">{{ $assembly->scheduled_at?->translatedFormat('j F Y') }}</p>
                        </div>
                        <div class="flex gap-1">
                            <x-button icon="o-eye" :label="__('Read')" class="btn-ghost btn-xs"
                                :link="route('meetings.minutes.read', $assembly)" />
                            <x-button icon="o-arrow-down-tray" label="PDF" class="btn-ghost btn-xs"
                                :link="route('meetings.minutes.pdf', $assembly)" no-wire-navigate external />
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>
