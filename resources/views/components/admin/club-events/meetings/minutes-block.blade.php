{{--
    The decisions and actions of one block of the minutes — an agenda point,
    or "outside the agenda" — with their quick-add fields.

    Typed fast during the meeting: a decision is a line and Enter, an action a
    title and Enter; who, when and the details come after, from discreet
    pickers. ✎ opens the full editor.

    @param string $block the quick-add key: the agenda item id, or "outside"
    @param \Illuminate\Support\Collection $decisions MeetingDecision of the block
    @param \Illuminate\Support\Collection $actions MeetingActionItem of the block
    @param array<int, string> $numbers decision id => "D3"
    @param list<array{id: int, name: string}> $assignees
    @param bool $readOnly another member holds the pen
--}}
@props(['block', 'decisions', 'actions', 'numbers', 'assignees', 'readOnly' => false])

<div class="space-y-4">
    {{-- ── Decisions ─────────────────────────────────────────────── --}}
    <div>
        <p class="mb-1.5 text-xs font-bold uppercase tracking-widest text-muted">{{ __('Decisions') }}</p>
        <ul class="space-y-1.5">
            @foreach ($decisions as $decision)
                <li x-data="{ editing: false }" wire:key="decision-{{ $decision->id }}"
                    class="group rounded-lg border border-base-300 px-3 py-2">
                    <div class="flex items-start gap-2">
                        <span class="mt-0.5 rounded bg-primary/10 px-1.5 text-xs font-black text-primary">{{ $numbers[$decision->id] ?? 'D' }}</span>
                        <div x-show="!editing" class="prose prose-sm min-w-0 flex-1 max-w-none text-base-content [&>:first-child]:mt-0 [&>:last-child]:mb-0">
                            {!! \App\Support\Markdown::safe($decision->body) !!}
                        </div>
                        <template x-if="editing">
                            <div class="min-w-0 flex-1">
                                <x-markdown-editor model="decisionBodies.{{ $decision->id }}" compact commit-on-blur
                                    :label="__('Decision :n', ['n' => $numbers[$decision->id] ?? ''])" :sticky-toolbar="false" />
                            </div>
                        </template>
                        @unless ($readOnly)
                            <div class="flex shrink-0 gap-0.5">
                                <button type="button" class="btn btn-ghost btn-xs btn-square" @click="editing = !editing"
                                    :aria-label="editing ? @js(__('Done')) : @js(__('Edit'))" :title="editing ? @js(__('Done')) : @js(__('Edit'))">
                                    <x-icon x-show="!editing" name="o-pencil" class="h-4 w-4" />
                                    <x-icon x-show="editing" x-cloak name="o-check" class="h-4 w-4" />
                                </button>
                                <button type="button" class="btn btn-ghost btn-xs btn-square text-error"
                                    wire:click="removeDecision({{ $decision->id }})"
                                    wire:confirm="{{ __('Delete this decision?') }}"
                                    aria-label="{{ __('Delete') }}" title="{{ __('Delete') }}">
                                    <x-icon name="o-trash" class="h-4 w-4" />
                                </button>
                            </div>
                        @endunless
                    </div>
                </li>
            @endforeach
        </ul>
        @unless ($readOnly)
            <label class="input input-sm mt-1.5 w-full">
                <x-icon name="o-plus" class="h-4 w-4 text-muted" />
                <input type="text" wire:model="newDecision.{{ $block }}"
                    wire:keydown.enter.prevent="addDecision('{{ $block }}')"
                    placeholder="{{ __('Type a decision, then Enter') }}"
                    aria-label="{{ __('New decision') }}" class="grow">
            </label>
        @endunless
    </div>

    {{-- ── Actions ───────────────────────────────────────────────── --}}
    <div>
        <p class="mb-1.5 text-xs font-bold uppercase tracking-widest text-muted">{{ __('Action items') }}</p>
        <ul class="space-y-1.5">
            @foreach ($actions as $action)
                <li x-data="{ details: false }" wire:key="action-{{ $action->id }}"
                    class="rounded-lg border border-base-300 px-3 py-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="toggleAction({{ $action->id }})" @disabled($readOnly)
                            class="btn btn-ghost btn-xs btn-square"
                            aria-label="{{ $action->is_completed ? __('Reopen') : __('Mark as done') }}"
                            title="{{ $action->is_completed ? __('Reopen') : __('Mark as done') }}">
                            <x-icon :name="$action->is_completed ? 's-check-circle' : 'o-stop'"
                                @class(['h-5 w-5', 'text-success' => $action->is_completed, 'text-muted' => ! $action->is_completed]) />
                        </button>
                        <input type="text" wire:model.blur="actions.{{ $action->id }}.title" @disabled($readOnly)
                            aria-label="{{ __('Action') }}"
                            @class([
                                'input input-ghost input-sm min-w-40 flex-1 font-semibold',
                                'line-through opacity-60' => $action->is_completed,
                            ])>
                        <select wire:model.live="actions.{{ $action->id }}.assigned_to_id" @disabled($readOnly)
                            class="select select-ghost select-sm w-auto max-w-44" aria-label="{{ __('Assigned to') }}">
                            <option value="">{{ __('Nobody') }}</option>
                            @foreach ($assignees as $assignee)
                                <option value="{{ $assignee['id'] }}">{{ $assignee['name'] }}</option>
                            @endforeach
                        </select>
                        <div class="flex items-center gap-1">
                            <input type="date" wire:model.live="actions.{{ $action->id }}.due_date" @disabled($readOnly)
                                class="input input-ghost input-sm w-36" aria-label="{{ __('Due date') }}">
                            @unless ($readOnly)
                                <div class="dropdown dropdown-end">
                                    <button type="button" tabindex="0" class="btn btn-ghost btn-xs"
                                        aria-label="{{ __('Quick due dates') }}" title="{{ __('Quick due dates') }}">
                                        <x-icon name="o-bolt" class="h-4 w-4" />
                                    </button>
                                    <ul tabindex="0" class="menu dropdown-content z-20 w-48 rounded-box border border-base-300 bg-base-100 p-1 shadow-lg">
                                        <li><button type="button" wire:click="setDue({{ $action->id }}, 'week')">{{ __('In a week') }}</button></li>
                                        <li><button type="button" wire:click="setDue({{ $action->id }}, 'two_weeks')">{{ __('In two weeks') }}</button></li>
                                        <li><button type="button" wire:click="setDue({{ $action->id }}, 'month_end')">{{ __('End of the month') }}</button></li>
                                    </ul>
                                </div>
                            @endunless
                        </div>
                        @unless ($readOnly)
                            <div class="ms-auto flex gap-0.5">
                                <button type="button" class="btn btn-ghost btn-xs btn-square" @click="details = !details"
                                    aria-label="{{ __('Details') }}" title="{{ __('Details') }}">
                                    <x-icon name="o-bars-3-bottom-left" @class(['h-4 w-4', 'text-primary' => filled($action->description)]) />
                                </button>
                                <button type="button" class="btn btn-ghost btn-xs btn-square text-error"
                                    wire:click="removeAction({{ $action->id }})"
                                    wire:confirm="{{ __('Delete this action?') }}"
                                    aria-label="{{ __('Delete') }}" title="{{ __('Delete') }}">
                                    <x-icon name="o-trash" class="h-4 w-4" />
                                </button>
                            </div>
                        @endunless
                    </div>
                    @if (filled($action->description))
                        <div x-show="!details" class="prose prose-sm mt-1 max-w-none ps-9 text-muted [&>:first-child]:mt-0 [&>:last-child]:mb-0">
                            {!! \App\Support\Markdown::safe($action->description) !!}
                        </div>
                    @endif
                    <template x-if="details">
                        <div class="mt-2 ps-9">
                            <x-markdown-editor model="actions.{{ $action->id }}.description" compact commit-on-blur
                                :label="__('Details')" :sticky-toolbar="false" />
                        </div>
                    </template>
                </li>
            @endforeach
        </ul>
        @unless ($readOnly)
            <label class="input input-sm mt-1.5 w-full">
                <x-icon name="o-plus" class="h-4 w-4 text-muted" />
                <input type="text" wire:model="newAction.{{ $block }}"
                    wire:keydown.enter.prevent="addAction('{{ $block }}')"
                    placeholder="{{ __('Type an action, then Enter') }}"
                    aria-label="{{ __('New action') }}" class="grow">
            </label>
        @endunless
    </div>
</div>
