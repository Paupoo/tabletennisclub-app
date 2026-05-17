<div class="mt-8 space-y-6 animate-in fade-in duration-500">

    <x-card class="shadow-sm" separator title="{{ __('Website event') }}">
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
                :hint="__('Pre-filled from the tournament name — you can customise it.')"
                :label="__('Event title')"
                :placeholder="__('e.g. Spring Open 2026')"
                wire:model="eventTitle"
            />
            @error('eventTitle')
                <p class="text-xs text-error">{{ $message }}</p>
            @enderror

            <x-textarea
                :hint="__('Displayed on the public events page. Plain text, no Markdown.')"
                :label="__('Description')"
                :placeholder="__('A short description visible to members on the website…')"
                rows="4"
                wire:model="eventDescription"
            />

            <x-input
                :hint="__('e.g. Club House, Rue des Sports 1, Ottignies')"
                :label="__('Location')"
                :placeholder="__('Where will the tournament take place?')"
                wire:model="eventLocation"
            />

            <x-checkbox
                :label="__('Feature this event on the website homepage')"
                wire:model="eventFeatured"
            />

        </div>
    </x-card>

    {{-- Summary of data synced automatically from the tournament --}}
    <x-alert class="border border-info/20 bg-info/5 text-sm" icon="o-information-circle">
        {{ __('Date, time, price and capacity are synced automatically from the tournament settings.') }}
    </x-alert>

    <div class="flex items-center justify-between">

        <x-button
            class="btn-ghost btn-sm"
            icon="o-forward"
            label="{{ __('Skip') }}"
            wire:click="$set('step', '3')"
        />

        <div class="flex items-center gap-2">
            <x-button
                class="btn-ghost btn-sm"
                icon="o-document-text"
                label="{{ __('Save as draft') }}"
                spinner="saveEventPost"
                wire:click="saveEventPost('draft')"
            />
            <x-button
                class="btn-primary btn-sm"
                icon="o-globe-alt"
                label="{{ __('Publish on website') }}"
                spinner="saveEventPost"
                wire:click="saveEventPost('published')"
            />
        </div>

    </div>

</div>
