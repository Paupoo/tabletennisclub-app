<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Qui a encadré quoi sur une période : la base des paiements à venir.
 *
 * Seul le pointage du coach fait foi. Une séance donnée ne compte qu'une fois
 * pointée, une aide de sparring que si le coach l'a confirmée présente. Ce qui
 * est passé sans être pointé reste à part, pour dire que les chiffres ne sont
 * pas définitifs. Une séance annulée ou à venir n'entre nulle part.
 */
class TrainingStaffReport
{
    /**
     * Une ligne par séance d'une personne, de la plus ancienne à la plus récente.
     *
     * @return list<array{date: string, pack: string, role: string, status: string}>
     */
    public function detail(User $user, CarbonInterface $from, CarbonInterface $to, ?int $packId = null): array
    {
        return $this->lines($from, $to, $packId)
            ->where('user_id', $user->id)
            ->map(fn (\stdClass $line): array => [
                'date' => Carbon::parse($line->start)->toDateString(),
                'pack' => (string) $line->pack,
                'role' => $line->role,
                'status' => $line->status,
            ])
            ->values()
            ->all();
    }

    /**
     * Le décompte de la période, une ligne par personne, en CSV ou XLSX. Ce
     * qui est prévu n'y figure pas : le fichier sert à payer. `$scope` nomme
     * ce qu'il couvre (saison, mois, pack) et titre le fichier.
     *
     * @return array{filename: string, contents: string, mime: string}
     */
    public function export(CarbonInterface $from, CarbonInterface $to, string $format, ?int $packId = null, ?string $scope = null): array
    {
        $writers = ['csv' => 'Csv', 'xlsx' => 'Xlsx'];
        $format = strtolower(trim($format));

        if (! isset($writers[$format])) {
            throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Coaching staff'));
        $sheet->fromArray([__('Name'), __('Sessions coached'), __('Sparring presences'), __('Still to record')]);

        foreach ($this->rows($from, $to, $packId) as $line => $row) {
            $sheet->fromArray([$row['name'], $row['coached'], $row['sparred'], $row['pending']], null, 'A' . ($line + 2));
        }

        ob_start();
        IOFactory::createWriter($spreadsheet, $writers[$format])->save('php://output');
        $contents = (string) ob_get_clean();

        $spreadsheet->disconnectWorksheets();

        return [
            'filename' => str(__('Coaching staff') . ' ' . ($scope ?? $from->toDateString() . ' ' . $to->toDateString()))->slug() . ".{$format}",
            'contents' => $contents,
            'mime' => $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * Une ligne par personne qui a encadré sur la période, par nom.
     *
     * @return list<array{user_id: int, name: string, coached: int, sparred: int, pending: int, upcoming: int}>
     */
    public function rows(CarbonInterface $from, CarbonInterface $to, ?int $packId = null): array
    {
        $lines = $this->lines($from, $to, $packId)->groupBy('user_id');

        return User::query()
            ->whereIn('id', $lines->keys())
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get()
            ->map(function (User $user) use ($lines): array {
                $own = $lines->get($user->id, collect());

                return [
                    'user_id' => $user->id,
                    'name' => $user->full_name,
                    'coached' => $own->where('role', 'coach')->where('status', 'counted')->count(),
                    'sparred' => $own->where('role', 'sparring')->where('status', 'present')->count(),
                    'pending' => $own->where('status', 'to_record')->count(),
                    'upcoming' => $own->where('status', 'planned')->count(),
                ];
            })
            ->all();
    }

    /**
     * Les séances données comme coach et les présences attendues comme
     * sparring, sur des séances maintenues.
     *
     * Chaque ligne porte `user_id`, `start`, `pack`, `role` (`coach` | `sparring`)
     * et `status` (`counted` | `present` | `absent` | `to_record` | `planned`).
     *
     * @return Collection<int, \stdClass>
     */
    private function lines(CarbonInterface $from, CarbonInterface $to, ?int $packId): Collection
    {
        $now = Carbon::now()->toDateTimeString();

        $base = fn () => DB::table('trainings')
            ->leftJoin('training_packs', 'training_packs.id', '=', 'trainings.training_pack_id')
            ->where('trainings.status', 'scheduled')
            ->where('trainings.start', '>=', $from->copy()->startOfDay())
            ->where('trainings.start', '<=', $to->copy()->endOfDay())
            ->when($packId, fn ($query) => $query->where('trainings.training_pack_id', $packId));

        // Ce qui n'a pas commencé et n'est pas pointé est prévu : visible, jamais compté.
        $coached = $base()
            ->whereNotNull('trainings.trainer_id')
            ->select(['trainings.id', 'trainings.trainer_id as user_id', 'trainings.start', 'training_packs.name as pack'])
            ->selectRaw("'coach' as role")
            ->selectRaw("CASE WHEN trainings.attendance_taken_at IS NOT NULL THEN 'counted' WHEN trainings.start < ? THEN 'to_record' ELSE 'planned' END as status", [$now])
            ->get();

        $sparred = $base()
            ->join('training_sparrings', 'training_sparrings.training_id', '=', 'trainings.id')
            ->whereNull('training_sparrings.removed_at')
            ->select(['trainings.id', 'training_sparrings.user_id', 'trainings.start', 'training_packs.name as pack'])
            ->selectRaw("'sparring' as role")
            ->selectRaw("CASE WHEN training_sparrings.attendance IS NOT NULL THEN training_sparrings.attendance WHEN trainings.start < ? THEN 'to_record' ELSE 'planned' END as status", [$now])
            ->get();

        return $coached->concat($sparred)
            ->sortBy([['start', 'asc'], ['id', 'asc']])
            ->values();
    }
}
