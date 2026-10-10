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
    public function detail(User $user, CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->lines($from, $to)
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
     * Le décompte de la période, une ligne par personne, en CSV ou XLSX.
     *
     * @return array{filename: string, contents: string, mime: string}
     */
    public function export(CarbonInterface $from, CarbonInterface $to, string $format): array
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

        foreach ($this->rows($from, $to) as $line => $row) {
            $sheet->fromArray([$row['name'], $row['coached'], $row['sparred'], $row['pending']], null, 'A' . ($line + 2));
        }

        ob_start();
        IOFactory::createWriter($spreadsheet, $writers[$format])->save('php://output');
        $contents = (string) ob_get_clean();

        $spreadsheet->disconnectWorksheets();

        return [
            'filename' => str(__('Coaching staff') . ' ' . $from->toDateString() . ' ' . $to->toDateString())->slug() . ".{$format}",
            'contents' => $contents,
            'mime' => $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * Une ligne par personne qui a encadré sur la période, par nom.
     *
     * @return list<array{user_id: int, name: string, coached: int, sparred: int, pending: int}>
     */
    public function rows(CarbonInterface $from, CarbonInterface $to): array
    {
        $lines = $this->lines($from, $to)->groupBy('user_id');

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
                ];
            })
            ->all();
    }

    /**
     * Les séances données comme coach et les présences attendues comme
     * sparring, sur des séances maintenues et déjà commencées.
     *
     * Chaque ligne porte `user_id`, `start`, `pack`, `role` (`coach` | `sparring`)
     * et `status` (`counted` | `present` | `absent` | `to_record`).
     *
     * @return Collection<int, \stdClass>
     */
    private function lines(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $base = fn () => DB::table('trainings')
            ->leftJoin('training_packs', 'training_packs.id', '=', 'trainings.training_pack_id')
            ->where('trainings.status', 'scheduled')
            ->where('trainings.start', '>=', $from->copy()->startOfDay())
            ->where('trainings.start', '<=', $to->copy()->endOfDay())
            ->where(fn ($query) => $query
                ->where('trainings.start', '<', Carbon::now())
                ->orWhereNotNull('trainings.attendance_taken_at'));

        $coached = $base()
            ->whereNotNull('trainings.trainer_id')
            ->get([
                'trainings.id', 'trainings.trainer_id as user_id', 'trainings.start', 'training_packs.name as pack',
                DB::raw("'coach' as role"),
                DB::raw("CASE WHEN trainings.attendance_taken_at IS NULL THEN 'to_record' ELSE 'counted' END as status"),
            ]);

        $sparred = $base()
            ->join('training_sparrings', 'training_sparrings.training_id', '=', 'trainings.id')
            ->whereNull('training_sparrings.removed_at')
            ->get([
                'trainings.id', 'training_sparrings.user_id', 'trainings.start', 'training_packs.name as pack',
                DB::raw("'sparring' as role"),
                DB::raw("COALESCE(training_sparrings.attendance, 'to_record') as status"),
            ]);

        return $coached->concat($sparred)
            ->sortBy([['start', 'asc'], ['id', 'asc']])
            ->values();
    }
}
