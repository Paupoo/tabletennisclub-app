<?php

declare(strict_types=1);

namespace App\Services\ClubAdmin\Planning;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Shared\Enums\AgeCategoryEnum;
use App\Domains\Trainings\Models\TrainingPlan;
use App\Domains\Trainings\Models\TrainingPlanAssignment;
use App\Domains\Trainings\Models\TrainingPlanPack;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;

/**
 * Exports a {@see TrainingPlan} to a spreadsheet (CSV / ODS / XLSX).
 *
 * One row per assigned member and per pooled member. The season attributes
 * (competitive / can drive / captain / volunteer) come from the member's
 * subscription for the plan's season, eager-loaded once to avoid N+1.
 */
class ExportTrainingPlanService
{
    /** Supported export formats mapped to their PhpSpreadsheet writer type. */
    private const array WRITERS = [
        'csv' => 'Csv',
        'ods' => 'Ods',
        'xlsx' => 'Xlsx',
    ];

    /**
     * Build the spreadsheet for the plan and return the raw binary contents
     * together with a suggested file name.
     *
     * @return array{filename: string, contents: string, mime: string}
     */
    public function export(TrainingPlan $plan, string $format): array
    {
        $format = strtolower(trim($format));

        if (! isset(self::WRITERS[$format])) {
            throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }

        $spreadsheet = $this->buildSpreadsheet($plan);
        $writer = IOFactory::createWriter($spreadsheet, self::WRITERS[$format]);

        $contents = $this->render($writer);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'filename' => $this->fileName($plan, $format),
            'contents' => $contents,
            'mime' => $this->mimeType($format),
        ];
    }

    /**
     * Render a boolean season attribute as a translated yes/no.
     */
    private function boolean(?bool $value): string
    {
        return $value ? __('Yes') : __('No');
    }

    /**
     * Build a spreadsheet with the header row and one row per assignment.
     */
    private function buildSpreadsheet(TrainingPlan $plan): Spreadsheet
    {
        $subscriptions = $this->subscriptionsByUser($plan);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headers = $this->headers();
        $rowIndex = 1;

        foreach ($headers as $columnIndex => $header) {
            $sheet->setCellValue([$columnIndex + 1, $rowIndex], $header);
        }

        $plan->loadMissing(['packs', 'assignments.user']);

        /** @var Collection<int, TrainingPlanAssignment> $assignments */
        $assignments = $plan->assignments
            ->sortBy([
                ['training_plan_pack_id', 'asc'],
                ['position', 'asc'],
            ])
            ->values();

        $packNames = $plan->packs->keyBy('id');

        foreach ($assignments as $assignment) {
            $rowIndex++;
            $values = $this->rowFor($assignment, $packNames, $subscriptions);

            foreach ($values as $columnIndex => $value) {
                $sheet->setCellValueExplicit(
                    [$columnIndex + 1, $rowIndex],
                    $value,
                    DataType::TYPE_STRING,
                );
            }
        }

        return $spreadsheet;
    }

    private function fileName(TrainingPlan $plan, string $format): string
    {
        $slug = Str::slug($plan->name) ?: 'training-plan';

        return "{$slug}.{$format}";
    }

    /**
     * Translated header labels, in column order.
     *
     * @return array<int, string>
     */
    private function headers(): array
    {
        return [
            __('Pack'),
            __('Licence'),
            __('Last name'),
            __('First name'),
            __('Ranking'),
            __('Age category'),
            __('Competitive'),
            __('Can drive'),
            __('Captain'),
            __('Volunteer'),
        ];
    }

    private function mimeType(string $format): string
    {
        return match ($format) {
            'csv' => 'text/csv',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }

    /**
     * Render the writer to an in-memory string.
     */
    private function render(IWriter $writer): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to open a temporary stream for the export.');
        }

        try {
            $writer->save($stream);
            rewind($stream);
            $contents = stream_get_contents($stream);
        } finally {
            fclose($stream);
        }

        return $contents === false ? '' : $contents;
    }

    /**
     * Build the ordered cell values for one assignment row.
     *
     * @param  Collection<int, TrainingPlanPack>  $packNames
     * @param  Collection<int, Subscription>  $subscriptions
     * @return array<int, string>
     */
    private function rowFor(TrainingPlanAssignment $assignment, Collection $packNames, Collection $subscriptions): array
    {
        $user = $assignment->user;
        $pack = $assignment->training_plan_pack_id !== null
            ? $packNames->get($assignment->training_plan_pack_id)
            : null;

        $subscription = $subscriptions->get($user->id);

        $category = $user->birthdate !== null
            ? AgeCategoryEnum::fromBirthdate($user->birthdate)
            : null;

        return [
            $pack?->name ?? __('Pool'),
            (string) ($user->licence ?? ''),
            (string) ($user->last_name ?? ''),
            (string) ($user->first_name ?? ''),
            (string) ($user->ranking ?? ''),
            $category?->getLabel() ?? '',
            $this->boolean($subscription?->is_competitive),
            $this->boolean($subscription?->can_drive),
            $this->boolean($subscription?->wants_to_be_captain),
            $this->boolean($subscription?->volunteer_help),
        ];
    }

    /**
     * Subscriptions of the plan's season keyed by user id (single query).
     *
     * @return Collection<int, Subscription>
     */
    private function subscriptionsByUser(TrainingPlan $plan): Collection
    {
        return Subscription::query()
            ->where('season_id', $plan->season_id)
            ->affiliated()
            ->get()
            ->keyBy('user_id');
    }
}
