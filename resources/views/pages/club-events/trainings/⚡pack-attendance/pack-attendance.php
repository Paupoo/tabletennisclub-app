<?php

declare(strict_types=1);

use App\Domains\Trainings\Services\TrainingAttendanceReport;
use App\Livewire\Concerns\ViewsTrainingPack;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * L'onglet Présences d'un pack : la grille membres × séances.
 */
new class extends Component
{
    use ViewsTrainingPack;

    public bool $showAllSessions = false;

    /**
     * La grille membres × séances du pack consulté.
     *
     * Douze séances déjà tenues par défaut : un trimestre tient à l'écran, une
     * saison entière demanderait un scroll horizontal que personne ne lit.
     * « Toute la saison » ajoute aussi celles qui restent à venir.
     *
     * @return array{sessions: list<array<string, mixed>>, members: list<array<string, mixed>>, walkIns: list<array<string, mixed>>, externals: list<array<string, mixed>>}
     */
    #[Computed]
    public function attendanceMatrix(): array
    {
        $pack = $this->selectedPack;

        if (! $pack) {
            return ['sessions' => [], 'members' => [], 'walkIns' => [], 'externals' => []];
        }

        return $this->showAllSessions
            ? app(TrainingAttendanceReport::class)->matrix($pack, PHP_INT_MAX, includeUpcoming: true)
            : app(TrainingAttendanceReport::class)->matrix($pack);
    }
};
