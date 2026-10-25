{{--
    The follow-up of earlier actions, at the head of a committee's minutes.

    One line per action still open — or ticked done since, waiting for the
    committee to see it go. One tap says where it stands; postponing,
    handing over and abandoning ask for the one thing they need.

    @param \Illuminate\Support\Collection $items MeetingActionItem, reviews of this meeting eager-loaded
    @param list<array{id: int, name: string}> $assignees
    @param bool $readOnly another member holds the pen
--}}
@props(['items', 'assignees', 'readOnly' => false])

@php
    use App\Domains\Shared\Enums\MeetingActionReviewOutcomeEnum as Outcome;
    $reviewedCount = $items->filter(fn ($action) => $action->reviews->isNotEmpty())->count();
@endphp

<section id="follow-up" data-minutes-block="follow-up" class="scroll-mt-20 rounded-xl border border-base-300 bg-base-100 p-4">
    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="flex items-center gap-2 font-bold">
            <x-icon name="o-arrow-path-rounded-square" class="h-5 w-5 text-muted" /> {{ __('Follow-up of actions') }}
        </h2>
        <span class="text-sm text-muted">{{ __(':reviewed of :total reviewed', ['reviewed' => $reviewedCount, 'total' => $items->count()]) }}</span>
    </div>

    <ul class="space-y-2">
        @foreach ($items as $action)
            @php
                $review = $action->reviews->first();
                $origin = $action->meeting;
            @endphp
            <li x-data="{ mode: null }" wire:key="follow-up-{{ $action->id }}" class="rounded-lg border border-base-300 px-3 py-2.5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-medium">{{ $action->title }}</p>
                        <p class="text-xs text-muted">
                            {{ $action->assignedTo?->full_name ?? __('Nobody yet') }}
                            · {{ $action->due_date ? __('due :date', ['date' => $action->due_date->translatedFormat('j M Y')]) : __('no due date') }}
                            · {{ __(':type of :date', ['type' => $origin->type->getLabel(), 'date' => $origin->scheduled_at?->format('d/m/Y')]) }}
                        </p>
                        @if (filled($action->progress_note))
                            <p class="mt-1 text-sm italic text-base-content/80">« {{ $action->progress_note }} »</p>
                        @endif
                    </div>
                    @if ($review)
                        <span class="badge badge-sm {{ $review->outcome->getBadgeClass() }}">{{ $review->outcome->getLabel() }}</span>
                    @elseif ($action->is_completed)
                        <span class="badge badge-sm badge-success badge-soft">{{ __('Ticked done by its owner') }}</span>
                    @endif
                </div>

                @unless ($readOnly)
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <button type="button" class="btn btn-xs btn-success btn-soft" wire:click="reviewAction({{ $action->id }}, '{{ Outcome::DONE->value }}')">{{ __('Done') }}</button>
                        <button type="button" class="btn btn-xs btn-ghost border-base-300" wire:click="reviewAction({{ $action->id }}, '{{ Outcome::IN_PROGRESS->value }}')">{{ __('In progress') }}</button>
                        <button type="button" class="btn btn-xs btn-ghost border-base-300" @click="mode = mode === 'postpone' ? null : 'postpone'">{{ __('Postpone') }}</button>
                        <button type="button" class="btn btn-xs btn-ghost border-base-300" @click="mode = mode === 'reassign' ? null : 'reassign'">{{ __('Hand over') }}</button>
                        <button type="button" class="btn btn-xs btn-ghost border-base-300" @click="mode = mode === 'abandon' ? null : 'abandon'">{{ __('Drop the action') }}</button>
                    </div>

                    <div x-show="mode === 'postpone'" x-cloak class="mt-2 flex flex-wrap items-end gap-2">
                        <input type="date" wire:model="followUpDue.{{ $action->id }}" class="input input-sm w-44" aria-label="{{ __('New due date') }}">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="reviewAction({{ $action->id }}, '{{ Outcome::POSTPONED->value }}')" @click="mode = null">{{ __('Postpone') }}</button>
                    </div>
                    <div x-show="mode === 'reassign'" x-cloak class="mt-2 flex flex-wrap items-end gap-2">
                        <select wire:model="followUpOwner.{{ $action->id }}" class="select select-sm w-56" aria-label="{{ __('New owner') }}">
                            <option value="">{{ __('Pick a member') }}</option>
                            @foreach ($assignees as $assignee)
                                <option value="{{ $assignee['id'] }}">{{ $assignee['name'] }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="reviewAction({{ $action->id }}, '{{ Outcome::REASSIGNED->value }}')" @click="mode = null">{{ __('Hand over') }}</button>
                    </div>
                    <div x-show="mode === 'abandon'" x-cloak class="mt-2 flex flex-wrap items-end gap-2">
                        <input type="text" wire:model="followUpReason.{{ $action->id }}" maxlength="255" class="input input-sm w-full sm:w-80"
                            placeholder="{{ __('Why? One line for the minutes') }}" aria-label="{{ __('Reason') }}">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="reviewAction({{ $action->id }}, '{{ Outcome::ABANDONED->value }}')">{{ __('Drop the action') }}</button>
                    </div>
                    @error("followUpReason.{$action->id}") <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                    @error("followUpDue.{$action->id}") <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                    @error("followUpOwner.{$action->id}") <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                @endunless
            </li>
        @endforeach
    </ul>
</section>
