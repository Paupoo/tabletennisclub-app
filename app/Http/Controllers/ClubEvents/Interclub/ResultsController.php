<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Interclub;

use App\Enums\InterclubResult;
use App\Enums\LeagueCategory;
use App\Http\Controllers\Controller;
use App\Models\ClubEvents\Interclub\MatchResult;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ResultsController extends Controller
{
    public function index(Request $request): View
    {
        $selectedSeason = $request->input('season', '');

        $seasons = Season::orderByDesc('start_at')->pluck('name')->toArray();

        $season = $selectedSeason
            ? Season::where('name', 'like', "%{$selectedSeason}%")->first()
            : Season::current();

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
                'matchResults' => fn ($q) => $q->where('season_id', $season->id)->orderBy('match_date'),
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
                        'matches' => $team->matchResults->map(fn (MatchResult $mr) => [
                            'date' => $mr->is_bye ? 'Bye' : $mr->match_date?->format('d M Y'),
                            'opponent' => $mr->opponent_name ?? 'Bye',
                            'venue' => $mr->is_home ? 'Domicile' : 'Extérieur',
                            'score' => $mr->score ?? ($mr->is_bye ? 'Bye' : '—'),
                            'result' => $this->frenchResult($mr),
                        ])->toArray(),
                        'stats' => $this->buildStats($team->matchResults),
                    ])->toArray(),
                ];
            }
        }

        return View('public.results', compact('teamsByCategory', 'seasons', 'selectedSeason'));
    }

    /**
     * @param  Collection<int, MatchResult>  $matchResults
     * @return array{played: int, wins: int, losses: int, win_rate: int}
     */
    private function buildStats(Collection $matchResults): array
    {
        $real = $matchResults->where('is_bye', false)->filter(fn ($mr) => $mr->result !== null);
        $played = $real->count();
        $wins = $real->filter(fn ($mr) => in_array($mr->result, [InterclubResult::WIN, InterclubResult::FORFEIT_WIN]))->count();
        $losses = $real->filter(fn ($mr) => in_array($mr->result, [InterclubResult::LOSS, InterclubResult::FORFEIT_LOSS]))->count();

        return [
            'played' => $played,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $played > 0 ? (int) round($wins / $played * 100) : 0,
        ];
    }

    private function frenchResult(MatchResult $mr): string
    {
        if ($mr->is_bye) {
            return 'Bye';
        }

        if ($mr->result === null) {
            return '—';
        }

        return match ($mr->result) {
            InterclubResult::WIN => 'Victoire',
            InterclubResult::LOSS => 'Défaite',
            InterclubResult::DRAW => 'Nul',
            InterclubResult::FORFEIT_WIN => 'Forfait Adverse',
            InterclubResult::FORFEIT_LOSS => 'Forfait',
            InterclubResult::WITHDRAWAL => 'Forfait Général',
            InterclubResult::WITHDRAWAL_OPPONENT => 'Forfait Général Adverse',
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
