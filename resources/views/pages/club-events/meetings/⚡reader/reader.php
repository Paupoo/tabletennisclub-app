<?php

declare(strict_types=1);

use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingActionItem;
use App\Domains\Meetings\Services\MinutesReport;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

/*
 * Published minutes, for reading — the page the minutes mail links to.
 *
 * The minutes page is the note taker's desk and writes on every action; this
 * one only reads, except for the one thing a reader has to do: the member an
 * action is assigned to ticks it done. Access is MeetingPolicy::readMinutes(),
 * checked by the route and again on each action, since a Livewire update does
 * not go through the route's middleware.
 */
new class extends Component
{
    use HasBreadcrumbs, Toast;

    #[Locked]
    public int $meetingId;

    #[Computed]
    public function canManage(): bool
    {
        return auth()->user()?->can(Permission::MeetingsManage->value) ?? false;
    }

    #[Computed]
    public function canViewBackOffice(): bool
    {
        return auth()->user()?->can(Permission::MeetingsView->value) ?? false;
    }

    public function mount(Meeting $meeting): void
    {
        $this->meetingId = $meeting->id;
    }

    public function render(): View
    {
        return $this->view();
    }

    #[Computed]
    public function report(): MinutesReport
    {
        return MinutesReport::for(Meeting::findOrFail($this->meetingId), auth()->user());
    }

    /** Tick an action done, or back to do: the member it is assigned to, or whoever runs the meetings. */
    public function toggleAction(int $actionId): void
    {
        $meeting = Meeting::findOrFail($this->meetingId);
        Gate::authorize('readMinutes', $meeting);

        $action = MeetingActionItem::query()->where('meeting_id', $meeting->id)->findOrFail($actionId);
        Gate::authorize('completeAction', [$meeting, $action->assigned_to_id]);

        $action->update(['is_completed' => ! $action->is_completed]);
        unset($this->report);

        $this->success($action->is_completed ? __('Action marked as done') : __('Action marked as to do'));
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        $meeting = Meeting::findOrFail($this->meetingId);
        $chain = Breadcrumb::make()->home();

        // Never a link that answers 403: a member reading a general assembly's
        // minutes has no business with the back-office list.
        if ($this->canViewBackOffice) {
            $chain->meetings(route('admin.meetings.index'))->add($meeting->title, route('admin.meetings.show', $meeting));
        }

        return $chain->current(__('Minutes'));
    }
};
