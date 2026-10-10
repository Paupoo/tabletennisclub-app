<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingSessionHandedOverNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Qui encadre chaque séance d'un pack.
 *
 * Une séance passée a été donnée par quelqu'un : c'est un fait, et il servira
 * à payer. Une séance confiée à un remplaçant porte un choix fait à la main.
 * Aucun changement apporté au pack ne réécrit l'un ou l'autre : seules les
 * séances à venir restées sur l'encadrement du pack le suivent.
 */
class TrainingStaffing
{
    /**
     * L'encadrement d'une saison : ceux qui ont le rôle Coach, et ceux qui
     * encadrent déjà un pack ou une séance de cette saison.
     *
     * C'est parmi eux qu'un coach choisit son remplaçant : le remplaçant
     * reçoit les coordonnées des inscrits, le choix ne peut pas être ouvert à
     * tout le club. Le comité, lui, peut confier une séance à n'importe qui.
     *
     * @return Builder<User>
     */
    public function coachingStaff(int $seasonId): Builder
    {
        return User::query()->where(fn (Builder $query) => $query
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', Role::COACH->value))
            ->orWhereIn('users.id', TrainingPack::query()->where('season_id', $seasonId)->whereNotNull('trainer_id')->select('trainer_id'))
            ->orWhereIn('users.id', Training::query()->where('season_id', $seasonId)->whereNotNull('trainer_id')->select('trainer_id')));
    }

    /**
     * Passe au nouveau coach du pack les séances à venir qui suivaient l'ancien.
     */
    public function followPackCoach(TrainingPack $pack, ?int $formerTrainerId): void
    {
        if ($formerTrainerId === $pack->trainer_id) {
            return;
        }

        $pack->trainings()
            ->where('start', '>=', Carbon::now())
            ->where(fn ($query) => $formerTrainerId === null
                ? $query->whereNull('trainer_id')
                : $query->where('trainer_id', $formerTrainerId))
            ->update(['trainer_id' => $pack->trainer_id]);
    }

    /**
     * Confie une seule séance à un autre coach, le pack gardant son titulaire.
     *
     * Le remplaçant en devient le coach à part entière pour cette date : il
     * pointe, il joint les inscrits, et c'est lui qui l'a donnée.
     */
    public function handOverSession(Training $session, User $coach, User $actor): void
    {
        $session->update(['trainer_id' => $coach->id]);

        activity()
            ->causedBy($actor)
            ->performedOn($session)
            ->event('training_session_handed_over')
            ->withProperties(['coach_id' => $coach->id])
            ->log('training_session_handed_over');

        $coach->notify(new TrainingSessionHandedOverNotification($session));
    }
}
