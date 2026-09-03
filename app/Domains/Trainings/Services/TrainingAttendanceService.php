<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\Training;

/**
 * Écriture du pointage d'une séance.
 *
 * Le pivot `training_user` porte le statut vu par le coach ; la séance porte la
 * signature du pointage. Les deux ensemble permettent de distinguer « personne
 * n'est venu » de « personne n'a pointé » — sans quoi une séance oubliée ferait
 * tomber tout un pack à 0 % de présence.
 */
class TrainingAttendanceService
{
    /**
     * Note le passage d'un membre à une séance.
     *
     * Idempotent : repointer quelqu'un remplace son statut au lieu d'empiler
     * une seconde ligne.
     */
    public function record(Training $session, User $member, string $status): void
    {
        if ($session->trainees()->where('user_id', $member->id)->exists()) {
            $session->trainees()->updateExistingPivot($member->id, ['status' => $status]);

            return;
        }

        $session->trainees()->attach($member->id, ['status' => $status]);
    }

    /**
     * Clôt le pointage d'une séance.
     *
     * Les inscrits que le coach n'a pas touchés sont écrits `absent` pour de
     * bon : c'est ce qui permet à une case vide de vouloir dire « non pointé »
     * et rien d'autre. La signature est réécrite à chaque passage, si bien
     * qu'une correction tardive de la délégation laisse sa trace sans qu'il
     * faille une table d'audit.
     */
    public function validate(Training $session, User $by): void
    {
        if ($session->isCancelled()) {
            throw new \DomainException(__('A cancelled session cannot be counted.'));
        }

        $alreadySeen = $session->trainees()->pluck('users.id');

        $untouched = $session->trainingPack
            ? $session->trainingPack->trainees()->whereNotIn('users.id', $alreadySeen)->pluck('users.id')
            : collect();

        foreach ($untouched as $memberId) {
            $session->trainees()->attach($memberId, ['status' => 'absent']);
        }

        $session->update([
            'attendance_taken_at' => now(),
            'attendance_taken_by' => $by->id,
        ]);
    }
}
