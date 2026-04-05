<?php

namespace Resources\views\Pages\ClubEvents\Interclubs\Teams;

use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    // Supprime "public Collection $teams" pour laisser getTeamsProperty faire le travail
    public string $search = '';

    public bool $teamModal = false;

    public string $name = '';

    public string $division = '';

    public string $category = '';

    public string $captain = '';

    /**
     * Cette "Computed Property" est accessible via $this->teams dans le render
     */
    public function teams(): Collection
    {
        $data = collect([
            (object) ['id' => 1, 'name' => 'Ottignies-Blocry A', 'division' => 'Régionale 1', 'category' => 'Seniors', 'captain_name' => 'Thomas Leroy', 'rank' => 3, 'nextMatchDate' => now()->addDays(2)],
            (object) ['id' => 2, 'name' => 'Ottignies-Blocry B', 'division' => 'Provinciale 1', 'category' => 'Seniors', 'captain_name' => 'Sophie Martin', 'rank' => 7, 'nextMatchDate' => now()->addDays(5)],
            (object) ['id' => 3, 'name' => 'Ottignies-Blocry C', 'division' => 'Provinciale 2', 'category' => 'Seniors', 'captain_name' => 'Lucas Bernard', 'rank' => 1, 'nextMatchDate' => now()->addDays(1)],
            (object) ['id' => 4, 'name' => 'Ottignies-Blocry D', 'division' => 'Provinciale 3', 'category' => 'Seniors', 'captain_name' => 'Emma Dubois', 'rank' => 5, 'nextMatchDate' => now()->addDays(7)],
            (object) ['id' => 5, 'name' => 'Ottignies-Blocry E', 'division' => 'Provinciale 4', 'category' => 'Seniors', 'captain_name' => 'Nathan Petit', 'rank' => 9, 'nextMatchDate' => now()->addDays(3)],
            (object) ['id' => 6, 'name' => 'Ottignies-Blocry F', 'division' => 'Provinciale 4', 'category' => 'Seniors', 'captain_name' => 'Clara Fontaine', 'rank' => 2, 'nextMatchDate' => now()->addDays(4)],

            (object) ['id' => 7, 'name' => 'Ottignies-Blocry A', 'division' => 'Provinciale 2', 'category' => 'Vétérans', 'captain_name' => 'Michel Renard', 'rank' => 4, 'nextMatchDate' => now()->addDays(6)],
            (object) ['id' => 8, 'name' => 'Ottignies-Blocry B', 'division' => 'Provinciale 3', 'category' => 'Vétérans', 'captain_name' => 'Jacques Moreau', 'rank' => 6, 'nextMatchDate' => now()->addDays(2)],
            (object) ['id' => 9, 'name' => 'Ottignies-Blocry C', 'division' => 'Provinciale 3', 'category' => 'Vétérans', 'captain_name' => 'Pierre Lambert', 'rank' => 8, 'nextMatchDate' => now()->addDays(8)],

            (object) ['id' => 10, 'name' => 'Ottignies-Blocry A', 'division' => 'Provinciale 1', 'category' => 'Dames', 'captain_name' => 'Isabelle Rousseau', 'rank' => 1, 'nextMatchDate' => now()->addDay()],
            (object) ['id' => 11, 'name' => 'Ottignies-Blocry B', 'division' => 'Provinciale 2', 'category' => 'Dames', 'captain_name' => 'Camille Girard', 'rank' => 5, 'nextMatchDate' => now()->addDays(3)],

            (object) ['id' => 12, 'name' => 'Ottignies-Blocry A', 'division' => 'Découverte', 'category' => 'Juniors', 'captain_name' => 'Julie Moreau', 'rank' => 2, 'nextMatchDate' => now()->addDays(2)],
        ]);

        return $data->when($this->search, function ($collection) {
            return $collection->filter(
                fn ($team) => str_contains(strtolower($team->name), strtolower($this->search)) ||
                    str_contains(strtolower($team->captain_name), strtolower($this->search))
            );
        });
    }

    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => 'Lettre'],
            ['key' => 'division', 'label' => 'Division'],
            ['key' => 'category', 'label' => 'Catégorie'],
            ['key' => 'captain_name', 'label' => 'Capitaine'],
        ];
    }

    public function save(): void
    {
        // Validation basique pour le mockup
        if (empty($this->name)) {
            $this->error('La lettre est requise');

            return;
        }

        $this->success("Équipe {$this->name} ajoutée avec succès !");
        $this->teamModal = false;
        $this->reset(['name', 'division', 'category', 'captain']);
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add('Interclubs', '#')
                ->current(__('Our teams'))
                ->toArray(),
            'headers' => $this->headers(),
            'teams' => $this->teams(),
        ];
    }

    public function render(): View
    {
        return $this->view();
    }
};