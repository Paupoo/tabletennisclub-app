<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lecture du pointage : ce que le comité et les coachs regardent.
 *
 * Le dénominateur est toujours le nombre de séances **pointées**, jamais le
 * nombre de séances tenues. Une séance oubliée mettrait sinon tout un pack à
 * 0 % et se lirait comme une désaffection.
 */
class TrainingAttendanceReport
{
    /** Séances affichées par défaut : un trimestre tient à l'écran, la saison non. */
    private const int DEFAULT_SESSIONS = 12;

    /**
     * La grille membres × séances d'un pack.
     *
     * Une seule lecture répond aux deux questions du comité : une colonne
     * creuse dit que la séance du 12/11 n'a réuni que quatre personnes sur
     * dix-huit, une ligne creuse dit que ce membre paie et ne vient pas.
     *
     * @return array{
     *     sessions: list<array{id: int, date: string, cancelled: bool, counted: bool}>,
     *     members: list<array{id: int, name: string, cells: array<int, string|null>}>,
     *     walkIns: list<array{id: int, name: string, cells: array<int, string|null>}>
     * }
     */
    public function matrix(TrainingPack $pack, int $limit = self::DEFAULT_SESSIONS): array
    {
        $sessions = Training::query()
            ->where('training_pack_id', $pack->id)
            ->orderByDesc('start')
            ->limit($limit)
            ->get()
            ->sortBy('start')
            ->values();

        $members = $pack->trainees()->orderBy('last_name')->orderBy('first_name')->get();

        $statuses = $this->statusesFor($sessions->pluck('id'));

        $memberIds = $members->pluck('id');

        return [
            'sessions' => $sessions->map(fn (Training $session): array => [
                'id' => $session->id,
                'date' => $session->start->toDateString(),
                'cancelled' => $session->isCancelled(),
                'counted' => $session->attendance_taken_at !== null,
                'rate' => $session->attendance_taken_at === null || $session->isCancelled()
                    ? null
                    : $this->rateOf(array_intersect_key(
                        $statuses[$session->id] ?? [],
                        $memberIds->flip()->all(),
                    )),
            ])->all(),
            'walkIns' => $this->walkInRows($sessions, $statuses, $memberIds),
            'members' => $members->map(function ($member) use ($sessions, $statuses): array {
                $cells = $sessions->mapWithKeys(fn (Training $session): array => [
                    $session->id => $this->cellFor($session, $statuses, $member->id),
                ])->all();

                return [
                    'id' => $member->id,
                    'name' => $member->last_name . ' ' . $member->first_name,
                    'cells' => $cells,
                    'rate' => $this->rateOf(array_filter($cells, fn (?string $c): bool => $c !== null)),
                ];
            })->all(),
        ];
    }

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
     * Le statut d'un membre sur une séance, ou `null` si rien n'a été pointé.
     *
     * Une séance annulée n'a pas de case : personne n'y était attendu.
     *
     * @param  array<int, array<int, string>>  $statuses
     */
    private function cellFor(Training $session, array $statuses, int $memberId): ?string
    {
        if ($session->isCancelled() || $session->attendance_taken_at === null) {
            return null;
        }

        return $statuses[$session->id][$memberId] ?? null;
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

    /**
     * Part de `present` dans un lot de statuts, en pourcentage entier.
     *
     * `null` sur un lot vide : afficher 0 % dirait « personne n'est venu »
     * là où la vérité est « on n'en sait rien ».
     *
     * @param  array<int|string, string>  $statuses
     */
    private function rateOf(array $statuses): ?int
    {
        if ($statuses === []) {
            return null;
        }

        $present = count(array_filter($statuses, fn (string $s): bool => $s === 'present'));

        return (int) round(($present / count($statuses)) * 100);
    }

    /**
     * Statuts pointés, indexés par séance puis par membre.
     *
     * Une seule requête pour toute la grille : la lire séance par séance
     * ferait un N+1 proportionnel au produit des deux dimensions.
     *
     * @param  Collection<int, int>  $sessionIds
     * @return array<int, array<int, string>>
     */
    private function statusesFor($sessionIds): array
    {
        if ($sessionIds->isEmpty()) {
            return [];
        }

        $rows = DB::table('training_user')
            ->whereIn('training_id', $sessionIds)
            ->get(['training_id', 'user_id', 'status']);

        $indexed = [];

        foreach ($rows as $row) {
            $indexed[$row->training_id][$row->user_id] = $row->status;
        }

        return $indexed;
    }

    /**
     * Les lignes des présents non inscrits au pack.
     *
     * Tenues à part des inscrits : les mêler à la grille gonflerait le taux
     * de la colonne avec des gens qui n'étaient pas attendus.
     *
     * @param  Collection<int, Training>  $sessions
     * @param  array<int, array<int, string>>  $statuses
     * @param  Collection<int, int>  $memberIds
     * @return list<array{id: int, name: string, cells: array<int, string|null>}>
     */
    private function walkInRows($sessions, array $statuses, $memberIds): array
    {
        $seen = collect($statuses)->flatMap(fn (array $byMember): array => array_keys($byMember))
            ->unique()
            ->diff($memberIds);

        if ($seen->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('id', $seen)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->last_name . ' ' . $user->first_name,
                'cells' => $sessions->mapWithKeys(fn (Training $session): array => [
                    $session->id => $this->cellFor($session, $statuses, $user->id),
                ])->all(),
            ])
            ->values()
            ->all();
    }
}
