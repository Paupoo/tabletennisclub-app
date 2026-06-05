<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Interclub;

use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubResultEnum;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ResultsController extends Controller
{
    public function index(Request $request): View
    {
        $selectedSeason = $request->input('season', '');

        $currentSeason = Season::current();

        $seasons = Season::orderByDesc('start_at')
            ->when(
                $currentSeason,
                fn (Builder $q) => $q->where('start_at', '<', $currentSeason->start_at),
                fn (Builder $q) => $q->where('start_at', '<', now())
            )
            ->limit(5)
            ->get()
            ->when($currentSeason, fn (Collection $coll) => $coll->prepend($currentSeason));

        $season = $selectedSeason
            ? $seasons->firstWhere('name', $selectedSeason) ?? $currentSeason
            : $currentSeason;

        $effectiveSeasonName = $season?->name ?? '';

        $categoryOrder = [
            LeagueCategory::MEN->name => 0,
            LeagueCategory::WOMEN->name => 1,
            LeagueCategory::VETERANS->name => 2,
        ];

        $categoryLabels = [
            LeagueCategory::MEN->name => 'Hommes',
            LeagueCategory::WOMEN->name => 'Dames',
            LeagueCategory::VETERANS->name => 'Vétérans',
        ];

        $teamsByCategory = [];

        if ($season) {
            $grouped = Team::with([
                'league',
                'interclubResults' => fn (Relation $q) => $q->where('season_id', $season->id)->orderBy('match_date'),
            ])
                ->inClub()
                ->where('season_id', $season->id)
                ->get()
                ->sortBy(fn (Team $t) => $categoryOrder[$t->league?->category] ?? 99)
                ->groupBy(fn (Team $t) => $t->league?->category ?? LeagueCategory::MEN->name);

            foreach ($grouped as $catName => $teams) {
                $teamsByCategory[] = [
                    'category' => $catName,
                    'label' => $categoryLabels[$catName] ?? $catName,
                    'teams' => $teams->map(fn (Team $team) => [
                        'name' => 'Équipe ' . $team->name . ($team->league ? ' - Division ' . $team->league->division : ''),
                        'position' => $team->final_position ?? '—',
                        'position_class' => $this->positionClass($team->final_position),
                        'matches' => $team->interclubResults->map(fn (InterclubResult $mr) => [
                            'date' => $mr->is_bye ? 'Bye' : $mr->match_date?->format('d M Y'),
                            'opponent' => $mr->opponent_name ?? 'Bye',
                            'venue' => $mr->is_home ? 'Domicile' : 'Extérieur',
                            'score' => $mr->score ?? ($mr->is_bye ? 'Bye' : '—'),
                            'result' => $this->frenchResult($mr),
                        ])->toArray(),
                        'stats' => $this->buildStats($team->interclubResults),
                    ])->toArray(),
                ];
            }
        }

        return view('public.results', compact('teamsByCategory', 'seasons', 'effectiveSeasonName'));
    }

    /**
     * @param  Collection<int, InterclubResult>  $interclubResults
     * @return array{played: int, wins: int, losses: int, win_rate: int}
     */
    private function buildStats(Collection $interclubResults): array
    {
        $real = $interclubResults->where('is_bye', false)->filter(fn (InterclubResult $mr) => $mr->result !== null);
        $played = $real->count();
        $wins = $real->filter(fn (InterclubResult $mr) => in_array($mr->result, [InterclubResultEnum::WIN, InterclubResultEnum::FORFEIT_WIN]))->count();
        $losses = $real->filter(fn (InterclubResult $mr) => in_array($mr->result, [InterclubResultEnum::LOSS, InterclubResultEnum::FORFEIT_LOSS]))->count();

        return [
            'played' => $played,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $played > 0 ? (int) round($wins / $played * 100) : 0,
        ];
    }

    private function frenchResult(InterclubResult $mr): string
    {
        if ($mr->is_bye) {
            return 'Bye';
        }

        if ($mr->result === null) {
            return '—';
        }

        return match ($mr->result) {
            InterclubResultEnum::WIN => 'Victoire',
            InterclubResultEnum::LOSS => 'Défaite',
            InterclubResultEnum::DRAW => 'Nul',
            InterclubResultEnum::FORFEIT_WIN => 'Forfait Adverse',
            InterclubResultEnum::FORFEIT_LOSS => 'Forfait',
            InterclubResultEnum::WITHDRAWAL => 'Forfait Général',
            InterclubResultEnum::WITHDRAWAL_OPPONENT => 'Forfait Général Adverse',
        };
    }

    private function positionClass(?string $position): string
    {
        if (! $position) {
            return 'bg-gray-100 text-gray-800';
        }

        if (str_contains($position, '1')) {
            return 'bg-yellow-100 text-yellow-800';
        }

        if (str_contains($position, '2') || str_contains($position, '3')) {
            return 'bg-orange-100 text-orange-800';
        }

        return 'bg-gray-100 text-gray-800';
    }
}
