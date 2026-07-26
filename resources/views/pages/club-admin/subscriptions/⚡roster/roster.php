<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubAdmin\Subscriptions\Roster;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\AgeCategoryEnum;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer, Toast, WithPagination;

    /** Tri-state filter: '1' yes, '0' no, '' any. */
    #[Url]
    public string $ageCategory = '';

    /** Tri-state filter: '1' yes, '0' no, '' any. */
    #[Url]
    public string $canDrive = '';

    /** Tri-state filter: '1' yes, '0' no, '' any. */
    #[Url]
    public string $competitive = '';

    #[Url]
    public string $search = '';

    /** @var array{column: string, direction: string} */
    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    /** Tri-state filter: '1' yes, '0' no, '' any. */
    #[Url]
    public string $volunteerHelp = '';

    /** Tri-state filter: '1' yes, '0' no, '' any. */
    #[Url]
    public string $wantsDirectedTraining = '';

    /** Tri-state filter: '1' yes, '0' no, '' any. */
    #[Url]
    public string $wantsToBeCaptain = '';

    public function clearFilters(): void
    {
        $this->competitive = '';
        $this->canDrive = '';
        $this->wantsToBeCaptain = '';
        $this->volunteerHelp = '';
        $this->wantsDirectedTraining = '';
        $this->ageCategory = '';
        $this->resetPage();
    }

    /** @return array<int, array{key: string, label: string}> */
    #[Computed]
    public function filterChips(): array
    {
        return $this->getFilterChips();
    }

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        $yesNo = fn (string $value): string => $value === '1' ? __('Yes') : __('No');

        if (filled($this->competitive)) {
            $chips[] = ['key' => 'competitive', 'label' => __('Competitive') . ': ' . $yesNo($this->competitive)];
        }

        if (filled($this->canDrive)) {
            $chips[] = ['key' => 'canDrive', 'label' => __('Can drive') . ': ' . $yesNo($this->canDrive)];
        }

        if (filled($this->wantsToBeCaptain)) {
            $chips[] = ['key' => 'wantsToBeCaptain', 'label' => __('Captain') . ': ' . $yesNo($this->wantsToBeCaptain)];
        }

        if (filled($this->volunteerHelp)) {
            $chips[] = ['key' => 'volunteerHelp', 'label' => __('Volunteer') . ': ' . $yesNo($this->volunteerHelp)];
        }

        if (filled($this->wantsDirectedTraining)) {
            $chips[] = ['key' => 'wantsDirectedTraining', 'label' => __('Directed') . ': ' . $yesNo($this->wantsDirectedTraining)];
        }

        if (filled($this->ageCategory)) {
            $label = AgeCategoryEnum::tryFrom($this->ageCategory)?->getLabel() ?? $this->ageCategory;
            $chips[] = ['key' => 'ageCategory', 'label' => __('Age category') . ': ' . $label];
        }

        return $chips;
    }

    public function render(): View
    {
        return $this->view();
    }

    /**
     * Toggle a boolean season attribute on a subscription (admin entry — decision #3).
     * Reserved to the management group (decision #18).
     */
    public function updateAttribute(int $subscriptionId, string $attribute, bool $value): void
    {
        $this->authorizeManagement();

        if (! in_array($attribute, ['can_drive', 'wants_to_be_captain', 'volunteer_help', 'wants_directed_training'], true)) {
            return;
        }

        $subscription = $this->seasonSubscription($subscriptionId);

        $payload = [$attribute => $value];

        // Turning driving off clears any residual seat count.
        if ($attribute === 'can_drive' && ! $value) {
            $payload['seats_available'] = null;
        }

        $subscription->update($payload);

        $this->success(__('Roster updated.'));
    }

    public function updatedAgeCategory(): void
    {
        $this->resetPage();
    }

    public function updatedCanDrive(): void
    {
        $this->resetPage();
    }

    public function updatedCompetitive(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedVolunteerHelp(): void
    {
        $this->resetPage();
    }

    public function updatedWantsDirectedTraining(): void
    {
        $this->resetPage();
    }

    public function updatedWantsToBeCaptain(): void
    {
        $this->resetPage();
    }

    /**
     * Set the number of seats available for carpooling on a subscription.
     */
    public function updateSeats(int $subscriptionId, ?int $seats): void
    {
        $this->authorizeManagement();

        $subscription = $this->seasonSubscription($subscriptionId);

        $subscription->update([
            'seats_available' => $seats !== null ? max(0, $seats) : null,
        ]);

        $this->success(__('Roster updated.'));
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $season = Season::active()->first();

        $triStateOptions = [
            ['id' => '1', 'name' => __('Yes')],
            ['id' => '0', 'name' => __('No')],
        ];

        // Explicit child → teen → adult order (Pint sorts enum cases alphabetically).
        $ageCategoryOptions = collect([
            AgeCategoryEnum::CHILD,
            AgeCategoryEnum::TEEN,
            AgeCategoryEnum::ADULT,
        ])->map(fn (AgeCategoryEnum $c): array => ['id' => $c->value, 'name' => $c->getLabel()]);

        $headers = [
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'ranking', 'label' => __('Ranking')],
            ['key' => 'age_category', 'label' => __('Age category'), 'sortable' => false, 'class' => 'hidden md:table-cell'],
            ['key' => 'is_competitive', 'label' => __('Competitive'), 'sortable' => false],
            ['key' => 'can_drive', 'label' => __('Can drive'), 'sortable' => false],
            ['key' => 'wants_to_be_captain', 'label' => __('Captain'), 'sortable' => false, 'class' => 'hidden lg:table-cell'],
            ['key' => 'volunteer_help', 'label' => __('Volunteer'), 'sortable' => false, 'class' => 'hidden lg:table-cell'],
            ['key' => 'wants_directed_training', 'label' => __('Directed'), 'sortable' => false, 'class' => 'hidden lg:table-cell'],
        ];

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'season' => $season,
            'rows' => $this->rows(),
            'headers' => $headers,
            'triStateOptions' => $triStateOptions,
            'ageCategoryOptions' => $ageCategoryOptions,
            'canManage' => Gate::allows(Permission::SubscriptionsManage->value),
            'filterChips' => $this->filterChips,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Season roster'));
    }

    /**
     * Build the roster rows for the active season.
     *
     * Boolean/competitive filters and ranking sort happen in SQL (with `user`
     * eager-loaded to avoid N+1). Age category is derived in PHP from the
     * member's birthdate (decision #5) and therefore filtered after hydration;
     * pagination is rebuilt manually so the page count stays accurate.
     */
    protected function rows(): LengthAwarePaginator
    {
        $season = Season::active()->first();

        if ($season === null) {
            return new LengthAwarePaginator(collect(), 0, 20, $this->getPage());
        }

        $query = Subscription::query()
            ->with('user')
            ->forSeason($season)
            ->active()
            ->whereHas('user')
            ->when($this->search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%")))
            ->when($this->competitive !== '', fn ($q) => $q->where('is_competitive', $this->competitive === '1'))
            ->when($this->canDrive !== '', fn ($q) => $q->where('can_drive', $this->canDrive === '1'))
            ->when($this->wantsToBeCaptain !== '', fn ($q) => $q->where('wants_to_be_captain', $this->wantsToBeCaptain === '1'))
            ->when($this->volunteerHelp !== '', fn ($q) => $q->where('volunteer_help', $this->volunteerHelp === '1'))
            ->when($this->wantsDirectedTraining !== '', fn ($q) => $q->where('wants_directed_training', $this->wantsDirectedTraining === '1'));

        if ($this->sortBy['column'] === 'ranking') {
            $query->join('users', 'users.id', '=', 'subscriptions.user_id')
                ->orderBy('users.ranking', $this->sortBy['direction'])
                ->select('subscriptions.*');
        } else {
            $query->join('users', 'users.id', '=', 'subscriptions.user_id')
                ->orderBy('users.first_name', $this->sortBy['direction'])
                ->orderBy('users.last_name', $this->sortBy['direction'])
                ->select('subscriptions.*');
        }

        /** @var Collection<int, object> $mapped */
        $mapped = $query->get()
            ->map(fn (Subscription $sub): object => $this->toRow($sub))
            ->filter(fn (object $row): bool => $this->ageCategory === '' || $row->age_category === $this->ageCategory)
            ->values();

        $perPage = 20;
        $page = $this->getPage();

        return new LengthAwarePaginator(
            $mapped->forPage($page, $perPage)->values(),
            $mapped->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /**
     * @return object{
     *   id: int, name: string, ranking: ?string, age_category: ?string,
     *   age_label: ?string, is_competitive: bool, can_drive: bool,
     *   seats_available: ?int, wants_to_be_captain: bool, volunteer_help: bool,
     *   wants_directed_training: bool
     * }
     */
    protected function toRow(Subscription $sub): object
    {
        $birthdate = $sub->user->birthdate;
        $category = $birthdate !== null ? AgeCategoryEnum::fromBirthdate($birthdate) : null;

        return (object) [
            'id' => $sub->id,
            'name' => $sub->user->first_name . ' ' . $sub->user->last_name,
            'ranking' => $sub->user->ranking,
            'age_category' => $category?->value,
            'age_label' => $category?->getLabel(),
            'is_competitive' => $sub->is_competitive,
            'can_drive' => $sub->can_drive,
            'seats_available' => $sub->seats_available,
            'wants_to_be_captain' => $sub->wants_to_be_captain,
            'volunteer_help' => $sub->volunteer_help,
            'wants_directed_training' => $sub->wants_directed_training,
        ];
    }

    /**
     * The roster is readable at the committee baseline; changing what it records
     * about a member is the `membres` duty.
     */
    private function authorizeManagement(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);
    }

    /**
     * Resolve a subscription that belongs to the active season (guards against
     * editing rows outside the current roster).
     */
    private function seasonSubscription(int $subscriptionId): Subscription
    {
        $season = Season::active()->first();

        return Subscription::query()
            ->forSeason($season)
            ->active()
            ->findOrFail($subscriptionId);
    }
};
