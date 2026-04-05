<?php

use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    // États des interfaces
    public bool $drawerSelection = false;

    public bool $chatDrawer = false;

    public bool $modalMessage = false;

    public ?string $selectedTeam = null;

    // Données de saisie
    public string $captainMessage = '';

    public string $search = '';

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

    public function with(): array
    {
        // Simulation recherche club
        $searchResults = [];
        if (strlen($this->search) >= 2) {
            $searchResults = [
                ['name' => 'Thomas W. (Equipe C)', 'rank' => 'C4', 'matches' => 15, 'winrate' => 70],
                ['name' => 'Nicolas S. (Equipe A)', 'rank' => 'B2', 'matches' => 5, 'winrate' => 90],
            ];
        }

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Captain Selection'))
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

    public function render(): View
    {
        return $this->view();
    }
};