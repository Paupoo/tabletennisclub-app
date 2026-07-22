<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\UserCalendarService;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer;

    public bool $icsModal = false;

    /** Displayed month, "Y-m" format. */
    public string $month = '';

    /** Selected day, "Y-m-d" format. */
    public string $selectedDay = '';

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

    public function previousMonth(): void
    {
        $this->navigateToMonth($this->monthStart()->subMonthNoOverflow());
    }

    public function nextMonth(): void
    {
        $this->navigateToMonth($this->monthStart()->addMonthNoOverflow());
    }

    public function goToToday(): void
    {
        $this->navigateToMonth(now()->startOfMonth());
    }

    public function selectDay(string $day): void
    {
        if (! Carbon::hasFormat($day, 'Y-m-d')) {
            return;
        }

        $this->selectedDay = $day;
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

    /**
     * The displayed grid spans full weeks (Monday–Sunday), so leading and
     * trailing days of adjacent months carry their events too.
     *
     * @return array<string, array<int, array<string, mixed>>> events keyed by "Y-m-d"
     */
    #[Computed]
    public function eventsByDay(): array
    {
        $gridStart = $this->monthStart()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $this->monthStart()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $events = app(UserCalendarService::class)
            ->eventsFor($this->user, $this->showAllEvents, $this->selectedCategories, $gridStart, $gridEnd);

        $byDay = [];

        foreach ($events as $event) {
            $start = Carbon::parse($event['startDateTime'])->startOfDay();
            // Multi-day tournaments repeat on every covered day of the grid.
            $end = ! empty($event['endDate']) ? Carbon::parse($event['endDate'])->startOfDay() : $start;

            $day = $start->greaterThan($gridStart) ? $start->copy() : $gridStart->copy()->startOfDay();
            $last = $end->lessThan($gridEnd) ? $end : $gridEnd;

            while ($day->lessThanOrEqualTo($last)) {
                $byDay[$day->format('Y-m-d')][] = $event;
                $day->addDay();
            }
        }

        return $byDay;
    }

    /**
     * @return array<int, array<int, array{date: string, day: int, inMonth: bool, isToday: bool, isPast: bool, isSelected: bool, events: array<int, array<string, mixed>>}>>
     */
    #[Computed]
    public function weeks(): array
    {
        $eventsByDay = $this->eventsByDay;
        $monthStart = $this->monthStart();
        $cursor = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthStart->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $today = now()->format('Y-m-d');

        $weeks = [];
        $week = [];

        while ($cursor->lessThanOrEqualTo($gridEnd)) {
            $date = $cursor->format('Y-m-d');
            $week[] = [
                'date' => $date,
                'day' => $cursor->day,
                'inMonth' => $cursor->month === $monthStart->month,
                'isToday' => $date === $today,
                'isPast' => $date < $today,
                'isSelected' => $date === $this->selectedDay,
                'events' => $eventsByDay[$date] ?? [],
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }

            $cursor->addDay();
        }

        return $weeks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function selectedDayEvents(): array
    {
        return $this->eventsByDay[$this->selectedDay] ?? [];
    }

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;
        $this->month = now()->format('Y-m');
        $this->selectedDay = now()->format('Y-m-d');
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'weeks' => $this->weeks,
            'selectedDayEvents' => $this->selectedDayEvents,
            'monthLabel' => ucfirst($this->monthStart()->translatedFormat('F Y')),
            'isCurrentMonth' => $this->month === now()->format('Y-m'),
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

    private function monthStart(): Carbon
    {
        if (! Carbon::hasFormat($this->month, 'Y-m')) {
            $this->month = now()->format('Y-m');
        }

        return Carbon::createFromFormat('Y-m-d', $this->month . '-01')->startOfDay();
    }

    /**
     * Moving to another month re-anchors the selection: today when landing on
     * the current month, the 1st otherwise.
     */
    private function navigateToMonth(Carbon $monthStart): void
    {
        $this->month = $monthStart->format('Y-m');

        $this->selectedDay = $this->month === now()->format('Y-m')
            ? now()->format('Y-m-d')
            : $monthStart->format('Y-m-d');
    }
};
