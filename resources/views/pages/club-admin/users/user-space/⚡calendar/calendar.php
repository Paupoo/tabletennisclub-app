<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\UserCalendarService;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer;

    public bool $icsModal = false;

    /** @var string[] */
    public array $selectedCategories = [];

    public bool $showAllEvents = false;

    public User $user;

    public function clearFilters(): void
    {
        $this->reset(['selectedCategories']);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getFilterChips(): array
    {
        $labels = collect($this->categoryOptions())->pluck('name', 'id');

        return collect($this->selectedCategories)
            ->map(fn (string $category): array => [
                'key' => 'category:' . $category,
                'label' => $labels[$category] ?? $category,
            ])
            ->values()
            ->all();
    }

    /**
     * No pagination on this page, and categories are an array filter:
     * remove one category at a time from its chip.
     */
    public function removeFilter(string $key): void
    {
        if (str_starts_with($key, 'category:')) {
            $category = substr($key, strlen('category:'));
            $this->selectedCategories = array_values(
                array_filter($this->selectedCategories, fn (string $c): bool => $c !== $category)
            );

            return;
        }

        $this->reset([$key]);
    }

    /**
     * Permanent signed URL of the member's personal ICS feed — the signature
     * is the secret, so the feed works without a session (calendar providers
     * poll it server-side).
     */
    #[Computed]
    public function icsUrl(): string
    {
        return URL::signedRoute('admin.user.calendar.ics', ['user' => $this->user]);
    }

    #[Computed]
    public function calendarData(): array
    {
        return app(UserCalendarService::class)
            ->eventsFor($this->user, $this->showAllEvents, $this->selectedCategories)
            ->groupBy('monthKey')
            ->map(fn ($group) => $group->values()->all())
            ->all();
    }

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'calendar' => $this->calendarData,
            'categories' => $this->categoryOptions(),
            'filterChips' => $this->getFilterChips(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Calendar'));
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function categoryOptions(): array
    {
        return [
            ['id' => 'tournament', 'name' => __('Tournament')],
            ['id' => 'training',   'name' => __('Training')],
            ['id' => 'interclub',  'name' => __('Interclub')],
            ['id' => 'meeting',    'name' => __('Meeting')],
        ];
    }
};
