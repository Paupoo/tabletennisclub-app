<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Services;

use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lecture du pointage : ce que le comité et les coachs regardent.
 *
 * Le dénominateur est toujours le nombre de séances **pointées**, jamais le
 * nombre de séances tenues. Une séance oubliée mettrait sinon tout un pack à
 * 0 % et se lirait comme une désaffection.
 */
class TrainingAttendanceReport
{
    /**
     * Taux de présence d'un membre sur un pack, en pourcentage entier.
     *
     * `null` quand aucune séance n'a été pointée : afficher 0 % voudrait dire
     * « il n'est jamais venu », alors qu'on n'en sait rien.
     */
    public function memberRate(TrainingPack $pack, int $userId): ?int
    {
        $counted = $this->countedSessions($pack)->count();

        if ($counted === 0) {
            return null;
        }

        $present = $this->countedSessions($pack)
            ->whereHas('trainees', fn (Builder $q) => $q
                ->where('users.id', $userId)
                ->where('training_user.status', 'present')
            )
            ->count();

        return (int) round(($present / $counted) * 100);
    }

    /**
     * Les séances du pack dont le pointage a été validé.
     *
     * Une séance annulée n'en fait jamais partie : personne n'y était attendu,
     * elle ne peut pas compter comme une absence.
     *
     * @return Builder<Training>
     */
    private function countedSessions(TrainingPack $pack): Builder
    {
        return Training::query()
            ->where('training_pack_id', $pack->id)
            ->where('status', 'scheduled')
            ->whereNotNull('attendance_taken_at');
    }
}
