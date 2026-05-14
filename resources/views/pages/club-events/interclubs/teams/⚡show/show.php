<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\Teams\Show;

use App\Enums\LeagueLevel;
use App\Models\ClubEvents\Interclub\Interclub;
use App\Models\ClubEvents\Interclub\Team;
use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    #[Locked]
    public int $teamId;

    public function mount(Team $team): void
    {
        $this->teamId = $team->id;
    }

    public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        $team = Team::with(['league', 'captain', 'users', 'club'])->findOrFail($this->teamId);

        $categoryLabels = [
            'MEN'      => 'Hommes',
            'VETERANS' => 'Vétérans',
            'WOMEN'    => 'Dames',
        ];

        $category    = $categoryLabels[$team->league?->category] ?? ($team->league?->category ?? '—');
        $levelLabels = array_column(LeagueLevel::cases(), 'value', 'name');
        $levelLabel  = $levelLabels[$team->league?->level] ?? $team->league?->level;
        $division    = implode(' – ', array_filter([$levelLabel, $team->league?->division]));

        // Matchs passés (résultats mock tant que le module résultats n'est pas codé)
        $pastInterclubs = Interclub::where(fn ($q) => $q
            ->where('visited_team_id', $team->id)
            ->orWhere('visiting_team_id', $team->id)
        )
            ->where('start_date_time', '<', now())
            ->orderByDesc('start_date_time')
            ->with(['visitedTeam.club', 'visitingTeam.club'])
            ->get();

        // Matchs à venir
        $upcomingInterclubs = Interclub::where(fn ($q) => $q
            ->where('visited_team_id', $team->id)
            ->orWhere('visiting_team_id', $team->id)
        )
            ->where('start_date_time', '>=', now())
            ->orderBy('start_date_time')
            ->with(['visitedTeam.club', 'visitingTeam.club'])
            ->get();

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add('Interclubs', '#')
                ->add('Équipes', route('admin.interclubs.teams'))
                ->current($team->club?->name . ' ' . $team->name)
                ->toArray(),
            'team'               => $team,
            'category'           => $category,
            'division'           => $division ?: '—',
            'pastInterclubs'     => $pastInterclubs,
            'upcomingInterclubs' => $upcomingInterclubs,
        ];
    }
};
