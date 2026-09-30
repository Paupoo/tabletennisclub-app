{{--
    One action of published minutes, as a card.

    The status is written out, not only coloured: overdue, to do with the days
    left, or done. A done action fades, so what is left to do stands out.

    @param \App\Domains\Meetings\Services\MinutesAction $action
    @param bool $canToggle the reader is the assignee, or runs the meetings
    @param string|null $point the agenda point it was handed out on
--}}
@props(['action', 'canToggle' => false, 'point' => null])

@php
    $item = $action->item;
    $badgeClass = match ($action->status) {
        'overdue' => 'badge-error',
        'done' => 'badge-success',
        default => 'badge-warning',
    };
@endphp

<article data-minutes-action="{{ $action->status }}" wire:key="minutes-action-{{ $item->id }}"
    {{ $attributes->class([
        'flex flex-col gap-3 rounded-xl border bg-base-100 p-4',
        'border-error/40' => $action->status === 'overdue',
        'border-base-300' => $action->status !== 'overdue',
    ]) }}>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <span class="badge badge-soft {{ $badgeClass }} badge-sm font-semibold">{{ $action->label() }}</span>
        @if ($item->due_date)
            <span class="text-xs text-muted">
                <x-icon name="o-calendar" class="mb-0.5 inline h-3.5 w-3.5" />
                {{ __('Due :date', ['date' => $item->due_date->translatedFormat('j M Y')]) }}
            </span>
        @endif
    </div>

    <div @class(['min-w-0', 'opacity-70' => $action->status === 'done'])>
        <h3 @class(['font-bold text-base-content', 'line-through' => $action->status === 'done'])>{{ $item->title }}</h3>
        @if ($point)
            <p class="text-xs text-muted">{{ $point }}</p>
        @endif
        @if (filled($item->description))
            <div class="prose prose-sm mt-1 max-w-none text-muted [&>:first-child]:mt-0 [&>:last-child]:mb-0">
                {!! \App\Support\Markdown::safe($item->description) !!}
            </div>
        @endif
    </div>

    <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-base-300 pt-3">
        <span class="flex min-w-0 items-center gap-2 text-sm">
            <x-icon name="o-user-circle" class="h-5 w-5 shrink-0 text-muted" />
            @if ($action->mine)
                <span class="font-semibold text-primary">{{ __('You') }}</span>
            @elseif ($item->assignedTo)
                <span class="truncate">{{ $item->assignedTo->full_name }}</span>
            @else
                <span class="italic text-muted">{{ __('Nobody') }}</span>
            @endif
        </span>

        @if ($canToggle)
            <x-button wire:click="toggleAction({{ $item->id }})" spinner="toggleAction({{ $item->id }})"
                :icon="$item->is_completed ? 'o-arrow-uturn-left' : 'o-check'"
                :label="$item->is_completed ? __('Reopen') : __('Mark as done')"
                :class="$item->is_completed ? 'btn-sm btn-ghost' : 'btn-sm btn-success btn-soft'" />
        @endif
    </div>
</article>
