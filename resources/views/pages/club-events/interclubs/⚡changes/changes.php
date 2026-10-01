<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\Changes;

use App\Domains\Competitions\Interclub\Models\InterclubChange;
use App\Domains\Competitions\Interclub\Notifications\InterclubChangeNotification;
use App\Domains\Competitions\Interclub\Services\InterclubChangeNotifier;
use App\Domains\Shared\Enums\InterclubChangeStatus;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * Federation changes held back because one sync brought too many at once.
 *
 * The calendar already reflects them; only the messages wait. Whoever holds
 * the interclubs duty checks them against what the federation announced and
 * either tells the teams or lets them go untold.
 */
new class extends Component
{
    use HasBreadcrumbs, Toast;

    /** @var array<int, string> The groups ticked for the next action. */
    public array $selected = [];

    /**
     * Mark the ticked changes as reviewed and not to be sent.
     */
    public function dismissSelected(): void
    {
        Gate::authorize(Permission::InterclubsManage->value);

        $changes = $this->selectedChanges();

        InterclubChange::whereKey($changes->modelKeys())->update([
            'status' => InterclubChangeStatus::DISMISSED,
            'notified_by' => Auth::id(),
        ]);

        $this->selected = [];
        $this->success(__('Changes dismissed: no team was told.'), position: 'toast-bottom toast-end');
    }

    /**
     * Tell the teams about the ticked changes, as the sync would have.
     *
     * A fixture that has started since is no longer announced — the same rule
     * the sync follows — and is set aside rather than sent late.
     */
    public function notifySelected(InterclubChangeNotifier $notifier): void
    {
        Gate::authorize(Permission::InterclubsManage->value);

        [$started, $toSend] = $this->selectedChanges()
            ->partition(fn (InterclubChange $change): bool => $change->interclub->start_date_time->isPast());

        InterclubChange::whereKey($started->modelKeys())->update(['status' => InterclubChangeStatus::SILENT]);

        if ($toSend->isNotEmpty()) {
            $notifier->notify($toSend, Auth::user());
        }

        $this->selected = [];
        $this->success(trans_choice('{0} Nothing left to send.|{1} 1 team message sent.|[2,*] :count team messages sent.', $notifier->groups($toSend)->count()), position: 'toast-bottom toast-end');
    }

    public function render(): View
    {
        return $this->view()->title(__('Federation changes'));
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add(__('Interclubs'), route('admin.interclubs.captain-selection'))
                ->current(__('Federation changes'))
                ->toArray(),
            'groups' => $this->heldGroups(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Federation changes'));
    }

    /**
     * The held changes as the teams would receive them.
     *
     * @return Collection<string, array{subject: string, rows: array<int, array{before: string, after: string}>, isReschedule: bool, fixtureStarted: bool}>
     */
    private function heldGroups(): Collection
    {
        $held = InterclubChange::with(['interclub.visitedTeam.club', 'interclub.visitingTeam.club'])
            ->where('status', InterclubChangeStatus::HELD)
            ->orderBy('id')
            ->get();

        return app(InterclubChangeNotifier::class)->groups($held)
            ->map(fn (Collection $group): array => [
                'subject' => (new InterclubChangeNotification($group))->subject(),
                'rows' => $group->map(fn (InterclubChange $change): array => [
                    'before' => InterclubChangeNotification::moment($change->before),
                    'after' => InterclubChangeNotification::moment($change->after),
                ])->values()->all(),
                'isReschedule' => $group->first()->kind->value === 'Rescheduled',
                'fixtureStarted' => $group->contains(fn (InterclubChange $change): bool => $change->interclub->start_date_time->isPast()),
            ]);
    }

    /**
     * @return EloquentCollection<int, InterclubChange>
     */
    private function selectedChanges(): EloquentCollection
    {
        $held = InterclubChange::with(['interclub.visitedTeam.club', 'interclub.visitingTeam.club'])
            ->where('status', InterclubChangeStatus::HELD)
            ->orderBy('id')
            ->get();

        return new EloquentCollection(app(InterclubChangeNotifier::class)->groups($held)
            ->only($this->selected)
            ->flatMap(fn (Collection $group): array => $group->all())
            ->values()
            ->all());
    }
};
