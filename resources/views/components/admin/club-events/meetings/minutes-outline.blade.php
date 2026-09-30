{{--
    The minutes' outline: where the meeting stands, point by point, and the
    buttons that finish the minutes. Sticky beside the page on a desktop,
    folded under a bar on a phone.

    Rendered inside the minutes component: its buttons call `go()` from the
    page's Alpine scope and the component's actions through wire:click.

    @param \App\Domains\Meetings\Models\Meeting $meeting with agendaItems, decisions, actionItems, minutes
    @param int|null $current the point being discussed
    @param array{present: int, excused: int, absent: int, pending: int} $counts
    @param string $outside the key of the "outside the agenda" block
    @param bool $readOnly another member holds the pen
    @param string|null $savedAt time of the last write
--}}
@props(['meeting', 'current', 'counts', 'outside', 'readOnly' => false, 'savedAt' => null])

@php
    $minutes = $meeting->minutes;
    $isAssembly = $meeting->type === \App\Domains\Shared\Enums\MeetingTypeEnum::GENERAL_ASSEMBLY;
    $scrollTo = fn (string $id): string => "navOpen = false; document.getElementById('{$id}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })";
@endphp

<nav aria-label="{{ __('Outline') }}" class="space-y-4">
    <div class="rounded-xl border border-base-300 bg-base-100 p-3">
        <ul class="space-y-0.5 text-sm">
            @if ($meeting->users->isNotEmpty() || $isAssembly)
                <li>
                    <button type="button" @click="{{ $scrollTo('attendance') }}"
                        class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left hover:bg-base-200">
                        <x-icon name="o-user-group" class="h-4 w-4 shrink-0 text-muted" />
                        <span class="flex-1">{{ __('Attendance list') }}</span>
                        <span class="text-xs tabular-nums text-muted">{{ $counts['present'] }}@if ($meeting->quorum !== null)/{{ $meeting->quorum }}@endif</span>
                    </button>
                </li>
            @endif
            <li>
                <button type="button" @click="{{ $scrollTo('announcements') }}"
                    class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left hover:bg-base-200">
                    <x-icon name="o-megaphone" class="h-4 w-4 shrink-0 text-muted" />
                    <span class="flex-1">{{ __('Announcements') }}</span>
                    <span class="text-xs tabular-nums text-muted">{{ count(array_filter($minutes?->announcements ?? [], filled(...))) }}</span>
                </button>
            </li>
        </ul>

        @if ($meeting->agendaItems->isNotEmpty())
            <p class="mb-1 mt-3 px-2 text-xs font-bold uppercase tracking-widest text-muted">{{ __('Agenda') }}</p>
        @endif
        <ol class="space-y-0.5 text-sm">
            @foreach ($meeting->agendaItems as $i => $item)
                @php
                    $d = $meeting->decisions->where('agenda_item_id', $item->id)->count();
                    $a = $meeting->actionItems->where('agenda_item_id', $item->id)->count();
                @endphp
                <li wire:key="outline-{{ $item->id }}">
                    <button type="button" @click="go({{ $item->id }})"
                        @class([
                            'flex w-full items-start gap-2 rounded-lg px-2 py-1.5 text-left hover:bg-base-200',
                            'bg-primary/10 font-semibold' => $item->id === $current,
                        ])>
                        <x-icon :name="$item->discussed_at ? 's-check-circle' : ($item->id === $current ? 's-play-circle' : 'o-stop-circle')"
                            @class(['mt-0.5 h-4 w-4 shrink-0', 'text-success' => $item->discussed_at, 'text-primary' => $item->id === $current, 'text-muted' => ! $item->discussed_at && $item->id !== $current]) />
                        <span class="min-w-0 flex-1">{{ $i + 1 }}. {{ $item->title }}</span>
                        @if ($d + $a > 0)
                            <span class="shrink-0 text-xs tabular-nums text-muted" title="{{ __(':d decisions · :a actions', ['d' => $d, 'a' => $a]) }}">
                                @if ($d > 0){{ $d }}D @endif @if ($a > 0){{ $a }}A @endif
                            </span>
                        @endif
                    </button>
                </li>
            @endforeach
            <li>
                <button type="button" @click="{{ $scrollTo('point-' . $outside) }}"
                    class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left hover:bg-base-200">
                    <x-icon name="o-ellipsis-horizontal-circle" class="h-4 w-4 shrink-0 text-muted" />
                    <span class="flex-1">{{ __('Outside the agenda') }}</span>
                </button>
            </li>
        </ol>
    </div>

    {{-- ── Saving and finishing ───────────────────────────────────── --}}
    <div class="space-y-2 rounded-xl border border-base-300 bg-base-100 p-3">
        <p class="flex items-center gap-1.5 text-xs text-muted" aria-live="polite">
            <span wire:loading.remove class="flex items-center gap-1.5">
                @if ($savedAt)
                    <x-icon name="o-check" class="h-3.5 w-3.5 text-success" /> {{ __('Saved at :time', ['time' => $savedAt]) }}
                @else
                    <x-icon name="o-cloud" class="h-3.5 w-3.5" /> {{ __('Every change is saved as you go') }}
                @endif
            </span>
            <span wire:loading class="flex items-center gap-1.5">
                <span class="loading loading-spinner loading-xs"></span> {{ __('Saving…') }}
            </span>
        </p>

        <x-button icon="o-eye" :label="__('Preview')" class="btn-ghost btn-sm w-full justify-start"
            :link="route('meetings.minutes.read', $meeting)" external />

        @if (! $meeting->scheduled_at?->isPast())
            <p class="text-xs text-muted">{{ __('Publish once the meeting is over.') }}</p>
        @elseif (! $minutes?->is_published)
            <x-button icon="o-check-badge" :label="__('Publish minutes')" class="btn-primary btn-sm w-full"
                wire:click="publishMinutes" spinner="publishMinutes" :disabled="$readOnly" />
        @else
            <x-button icon="o-paper-airplane"
                :label="$minutes->sent_to_committee_at ? __('Sent to the committee') : __('Send to committee')"
                class="btn-outline btn-sm w-full"
                wire:click="sendMinutes(false)" spinner="sendMinutes(false)"
                :disabled="(bool) $minutes->sent_to_committee_at" />
            @if ($isAssembly)
                <x-button icon="o-paper-airplane"
                    :label="$minutes->sent_to_all_at ? __('Sent to all members') : __('Send to all members')"
                    class="btn-outline btn-sm w-full"
                    wire:click="sendMinutes(true)" spinner="sendMinutes(true)"
                    :disabled="(bool) $minutes->sent_to_all_at"
                    wire:confirm="{{ __('Send the minutes to every active member? It cannot be recalled.') }}" />
            @endif
        @endif
    </div>
</nav>
