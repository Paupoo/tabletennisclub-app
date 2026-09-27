<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Communications\Services;

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\AudienceActivityKind;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Trainings\Models\TrainingPack;

/**
 * The activities a message can be aimed at, for the picker.
 *
 * Wider than what can still be joined: a tournament whose registrations have
 * closed is exactly the one whose players need to hear about a change of time.
 * Cancelled events and past seasons are left out.
 */
class AudienceActivityOptions
{
    /** @return list<array{id: int, name: string}> */
    public function for(AudienceActivityKind $kind): array
    {
        $seasonId = Season::current()?->id;

        $options = match ($kind) {
            AudienceActivityKind::Tournament => Tournament::query()
                ->where('status', '!=', TournamentStatusEnum::CANCELLED)
                ->where(fn ($recent) => $recent->whereNull('start_date')->orWhere('start_date', '>=', now()->subMonths(3)))
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Tournament $tournament): array => [
                    'id' => $tournament->id,
                    'name' => $tournament->name . ($tournament->start_date ? ' — ' . $tournament->start_date->format('d/m/Y') : ''),
                ]),
            AudienceActivityKind::TrainingPack => TrainingPack::query()
                ->where('season_id', $seasonId)
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (TrainingPack $pack): array => ['id' => $pack->id, 'name' => $pack->name]),
            AudienceActivityKind::Meeting => Meeting::query()
                ->where('status', '!=', MeetingStatusEnum::CANCELLED)
                ->where(fn ($recent) => $recent->whereNull('scheduled_at')->orWhere('scheduled_at', '>=', now()->subMonths(3)))
                ->orderByDesc('scheduled_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Meeting $meeting): array => [
                    'id' => $meeting->id,
                    'name' => $meeting->title . ($meeting->scheduled_at ? ' — ' . $meeting->scheduled_at->format('d/m/Y') : ''),
                ]),
            // The table holds the whole league, opponents included: our own
            // teams only, and named so that the men's A and the veterans' A
            // are told apart.
            AudienceActivityKind::Team => Team::query()
                ->with('league')
                ->where('season_id', $seasonId)
                ->where('club_id', Club::own()?->id)
                ->get()
                ->sortBy([
                    fn (Team $a, Team $b): int => (LeagueCategory::fromName($a->league?->category)?->sortOrder() ?? 99)
                        <=> (LeagueCategory::fromName($b->league?->category)?->sortOrder() ?? 99),
                    ['name', 'asc'],
                    ['id', 'asc'],
                ])
                ->map(fn (Team $team): array => ['id' => $team->id, 'name' => $this->teamName($team)]),
        };

        return $options->values()->all();
    }

    /** "Team A — Veterans · 3B" */
    private function teamName(Team $team): string
    {
        $details = array_filter([
            LeagueCategory::fromName($team->league?->category)?->label(),
            $team->league?->division,
        ]);

        return __('Team :name', ['name' => $team->name]) . ($details !== [] ? ' — ' . implode(' · ', $details) : '');
    }
}
