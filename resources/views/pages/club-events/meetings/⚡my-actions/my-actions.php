<?php

declare(strict_types=1);

use App\Domains\Meetings\Models\MeetingActionItem;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

/*
 * The actions handed to the reader, from every meeting — committees and
 * general assemblies alike.
 *
 * Between two meetings this is where an owner says where things stand: a
 * short note the next committee reads aloud, and the tick once it is done.
 * Postponing, handing over and abandoning stay with the committee. Every
 * query is scoped to the reader: a Livewire update does not replay the
 * route's middleware, and an id in a request is nobody's key to another's list.
 */
new class extends Component
{
    use HasBreadcrumbs, Toast;

    /** @var array<int, string> action id => the note being typed */
    public array $notes = [];

    /**
     * Closed in the last three months: done, or dropped by the committee.
     *
     * @return Collection<int, MeetingActionItem>
     */
    #[Computed]
    public function closedActions(): Collection
    {
        return $this->mine()
            ->where(fn (Builder $closed) => $closed->where('is_completed', true)->orWhereNotNull('abandoned_at'))
            ->where(fn (Builder $recent) => $recent
                ->where('completed_at', '>=', now()->subMonths(3))
                ->orWhere('abandoned_at', '>=', now()->subMonths(3)))
            ->with('meeting.minutes')
            ->latest('updated_at')
            ->orderByDesc('meeting_action_items.id')
            ->get();
    }

    public function markDone(int $actionId): void
    {
        $this->mine()->open()->findOrFail($actionId)->update(['is_completed' => true]);
        unset($this->openActions, $this->closedActions);
        $this->success(__('Action marked as done'));
    }

    public function mount(): void
    {
        $this->notes = $this->openActions->mapWithKeys(fn (MeetingActionItem $action): array => [$action->id => $action->progress_note ?? ''])->all();
    }

    /**
     * Still to do, the soonest due first; those without a date last.
     *
     * @return Collection<int, MeetingActionItem>
     */
    #[Computed]
    public function openActions(): Collection
    {
        return $this->mine()
            ->open()
            ->with('meeting.minutes')
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('meeting_action_items.id')
            ->get();
    }

    public function render(): View
    {
        return $this->view();
    }

    /** Ticked by mistake: back to do. An abandoned action stays the committee's to reopen. */
    public function reopen(int $actionId): void
    {
        $this->mine()->where('is_completed', true)->findOrFail($actionId)->update(['is_completed' => false]);
        unset($this->openActions, $this->closedActions);
        $this->notes[$actionId] ??= '';
    }

    /** Where it stands, in a line the next committee reads. */
    public function saveNote(int $actionId): void
    {
        $this->validate(["notes.{$actionId}" => ['nullable', 'string', 'max:500']], attributes: ["notes.{$actionId}" => __('note')]);

        $note = trim($this->notes[$actionId] ?? '');
        $this->mine()->open()->findOrFail($actionId)->update([
            'progress_note' => $note === '' ? null : $note,
            'progress_noted_at' => $note === '' ? null : now(),
        ]);

        unset($this->openActions);
        $this->success(__('Note saved'));
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->add(__('Meetings'), route('admin.meetings.index'))
            ->current(__('My actions'));
    }

    /** @return Builder<MeetingActionItem> */
    private function mine(): Builder
    {
        return MeetingActionItem::query()->where('assigned_to_id', auth()->id());
    }
};
