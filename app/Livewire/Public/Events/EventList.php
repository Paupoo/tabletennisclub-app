<?php

declare(strict_types=1);

namespace App\Livewire\Public\Events;

use App\Domains\ClubAdmin\Subscriptions\Models\Registration;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\ClubEventTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class EventList extends Component
{
    public int $defaultSeasonId = 0;

    public int $seasonId = 0;

    public Collection $seasons;

    public string $type = '';

    protected array $queryString = [
        'seasonId' => ['except' => 0, 'as' => 'season'],
        'type' => ['except' => ''],
    ];

    public function clearAllFilters(): void
    {
        $this->type = '';
        $this->seasonId = $this->defaultSeasonId;
    }

    public function clearFilter(string $filter): void
    {
        match ($filter) {
            'type' => $this->type = '',
            'seasonId' => $this->seasonId = $this->defaultSeasonId,
            default => null,
        };
    }

    public function getActiveFiltersCountProperty(): int
    {
        return collect([
            $this->seasonId !== $this->defaultSeasonId,
            $this->type !== '',
        ])->filter()->count();
    }

    public function getEventsProperty(): Collection
    {
        $today = now()->startOfDay();

        $query = EventPost::published()
            ->when($this->type, fn (Builder $q) => $q->where('type', $this->type))
            ->when($this->seasonId > 0, function (Builder $q): void {
                $season = $this->seasons->firstWhere('id', $this->seasonId);
                if ($season) {
                    $q->whereBetween('event_date', $this->seasonDateRange($season));
                }
            });

        $userRegisteredIds = $this->userRegisteredEventIds();

        return $query->get()
            ->sortBy(fn (EventPost $event): array => [
                $event->event_date >= $today ? 0 : 1,
                $event->event_date,
            ])
            ->map(fn (EventPost $event): array => [
                'id' => $event->id,
                'type' => $event->type->value,
                'type_label' => $event->type->getLabel(),
                'title' => $event->title,
                'description' => $event->description,
                'date' => $event->formatted_date,
                'time' => $event->formatted_time,
                'location' => $event->location,
                'price' => $event->price && (float) $event->price > 0
                    ? number_format((float) $event->price, 2, ',', ' ') . ' €'
                    : __('Gratuit'),
                'icon' => $event->icon,
                'is_past' => $event->is_past,
                'is_registered' => in_array($event->id, $userRegisteredIds, true),
            ])
            ->values();
    }

    public function getNextSeasonProperty(): ?Season
    {
        $season = $this->seasons->firstWhere('id', $this->seasonId);

        if (! $season) {
            return null;
        }

        return Season::where('start_at', '>', $season->start_at)->orderBy('start_at')->first();
    }

    public function getPreviousSeasonProperty(): ?Season
    {
        $season = $this->seasons->firstWhere('id', $this->seasonId);

        if (! $season) {
            return null;
        }

        return $this->seasons
            ->filter(fn (Season $s) => $s->start_at->lt($season->start_at))
            ->sortByDesc('start_at')
            ->first();
    }

    public function mount(): void
    {
        $this->seasons = $this->loadSeasons();
        $this->defaultSeasonId = Season::current()?->id ?? 0;

        if ($this->seasonId === 0) {
            $this->seasonId = $this->defaultSeasonId;
        }
    }

    public function render(): View
    {
        return view('livewire.public.events.event-list', [
            'events' => $this->events,
            'activeFiltersCount' => $this->activeFiltersCount,
            'eventTypes' => $this->availableTypes(),
            'previousSeason' => $this->previousSeason,
            'nextSeason' => $this->nextSeason,
        ]);
    }

    public function viewSeason(int $seasonId): void
    {
        $this->seasonId = $seasonId;
    }

    private function availableTypes(): array
    {
        return [
            ClubEventTypeEnum::TOURNAMENT->value => ClubEventTypeEnum::TOURNAMENT->getLabel(),
            ClubEventTypeEnum::TRAINING->value => ClubEventTypeEnum::TRAINING->getLabel(),
            ClubEventTypeEnum::INTERCLUB->value => ClubEventTypeEnum::INTERCLUB->getLabel(),
        ];
    }

    private function loadSeasons(): Collection
    {
        $currentSeason = Season::current();

        return Season::orderByDesc('start_at')
            ->when(
                $currentSeason,
                fn (Builder $q) => $q->where('start_at', '<', $currentSeason->start_at),
                fn (Builder $q) => $q->where('start_at', '<', now())
            )
            ->limit(5)
            ->get()
            ->when($currentSeason, fn (Collection $coll) => $coll->prepend($currentSeason));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function seasonDateRange(Season $season): array
    {
        $previousSeasonEnd = Season::where('start_at', '<', $season->start_at)->max('end_at');
        $nextSeasonStart = Season::where('start_at', '>', $season->start_at)->min('start_at');

        $start = $previousSeasonEnd
            ? Carbon::parse($previousSeasonEnd)->addDay()->startOfDay()
            : $season->start_at->copy()->startOfDay();

        $end = $nextSeasonStart
            ? Carbon::parse($nextSeasonStart)->subDay()->endOfDay()
            : $season->end_at->copy()->endOfDay();

        return [$start, $end];
    }

    /** @return int[] */
    private function userRegisteredEventIds(): array
    {
        if (! auth()->check()) {
            return [];
        }

        return Registration::where('user_id', auth()->id())
            ->pluck('event_post_id')
            ->map(fn ($id): int => (int) $id)
            ->toArray();
    }
}
