{{--
    Generic event-post form fields.

    Requires the parent Livewire component to use the HasEventPostForm trait.

    Props:
      - eventPostId  (?int)    — current post ID, used to display publish status badge
      - eventStatus  (string)  — 'DRAFT' | 'PUBLISHED'
      - syncNote     (?string) — optional info text about auto-synced fields (date, price…)
--}}
@props([
    'eventPostId' => null,
    'eventStatus' => 'DRAFT',
    'syncNote'    => null,
])

<div class="space-y-6">

    <x-card class="shadow-sm" separator :title="__('Website event')">
        <x-slot:menu>
            @if ($eventPostId)
                @if ($eventStatus === 'PUBLISHED')
                    <x-badge class="badge-success badge-sm" icon="o-globe-alt" value="{{ __('Published') }}" />
                @else
                    <x-badge class="badge-warning badge-sm" icon="o-document-text" value="{{ __('Draft') }}" />
                @endif
            @endif
        </x-slot:menu>

        <div class="space-y-4">

            <x-input
                :hint="__('Pre-filled automatically — you can customise it.')"
                :label="__('Event title')"
                :placeholder="__('e.g. Summer Camp 2026')"
                wire:model="eventTitle"
            />

            <x-textarea
                :hint="__('Displayed on the public events page. Plain text, no Markdown.')"
                :label="__('Description')"
                :placeholder="__('A short description visible to members on the website…')"
                rows="4"
                wire:model="eventDescription"
            />

            <x-input
                :hint="__('Where the event takes place.')"
                :label="__('Location')"
                :placeholder="__('e.g. Salle Demeester, Ottignies')"
                wire:model="eventLocation"
            />

            <x-checkbox
                :label="__('Feature this event on the website homepage')"
                wire:model="eventFeatured"
            />

        </div>
    </x-card>

    @if ($syncNote)
        <x-alert class="border border-info/20 bg-info/5 text-sm" icon="o-information-circle">
            {{ $syncNote }}
        </x-alert>
    @endif

</div>
