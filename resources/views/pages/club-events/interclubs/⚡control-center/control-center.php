<?php

use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    Use Toast;

    public bool $drawerSelection = false;

    public bool $modalMessage = false;

    public string $search = '';

    public string $captainMessage = '';

    public string $selectedWeek = 'WK13';

    public ?int $selectedTeam = null;

    public bool $filterAlerts = false;

    public function prevWeek(): void
    {
        $current = (int) str_replace('WK', '', $this->selectedWeek);
        if ($current > 1) {
            $this->selectedWeek = 'WK'.($current - 1);
        }
    }

    public function nextWeek(): void
    {
        $current = (int) str_replace('WK', '', $this->selectedWeek);
        if ($current < 22) {
            $this->selectedWeek = 'WK'.($current + 1);
        }
    }

    public function with(): array
    {
        $weekNum = (int) str_replace('WK', '', $this->selectedWeek);

        $headers = [
            ['key' => 'name', 'label' => __('Team'), 'class' => 'w-72'],
            ['key' => 'captain', 'label' => __('Captain')],
            ['key' => 'players', 'label' => __('Headcount'), 'class' => 'text-center'],
            ['key' => 'action', 'label' => ''],
        ];

        // Simulation des équipes basée sur la semaine sélectionnée
        $raw_teams = collect([
            ['id' => 1, 'category' => __('Men'), 'name' => 'Ottignies A', 'div' => 'Div 1', 'captain' => 'Jean D.'],
            ['id' => 2, 'category' => __('Men'), 'name' => 'Ottignies B', 'div' => 'Div 3B', 'captain' => 'Marc D.'],
            ['id' => 3, 'category' => __('Men'), 'name' => 'Ottignies C', 'div' => 'Div 4A', 'captain' => 'Luc L.'],
            ['id' => 4, 'category' => __('Men'), 'name' => 'Ottignies D', 'div' => 'Div 5C', 'captain' => 'Eric P.'],
            ['id' => 5, 'category' => __('Women'), 'name' => 'Ottignies A', 'div' => 'Div 2', 'captain' => 'Marie S.'],
            ['id' => 6, 'category' => __('Women'), 'name' => 'Ottignies B', 'div' => 'Div 3', 'captain' => 'Julie W.'],
        ])->map(function ($team) use ($weekNum) {
            $max = (str_contains($team['category'], 'Women')) ? 3 : 4;

            // Logique de simulation déterministe
            $team['is_home'] = ($team['id'] + $weekNum) % 2 === 0;
            $team['players'] = ($team['id'] * $weekNum) % ($max + 1);
            $team['max_players'] = $max;

            // Statut calculé
            if ($team['players'] === 0) {
                $team['status'] = 'alert';
            } elseif ($team['players'] < $max) {
                $team['status'] = 'pending';
            } else {
                $team['status'] = 'validated';
            }

            // Logistique (Clé et Argent) - Uniquement si Home
            $team['key_holder'] = ($team['is_home'] && $team['players'] > 0) ? $team['captain'] : null;
            $team['bar_manager'] = ($team['is_home'] && $weekNum % 2 === 0 && $team['players'] > 1) ? 'Bénévole '.$team['id'] : null;

            return $team;
        });

        $categories = $raw_teams
            ->filter(fn ($t) => empty($this->search) || str_contains(strtolower($t['name']), strtolower($this->search)))
            ->when($this->selectedTeam, fn ($c) => $c->where('id', $this->selectedTeam))
            ->filter(fn ($t) => ! $this->filterAlerts || $t['status'] === 'alert')
            ->groupBy('category');

        $searchResults = [];
        if (strlen($this->search) >= 2) {
            $searchResults = [
                ['name' => 'Thomas W. (Equipe C)', 'rank' => 'C4', 'matches' => 15, 'winrate' => 70],
                ['name' => 'Nicolas S. (Equipe A)', 'rank' => 'B2', 'matches' => 5, 'winrate' => 90],
            ];
        }

        return [
            'headers' => $headers,
            'categories' => $categories,
            'weeks_options' => collect(range(1, 22))->map(fn ($w) => ['id' => 'WK'.$w, 'name' => __('Week ').$w]),
            'weeks_monitor' => collect(range(1, 20))->map(fn ($w) => [
                'wk' => $w,
                'status' => ($w < $weekNum) ? 'ok' : (($w == $weekNum) ? 'warning' : 'pending'),
            ]),
            'teams_list' => $raw_teams->map(fn ($t) => ['id' => $t['id'], 'name' => $t['name']]),
            'day_responsibilities' => [
                'keys' => ($weekNum % 2 === 0) ? ['Jean D.', 'Luc L.'] : ['Marc D.'],
                'bar' => ($weekNum % 2 === 0) ? 'Aurelien V.' : null,
            ],
            'day_status' => ($weekNum % 2 === 0) ? 'ok' : 'incomplete',
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Interclubs Control Center'))
                ->toArray(),
            'weeks' => [
                ['wk' => 13, 'date' => '05/02', 'opp' => 'Perwez A', 'status' => 'pending', 'is_demo' => true],
                ['wk' => 14, 'date' => '12/02', 'opp' => 'Wavre C', 'status' => 'pending', 'is_demo' => false],
                ['wk' => 15, 'date' => '19/02', 'opp' => 'Logis J', 'status' => 'ready', 'is_demo' => false],
                ['wk' => 16, 'date' => '26/02', 'opp' => 'Auderghem E', 'status' => 'ready', 'is_demo' => false],
                ['wk' => 17, 'date' => '05/03', 'opp' => 'Champ d\'en Haut', 'status' => 'future', 'is_demo' => false],
            ],
            'roster' => [
                ['name' => 'Marc D.', 'rank' => 'B4', 'matches' => 12, 'winrate' => 85, 'available' => 'yes'],
                ['name' => 'Aurelien V.', 'rank' => 'B6', 'matches' => 10, 'winrate' => 60, 'available' => 'yes'],
                ['name' => 'Jean-Paul H.', 'rank' => 'C0', 'matches' => 8, 'winrate' => 45, 'available' => 'maybe'],
                ['name' => 'Luc L.', 'rank' => 'C2', 'matches' => 4, 'winrate' => 25, 'available' => 'no'],
                ['name' => 'Eric P.', 'rank' => 'C2', 'matches' => 2, 'winrate' => 50, 'available' => 'yes'],
            ],
            'searchResults' => $searchResults,
        ];
    }

    // Sélection simulée (WK13)
    public array $selectedPlayers = ['Marc D.', 'Aurelien V.'];

    /**
     * Gère la sélection/désélection d'un joueur
     */
    public function togglePlayer(string $name): void
    {
        if (in_array($name, $this->selectedPlayers)) {
            $this->selectedPlayers = array_diff($this->selectedPlayers, [$name]);
        } else {
            if (count($this->selectedPlayers) < 4) {
                $this->selectedPlayers[] = $name;
            } else {
                $this->warning('Équipe complète', 'Maximum 4 joueurs.', position: 'toast-bottom toast-end');
            }
        }
    }

    /**
     * Déclenché lors du clic sur "Confirmer" dans le drawer
     */
    public function saveSelection(): void
    {
        $this->drawerSelection = false;
        $this->modalMessage = true;
    }

    /**
     * Finalise la convocation après le message du capitaine
     */
    public function confirmAndSend(): void
    {
        $this->modalMessage = false;

        $this->success(
            'Sélection validée !',
            'Les joueurs ont reçu leur convocation.',
            icon: 'o-paper-airplane'
        );

        $this->captainMessage = '';
    }

    public function render(): View
    {
        return $this->view();
    }
};