<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Federation changes')">
        <x-slot:subtitle>{{ __('Held back because the federation changed too many fixtures at once. The calendar is already up to date; only the messages to the teams wait.') }}</x-slot:subtitle>
    </x-header>

    @if ($groups->isEmpty())
        <x-card class="mt-4">
            <div class="py-16 text-center text-base-content/60">
                <p class="text-sm">{{ __('No change is waiting for review.') }}</p>
            </div>
        </x-card>
    @else
        <x-card class="mt-4">
            <div class="divide-y divide-base-300">
                @foreach ($groups as $key => $group)
                    <label wire:key="change-{{ $key }}" class="flex cursor-pointer items-start gap-3 py-3">
                        <input type="checkbox" class="checkbox checkbox-sm mt-1" value="{{ $key }}" wire:model.live="selected" />
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold">{{ $group['subject'] }}</div>
                            @foreach ($group['rows'] as $row)
                                <div class="mt-1 text-sm text-base-content/70">
                                    @if ($group['isReschedule'])
                                        {{ __('Before') }} : {{ $row['before'] }}<br>
                                        {{ __('Now') }} : {{ $row['after'] }}
                                    @else
                                        {{ $row['after'] }}
                                    @endif
                                </div>
                            @endforeach
                            @if ($group['fixtureStarted'])
                                <x-badge class="badge-ghost badge-xs mt-1 font-bold" :value="__('Already started: will not be sent')" />
                            @endif
                        </div>
                    </label>
                @endforeach
            </div>

            <x-slot:actions>
                <x-button
                    :label="__('Do not tell')"
                    class="btn-ghost btn-sm"
                    wire:click="dismissSelected"
                    spinner="dismissSelected"
                    :disabled="$selected === []" />
                <x-button
                    :label="__('Tell the teams')"
                    icon="o-paper-airplane"
                    class="btn-primary btn-sm"
                    wire:click="notifySelected"
                    spinner="notifySelected"
                    :disabled="$selected === []" />
            </x-slot:actions>
        </x-card>
    @endif
</div>
