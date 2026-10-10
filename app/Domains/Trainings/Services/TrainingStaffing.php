<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingSessionCancelledForStaffNotification;
use App\Domains\Trainings\Notifications\TrainingSessionHandedOverNotification;
use App\Domains\Trainings\Notifications\TrainingSparringAssignedNotification;
use App\Domains\Trainings\Notifications\TrainingSparringReleasedNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
     * Ajoute un sparring à une seule séance. Un attitré retiré pour cette date
     * y revient tel qu'il était.
     */
    public function addToSession(Training $session, User $sparring, User $actor): void
    {
        $row = DB::table('training_sparrings')->where('training_id', $session->id)->where('user_id', $sparring->id);

        if ($row->exists()) {
            $row->update(['removed_at' => null, 'updated_at' => now()]);
        } else {
            $session->sparrings()->attach($sparring->id, ['source' => 'session']);
        }

        $sparring->notify(new TrainingSparringAssignedNotification(session: $session));
    }

    /**
     * Attitre un sparring au pack : il rejoint chaque séance à venir, sauf
     * celles où une ligne existe déjà (ajout à la main, ou retrait pour la date).
     */
    public function attachToPack(TrainingPack $pack, User $sparring, User $actor): void
    {
        if (! $this->takesSparrings($pack)) {
            throw new \DomainException(__('Free practice has no coach to record a sparring.'));
        }

        $pack->sparrings()->syncWithoutDetaching([$sparring->id]);

        $alreadySet = DB::table('training_sparrings')->where('user_id', $sparring->id)->pluck('training_id');

        $rows = $this->comingSessions($pack)
            ->whereNotIn('id', $alreadySet)
            ->pluck('id')
            ->map(fn (int $trainingId): array => [
                'training_id' => $trainingId,
                'user_id' => $sparring->id,
                'source' => 'pack',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('training_sparrings')->insert($rows->all());

        $sparring->notify(new TrainingSparringAssignedNotification(pack: $pack));
    }

    /**
     * L'encadrement d'une saison : ceux qui ont le rôle Coach, ceux qui
     * encadrent déjà un pack ou une séance de cette saison, et les sparrings
     * attitrés à l'un de ses packs.
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
            ->orWhereIn('users.id', Training::query()->where('season_id', $seasonId)->whereNotNull('trainer_id')->select('trainer_id'))
            ->orWhereIn('users.id', DB::table('training_pack_sparrings')
                ->join('training_packs', 'training_packs.id', '=', 'training_pack_sparrings.training_pack_id')
                ->where('training_packs.season_id', $seasonId)
                ->select('training_pack_sparrings.user_id')));
    }

    /**
     * Retire l'attitré des séances à venir du pack. Le passé reste tel qu'il a
     * été pointé, et une séance où on l'a ajouté à la main le garde.
     */
    public function detachFromPack(TrainingPack $pack, User $sparring, User $actor): void
    {
        $pack->sparrings()->detach($sparring->id);

        DB::table('training_sparrings')
            ->where('user_id', $sparring->id)
            ->where('source', 'pack')
            ->whereIn('training_id', $this->comingSessions($pack)->select('id'))
            ->delete();

        $sparring->notify(new TrainingSparringReleasedNotification(pack: $pack));
    }

    /**
     * Passe au nouveau coach du pack les séances à venir qui suivaient l'ancien.
     */
    public function followPackCoach(TrainingPack $pack, ?int $formerTrainerId): void
    {
        if ($formerTrainerId === $pack->trainer_id) {
            return;
        }

        $this->comingSessions($pack)
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

    /**
     * Prévient l'encadrement d'une séance annulée : ses sparrings, et le coach
     * de la séance s'il n'est pas celui qui l'annule — un remplaçant, quand
     * c'est le comité qui annule.
     */
    public function notifyCancellation(Training $session, User $actor): void
    {
        $staff = $session->sparrings()->get();

        if ($session->trainer_id && $session->trainer_id !== $actor->id) {
            $staff->push($session->trainer);
        }

        $staff->filter()
            ->unique('id')
            ->reject(fn (User $user): bool => $user->id === $actor->id)
            ->each->notify(new TrainingSessionCancelledForStaffNotification($session));
    }

    /**
     * Retire un sparring d'une seule séance. Un attitré laisse une trace, pour
     * que le pack ne le remette pas ; un renfort ponctuel disparaît.
     */
    public function removeFromSession(Training $session, User $sparring, User $actor): void
    {
        $row = DB::table('training_sparrings')->where('training_id', $session->id)->where('user_id', $sparring->id);

        if ((clone $row)->value('source') === 'pack') {
            $row->update(['removed_at' => now(), 'updated_at' => now()]);
        } else {
            $row->delete();
        }

        $sparring->notify(new TrainingSparringReleasedNotification(session: $session));
    }

    /**
     * Un sparring se pointe : sans coach, l'entraînement libre n'en a pas.
     */
    public function takesSparrings(TrainingPack $pack): bool
    {
        return $pack->type !== TrainingType::FREE;
    }

    /**
     * Les séances du pack qui n'ont pas encore commencé : les seules qu'un
     * changement d'encadrement du pack a le droit de toucher.
     *
     * @return HasMany<Training, TrainingPack>
     */
    private function comingSessions(TrainingPack $pack): HasMany
    {
        return $pack->trainings()->where('start', '>=', Carbon::now());
    }
}
