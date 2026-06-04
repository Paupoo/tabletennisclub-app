<div class="mt-8 space-y-6 animate-in fade-in duration-500">

    <x-admin.shared.event-post-form
        :event-post-id="$eventPostId"
        :event-status="$eventStatus"
        :sync-note="__('Date, time, price and capacity are synced automatically from the tournament settings.')"
    />

    <div class="flex items-center justify-between">

        <x-button
            class="btn-ghost btn-sm"
            icon="o-forward"
            :label="__('Skip')"
            wire:click="$set('step', '3')"
        />

        <div class="flex items-center gap-2">
            <x-button
                class="btn-ghost btn-sm"
                icon="o-document-text"
                :label="__('Save as draft')"
                spinner="saveEventPost"
                wire:click="saveEventPost('draft')"
            />
            <x-button
                class="btn-primary btn-sm"
                icon="o-globe-alt"
                :label="__('Publish on website')"
                spinner="saveEventPost"
                wire:click="saveEventPost('published')"
            />
        </div>

    </div>

</div>
