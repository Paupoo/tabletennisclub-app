<x-tab name="2" :label="__('Invitations')" icon="o-envelope">
    <div class="mt-8 animate-in fade-in duration-500">
        @include('components.admin.club-events.tournaments.partials.shared.invitations-content', ['isLocked' => false])

        {{-- Événement web --}}
        <x-card class="mt-8 shadow-sm" separator :title="__('Website event')">
            <x-slot:menu>
                @if ($eventPostId)
                    @if ($eventStatus === 'PUBLISHED')
                        <x-badge class="badge-success badge-sm" icon="o-globe-alt" value="{{ __('Published') }}" />
                    @else
                        <x-badge class="badge-warning badge-sm" icon="o-document-text" value="{{ __('Draft') }}" />
                    @endif
                @endif
                <x-button
                    class="btn-ghost btn-sm"
                    icon="o-arrow-top-right-on-square"
                    :label="__('Edit in step 2')"
                    wire:click="$set('step', '2')"
                />
            </x-slot:menu>

            @if ($eventPostId)
                <div class="space-y-1 text-sm text-base-content/70">
                    <p><span class="font-medium text-base-content">{{ $eventTitle }}</span></p>
                    @if ($eventDescription)
                        <p class="line-clamp-2">{{ $eventDescription }}</p>
                    @endif
                </div>
            @else
                <x-alert class="text-sm" icon="o-information-circle">
                    {{ __('No website event created yet. Go to step 2 to create one.') }}
                </x-alert>
            @endif
        </x-card>
    </div>
</x-tab>
