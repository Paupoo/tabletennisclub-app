<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\UserCalendarService;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer;

    public bool $icsModal = false;

    /** Displayed month, "Y-m" format. */
    #[Url]
    public string $month = '';

    /** @var string[] */
    public array $selectedCategories = [];

    /** Selected day, "Y-m-d" format. Entangled client-side for instant selection. */
    #[Url]
    public string $selectedDay = '';

    public bool $showAllEvents = false;

    public User $user;

    public function clearFilters(): void
    {
        $this->reset(['selectedCategories']);
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
            // Multi-day tournaments repeat on every covered day of the grid,
            // tagged with their position (day 2/3…) so continuation days
            // don't misleadingly show the day-1 start time.
            $end = empty($event['endDate']) ? $start : Carbon::parse($event['endDate'])->startOfDay();

            $day = $start->greaterThan($gridStart) ? $start->copy() : $gridStart->copy()->startOfDay();
            $last = $end->lessThan($gridEnd) ? $end : $gridEnd;
            $dayCount = (int) $start->diffInDays($end) + 1;

            while ($day->lessThanOrEqualTo($last)) {
                $entry = $event;
                $entry['dayIndex'] = (int) $start->diffInDays($day) + 1;
                $entry['dayCount'] = $dayCount;

                $byDay[$day->format('Y-m-d')][] = $entry;
                $day->addDay();
            }
        }

        return $byDay;
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

    public function goToToday(): void
    {
        $this->navigateToMonth(now()->startOfMonth());
    }

    /**
     * Permanent signed URL of the member's personal ICS feed — the signature
     * is the secret, so the feed works without a session (calendar providers
     * poll it server-side).
     */
    #[Computed]
    public function icsUrl(): string
    {
        return Illuminate\Support\Facades\URL::signedRoute('admin.user.calendar.ics', ['user' => $this->user]);
    }

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;

        // #[Url] values are applied before mount(): only fill the defaults
        // when the query string didn't provide them.
        if ($this->month === '') {
            $this->month = now()->format('Y-m');
        }

        if ($this->selectedDay === '') {
            $this->selectedDay = now()->format('Y-m-d');
        }
    }

    public function nextMonth(): void
    {
        $this->navigateToMonth($this->monthStart()->addMonthNoOverflow());
    }

    public function previousMonth(): void
    {
        $this->navigateToMonth($this->monthStart()->subMonthNoOverflow());
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

    public function selectDay(string $day): void
    {
        if (! Carbon::hasFormat($day, 'Y-m-d')) {
            return;
        }

        $this->selectedDay = $day;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function selectedDayEvents(): array
    {
        return $this->eventsByDay[$this->selectedDay] ?? [];
    }

    /** Jump straight to any month from the month/year picker. */
    public function setMonth(string $month): void
    {
        if (! Carbon::hasFormat($month, 'Y-m')) {
            return;
        }

        $this->navigateToMonth(Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay());
    }

    /** The color legend doubles as a quick category filter. */
    public function toggleCategory(string $category): void
    {
        $validIds = array_column($this->categoryOptions(), 'id');

        if (! in_array($category, $validIds)) {
            return;
        }

        $this->selectedCategories = in_array($category, $this->selectedCategories)
            ? array_values(array_filter($this->selectedCategories, fn (string $c): bool => $c !== $category))
            : [...$this->selectedCategories, $category];
    }

    /**
     * @return array<int, array<int, array{date: string, day: int, inMonth: bool, isToday: bool, isPast: bool, events: array<int, array<string, mixed>>, ariaLabel: string, panelLabel: string}>>
     */
    #[Computed]
    public function weeks(): array
    {
        $eventsByDay = $this->eventsByDay;
        $monthStart = $this->monthStart();
        $cursor = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthStart->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $today = now()->format('Y-m-d');
        $tomorrow = now()->addDay()->format('Y-m-d');

        $weeks = [];
        $week = [];

        while ($cursor->lessThanOrEqualTo($gridEnd)) {
            $date = $cursor->format('Y-m-d');
            $dayEvents = $eventsByDay[$date] ?? [];
            $count = count($dayEvents);

            $week[] = [
                'date' => $date,
                'day' => $cursor->day,
                'inMonth' => $cursor->month === $monthStart->month,
                'isToday' => $date === $today,
                'isPast' => $date < $today,
                'events' => $dayEvents,
                'ariaLabel' => ucfirst($cursor->translatedFormat('l j F')) . ' — '
                    . trans_choice(':count event|:count events', $count, ['count' => $count]),
                'panelLabel' => match ($date) {
                    $today => __('Today'),
                    $tomorrow => __('Tomorrow'),
                    default => ucfirst($cursor->translatedFormat('l j F Y')),
                },
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }

            $cursor->addDay();
        }

        return $weeks;
    }

    public function with(): array
    {
        // selectedDay is entangled client-side and URL-backed: normalize
        // anything invalid back to today before rendering.
        if (! Carbon::hasFormat($this->selectedDay, 'Y-m-d')) {
            $this->selectedDay = now()->format('Y-m-d');
        }

        $monthStart = $this->monthStart();

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'weeks' => $this->weeks,
            'monthLabel' => ucfirst($monthStart->translatedFormat('F Y')),
            'isCurrentMonth' => $this->month === now()->format('Y-m'),
            'monthHasEvents' => $this->eventsByDay !== [],
            'pickerYear' => $monthStart->year,
            'monthShortNames' => collect(range(1, 12))
                ->map(fn (int $m): string => ucfirst($monthStart->copy()->month($m)->translatedFormat('M')))
                ->all(),
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
