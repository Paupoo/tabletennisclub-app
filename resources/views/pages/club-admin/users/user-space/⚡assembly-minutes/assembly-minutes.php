<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * The general assemblies' minutes, filed with the club's other texts (rules,
 * charter) rather than with what a member signs up for. The entry is always in
 * the menu: one assembly a year leaves the page empty for months, and the empty
 * state says so.
 */
new #[Title('General assembly minutes')] class extends Component
{
    use HasBreadcrumbs;

    public User $user;

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'assemblies' => $this->assemblies(),
        ];
    }

    /**
     * The minutes this member may read: sent to all, and opened by
     * MeetingPolicy::readMinutes() — a mail gets lost, the minutes should not.
     *
     * @return Collection<int, Meeting>
     */
    protected function assemblies(): Collection
    {
        return Meeting::query()
            ->with('minutes')
            ->where('type', MeetingTypeEnum::GENERAL_ASSEMBLY)
            ->whereHas('minutes', fn ($minutes) => $minutes->where('is_published', true)->whereNotNull('sent_to_all_at'))
            ->orderByDesc('scheduled_at')
            ->orderByDesc('meetings.id')
            ->get()
            ->filter(fn (Meeting $meeting): bool => Gate::allows('readMinutes', $meeting))
            ->values();
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('General assembly minutes'));
    }
};
