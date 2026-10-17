<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header :title="__('My actions')" :subtitle="__('What the meetings handed you. The next committee asks where each one stands.')" separator progress-indicator />

    @php
        $origin = fn ($action) => __(':type of :date', ['type' => $action->meeting->type->getLabel(), 'date' => $action->meeting->scheduled_at?->format('d/m/Y')]);
    @endphp

    <div class="mx-auto max-w-3xl space-y-6">
        <x-card :title="__('To do')" class="shadow-sm">
            @forelse ($this->openActions as $action)
                @php $overdue = $action->due_date?->isBefore(today()); @endphp
                <article wire:key="my-action-{{ $action->id }}" class="border-base-300 py-4 first:pt-0 last:pb-0 [&:not(:last-child)]:border-b">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="font-bold">{{ $action->title }}</h3>
                            <p class="text-xs text-muted">
                                @if ($action->meeting->minutes?->is_published)
                                    <a href="{{ route('meetings.minutes.read', $action->meeting) }}" wire:navigate class="link link-hover">{{ $origin($action) }}</a>
                                @else
                                    {{ $origin($action) }}
                                @endif
                            </p>
                        </div>
                        <span @class(['badge badge-soft badge-sm', 'badge-error' => $overdue, 'badge-warning' => ! $overdue])>
                            {{ $action->due_date ? __('Due :date', ['date' => $action->due_date->translatedFormat('j M Y')]) : __('No due date') }}
                        </span>
                    </div>

                    <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
                        <div class="min-w-0 flex-1">
                            <x-input wire:model="notes.{{ $action->id }}" maxlength="500" class="input-sm"
                                :label="__('Where it stands')" :placeholder="__('Quote received, waiting for the town hall…')" />
                            @if ($action->progress_noted_at)
                                <p class="mt-1 text-xs text-muted">{{ __('Noted on :date', ['date' => $action->progress_noted_at->translatedFormat('j M Y')]) }}</p>
                            @endif
                        </div>
                        <div class="flex gap-2 sm:mt-7">
                            <x-button :label="__('Save the note')" class="btn-ghost btn-sm" wire:click="saveNote({{ $action->id }})" spinner="saveNote({{ $action->id }})" />
                            <x-button icon="o-check" :label="__('Mark done')" class="btn-success btn-soft btn-sm" wire:click="markDone({{ $action->id }})" spinner="markDone({{ $action->id }})" />
                        </div>
                    </div>
                </article>
            @empty
                <p class="text-sm italic text-muted">{{ __('No action waiting for you.') }}</p>
            @endforelse
        </x-card>

        @if ($this->closedActions->isNotEmpty())
            <x-card :title="__('Closed recently')" :subtitle="__('The last three months.')" class="shadow-sm">
                <ul class="divide-y divide-base-300">
                    @foreach ($this->closedActions as $action)
                        <li wire:key="my-closed-{{ $action->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                            <div class="min-w-0">
                                <p class="text-sm font-medium line-through opacity-70">{{ $action->title }}</p>
                                <p class="text-xs text-muted">
                                    {{ $origin($action) }}
                                    · {{ $action->abandoned_at ? __('Abandoned — :reason', ['reason' => $action->abandon_reason]) : __('Done') }}
                                </p>
                            </div>
                            @if ($action->is_completed)
                                <x-button :label="__('Back to do')" class="btn-ghost btn-xs" wire:click="reopen({{ $action->id }})" />
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>
</div>
