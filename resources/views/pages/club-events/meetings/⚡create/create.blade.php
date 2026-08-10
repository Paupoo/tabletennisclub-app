<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header :title="__('New meeting')"
        :subtitle="__('Title, type, date — everything else is set up from the meeting page.')" />

    <div class="mx-auto max-w-xl">
        <x-card>
            <div class="space-y-5">
                <x-input :label="__('Title')" wire:model="title"
                    :placeholder="__('e.g. Committee meeting - October 2026')"
                    required />

                <x-select :label="__('Type')" wire:model="type"
                    :options="$this->typeOptions" />

                {{-- Date mode --}}
                <div>
                    <p class="mb-2 text-sm font-medium">{{ __('Date') }}</p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <label @class([
                            'flex flex-1 cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors',
                            'border-primary bg-primary/5' => $dateMode === 'fixed',
                            'border-base-300 hover:border-primary' => $dateMode !== 'fixed',
                        ])>
                            <input type="radio" value="fixed" wire:model.live="dateMode" class="radio radio-sm radio-primary" />
                            <span class="text-sm font-medium">{{ __('Fixed date') }}</span>
                        </label>
                        <label @class([
                            'flex flex-1 cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors',
                            'border-primary bg-primary/5' => $dateMode === 'poll',
                            'border-base-300 hover:border-primary' => $dateMode !== 'poll',
                        ])>
                            <input type="radio" value="poll" wire:model.live="dateMode" class="radio radio-sm radio-primary" />
                            <span class="text-sm font-medium">{{ __('Date poll') }}</span>
                        </label>
                    </div>
                </div>

                @if ($dateMode === 'fixed')
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-datetime type="datetime-local" :label="__('Date & time')" wire:model="scheduledAt" required />
                        <x-datetime type="datetime-local" :label="__('End time')" wire:model="endsAt"
                            :hint="__('Optional — defaults to 2 hours later')" />
                    </div>
                @else
                    <div class="space-y-3">
                        <p class="text-sm text-base-content/60">
                            {{ __('Propose a few dates — the committee will vote from the meeting page.') }}
                        </p>
                        @foreach ($dateProposals as $i => $proposal)
                            <div class="flex items-center gap-3" wire:key="proposal-{{ $i }}">
                                <x-datetime type="datetime-local" wire:model="dateProposals.{{ $i }}.proposed_at"
                                    class="flex-1" />
                                <x-button icon="o-trash" class="btn-ghost btn-sm btn-circle"
                                    wire:click="removeDateProposal({{ $i }})" />
                            </div>
                        @endforeach
                        @error('dateProposals')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                        <x-button icon="o-plus" :label="__('Add date option')"
                            class="btn-ghost btn-sm" wire:click="addDateProposal" />
                    </div>
                @endif
            </div>
        </x-card>

        <div class="mt-6 flex justify-end gap-2">
            <x-button :label="__('Cancel')" class="btn-ghost"
                link="{{ route('admin.meetings.index') }}" />
            <x-button :label="__('Create meeting')" icon="o-check"
                class="btn-primary" wire:click="save" spinner="save" />
        </div>
    </div>
</div>
