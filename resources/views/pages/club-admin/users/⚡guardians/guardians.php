<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\GuardianDuplicates;
use App\Domains\Shared\Enums\Permission;
use App\Jobs\SendGuardianInvitationJob;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

/**
 * Every responsible adult the club has on file.
 *
 * Read-only on purpose: correcting and merging a sheet happen on the file of a
 * child it answers for, so this screen is where the office looks before acting.
 */
new class extends Component
{
    use HasBreadcrumbs, HasBulkActions, HasFilterDrawer, Toast, WithPagination;

    /** One stage of Guardian::invitationStage(), or every one. */
    #[Url]
    public string $account = '';

    public bool $bulkDeleteModal = false;

    public bool $deleteModal = false;

    public ?int $guardianToDelete = null;

    /** Both sheets of every adult on file twice — where the dashboard task leads. */
    #[Url]
    public bool $onFileTwice = false;

    /** An adult's name, email or phone, or the name of a child they answer for. */
    public string $search = '';

    /** `name`, or `created_at` for the sheets typed in last first. */
    #[Url]
    public string $sortBy = 'name';

    /** Sheets that answer for no child, and so reach no one. */
    #[Url]
    public bool $withoutChildren = false;

    private ?GuardianDuplicates $duplicates = null;

    /**
     * The stages an account can be at, in the order the office cares about them.
     *
     * @return array<string, string>
     */
    public function accountOptions(): array
    {
        return [
            'actionable' => __('To invite'),
            'waiting' => __('Invitation pending'),
            'ready' => __('Account active'),
            'unreachable' => __('No account, no email'),
        ];
    }

    /**
     * Deletes every selected sheet no child still at the club relies on, and
     * names the others: the office replaces them on the child's file first.
     */
    public function bulkDelete(): void
    {
        Gate::authorize(Permission::UsersUpdate->value);

        $this->bulkDeleteModal = false;

        $deleted = 0;
        $kept = [];

        foreach (Guardian::query()->whereKey($this->selected)->get() as $guardian) {
            $children = $this->childrenAtTheClub($guardian);

            if ($children !== []) {
                $kept[] = __(':name (:children)', ['name' => $guardian->full_name, 'children' => implode(', ', $children)]);

                continue;
            }

            $guardian->delete();
            $deleted++;
        }

        $this->clearSelection();
        unset($this->guardians);

        $message = trans_choice('{0}No sheet deleted.|{1}1 sheet deleted.|[2,*]:count sheets deleted.', $deleted);

        if ($kept === []) {
            $this->success($message);

            return;
        }

        $this->warning($message . ' ' . __('Kept, a child still at the club relies on them: :names.', ['names' => implode(', ', $kept)]));
    }

    /**
     * Mails the link to the adults of the selection still to invite. The others
     * are counted out: a link already on its way, an account already active,
     * or no address to write to. The batch shares the `invitations` limiter
     * with the members list.
     */
    public function bulkInvite(): void
    {
        Gate::authorize(Permission::UsersUpdate->value);
        Gate::authorize('sendEmail', User::class);

        $guardians = Guardian::query()->with('member')->whereKey($this->selected)->get();
        $toInvite = $guardians->filter(fn (Guardian $guardian): bool => $guardian->invitationStage() === 'actionable');
        $others = $guardians->count() - $toInvite->count();

        $this->clearSelection();

        if ($toInvite->isEmpty()) {
            $this->warning(__('Nobody in this selection can be invited.'));

            return;
        }

        Bus::batch(
            $toInvite->map(fn (Guardian $guardian): SendGuardianInvitationJob => new SendGuardianInvitationJob($guardian->id))->values()->all()
        )->name('invitations')->dispatch();

        unset($this->guardians);

        $message = __(':count invitation(s) on their way.', ['count' => $toInvite->count()]);

        if ($others > 0) {
            $message .= ' ' . __(':count left out: already invited, account already active, or no email.', ['count' => $others]);
        }

        $this->success($message);
    }

    public function clearFilters(): void
    {
        $this->reset(['account', 'onFileTwice', 'search', 'withoutChildren']);
        $this->resetPage();
    }

    public function confirmBulkDelete(): void
    {
        Gate::authorize(Permission::UsersUpdate->value);

        $this->bulkDeleteModal = true;
    }

    public function confirmDelete(int $guardianId): void
    {
        Gate::authorize('delete', Guardian::findOrFail($guardianId));

        $this->guardianToDelete = $guardianId;
        $this->deleteModal = true;
    }

    /**
     * The person this sheet is already on file as, if any: another sheet, or
     * the member whose address it carries.
     */
    public function counterpartOf(Guardian $guardian): Guardian|User|null
    {
        return $this->duplicates()->counterpartOf($guardian);
    }

    /**
     * A sheet goes only once no child still at the club relies on it: deleting
     * it would take a minor's guardian away, and with it their mail, their
     * proxy and the family discount. Archived children do not hold it back.
     */
    public function delete(): void
    {
        $guardian = Guardian::findOrFail($this->guardianToDelete);

        Gate::authorize('delete', $guardian);

        $this->deleteModal = false;
        $this->guardianToDelete = null;

        $children = $this->childrenAtTheClub($guardian);

        if ($children !== []) {
            $this->error(__(':name still answers for :children: unlink them in Edit first.', [
                'name' => $guardian->full_name,
                'children' => implode(', ', $children),
            ]));

            return;
        }

        $guardian->delete();

        unset($this->guardians);
        $this->success(__(':name was deleted.', ['name' => $guardian->full_name]));
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->onFileTwice) {
            $chips[] = ['key' => 'onFileTwice', 'label' => __('On file twice')];
        }

        if ($this->withoutChildren) {
            $chips[] = ['key' => 'withoutChildren', 'label' => __('No child')];
        }

        if (array_key_exists($this->account, $this->accountOptions())) {
            $chips[] = ['key' => 'account', 'label' => $this->accountOptions()[$this->account]];
        }

        if (trim($this->search) !== '') {
            $chips[] = ['key' => 'search', 'label' => $this->search];
        }

        return $chips;
    }

    public function getTotalMatchingCount(): int
    {
        return $this->guardians->total();
    }

    /**
     * @return LengthAwarePaginator<int, Guardian>
     */
    #[Computed]
    public function guardians(): LengthAwarePaginator
    {
        return $this->matchingQuery()
            // Archived children too: a sheet that only answers for them is not a
            // sheet that answers for no one.
            ->with(['member', 'users' => fn ($query) => $query->withTrashed()->orderBy('first_name')])
            ->withExists(['member as is_member_this_season' => fn ($query) => $query->active()])
            ->when(
                $this->sortBy === 'created_at',
                fn (Builder $query) => $query->latest('guardians.created_at'),
                fn (Builder $query) => $query
                    ->orderByRaw($this->nameOnFile('last_name'))
                    ->orderByRaw($this->nameOnFile('first_name')),
            )
            ->orderBy('guardians.id')
            ->paginate(25);
    }

    /** A sheet corrected, merged or given children in the editor: read the rows again. */
    #[On('guardian-updated')]
    public function refreshGuardians(): void
    {
        unset($this->guardians);
    }

    public function render(): View
    {
        return $this->view()->title(__('Responsible adults'));
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['account', 'onFileTwice', 'search', 'sortBy', 'withoutChildren'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'filterChips' => $this->getFilterChips(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->users()
            ->current(__('Responsible adults'));
    }

    /**
     * @return array<int, string>
     */
    protected function getPageIds(): array
    {
        return collect($this->guardians->items())->map(fn (Guardian $guardian): string => (string) $guardian->id)->all();
    }

    /**
     * The sheets the list holds, search and filters applied: what a page shows
     * and what "select all results" selects.
     *
     * @return Builder<Guardian>
     */
    protected function matchingQuery(): Builder
    {
        return Guardian::query()
            ->when(trim($this->search) !== '', fn (Builder $query) => $this->applySearch($query))
            ->when($this->withoutChildren, fn (Builder $query) => $query->whereDoesntHave('users', fn (Builder $child) => $child->withTrashed()))
            ->when($this->onFileTwice, fn (Builder $query) => $query->whereKey($this->duplicates()->onFileTwice()->modelKeys()))
            ->when(array_key_exists($this->account, $this->accountOptions()), fn (Builder $query) => $query->whereKey(
                Guardian::query()->with('member')->get()
                    ->filter(fn (Guardian $guardian): bool => $guardian->invitationStage() === $this->account)
                    ->modelKeys()
            ));
    }

    /**
     * @return array<int, string>
     */
    protected function selectionScope(): array
    {
        return ['account', 'onFileTwice', 'search', 'withoutChildren'];
    }

    /**
     * Every word must match somewhere: on the sheet, on the account of an adult
     * who signs in (whose details are read from it, not from the sheet), or on a
     * child they answer for. A phone number matches whatever spaces, dots or
     * slashes it was typed with.
     *
     * @param  Builder<Guardian>  $query
     */
    private function applySearch(Builder $query): void
    {
        $terms = preg_split('/\s+/', mb_strtolower(trim($this->search)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($terms as $term) {
            $like = '%' . $term . '%';
            $digits = preg_replace('/\D/', '', $term);
            $phoneLike = $digits !== '' && $digits === preg_replace('/[\s.\/+-]/', '', $term) ? '%' . $digits . '%' : null;

            $query->where(function (Builder $match) use ($like, $phoneLike): void {
                $match->whereRaw('LOWER(guardians.first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(guardians.last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(guardians.email) LIKE ?', [$like])
                    ->orWhereHas('member', fn (Builder $member) => $member
                        ->whereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                        ->when($phoneLike !== null, fn (Builder $phone) => $phone->orWhereRaw($this->digitsOf('phone_number') . ' LIKE ?', [$phoneLike])))
                    ->orWhereHas('users', fn (Builder $child) => $child
                        ->whereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like]))
                    ->when($phoneLike !== null, fn (Builder $phone) => $phone->orWhereRaw($this->digitsOf('guardians.phone') . ' LIKE ?', [$phoneLike]));
            });
        }
    }

    /**
     * The names of the children still at the club this sheet answers for.
     *
     * @return array<int, string>
     */
    private function childrenAtTheClub(Guardian $guardian): array
    {
        return $guardian->users()->get()
            ->map(fn (User $child): string => $child->first_name . ' ' . $child->last_name)
            ->all();
    }

    /** A phone column with its separators stripped, as SQL. */
    private function digitsOf(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, ' ', ''), '.', ''), '/', ''), '-', ''), '+', '')";
    }

    /** Read once per request: the list asks it for every row it shows. */
    private function duplicates(): GuardianDuplicates
    {
        return $this->duplicates ??= new GuardianDuplicates;
    }

    /**
     * A name as the list shows it: an adult who signs in carries the one on
     * their account, whatever the sheet still says.
     */
    private function nameOnFile(string $column): string
    {
        return "LOWER(COALESCE((SELECT users.{$column} FROM users WHERE users.id = guardians.user_id), guardians.{$column}))";
    }
};
