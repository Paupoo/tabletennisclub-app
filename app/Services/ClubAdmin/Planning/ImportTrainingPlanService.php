<?php

declare(strict_types=1);

namespace App\Services\ClubAdmin\Planning;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\TrainingPlan;
use App\Domains\Trainings\Models\TrainingPlanPack;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Imports a CSV (same columns as the export) into a {@see TrainingPlan},
 * moving members between the plan's packs (and the pool).
 *
 * Member matching: by `licence` first, then by `email` as a fallback. A row
 * whose member cannot be matched is reported as `unmatched`. A row whose pack
 * name is unknown in the plan is reported as `skipped` (import v1 never creates
 * a pack). The import only mutates the plan's assignments — never the real
 * training packs or enrolments (decision #10).
 */
class ImportTrainingPlanService
{
    /**
     * Apply a CSV to the plan.
     *
     * @return array{updated: int, skipped: array<int, string>, unmatched: array<int, string>}
     */
    public function import(TrainingPlan $plan, string $csvContents): array
    {
        $rows = $this->parse($csvContents);

        if ($rows === []) {
            return ['updated' => 0, 'skipped' => [], 'unmatched' => []];
        }

        $header = $this->normaliseHeader(array_shift($rows));

        return DB::transaction(function () use ($plan, $header, $rows): array {
            $packsByName = $this->packsByName($plan);
            $seasonUserIds = $this->seasonUserIds($plan);

            $updated = 0;
            $skipped = [];
            $unmatched = [];

            foreach ($rows as $raw) {
                $row = $this->mapRow($header, $raw);

                if ($this->isBlank($row)) {
                    continue;
                }

                $user = $this->matchMember($row);

                if ($user === null) {
                    $unmatched[] = $this->identifier($row);

                    continue;
                }

                $packName = trim((string) ($row['pack'] ?? ''));
                $isPool = $packName === '' || mb_strtolower($packName) === mb_strtolower(__('Pool'));

                if ($isPool) {
                    $packId = null;
                } else {
                    $pack = $packsByName->get(mb_strtolower($packName));

                    if ($pack === null) {
                        $skipped[] = $packName;

                        continue;
                    }

                    $packId = $pack->id;
                }

                if (! $this->assignMember($plan, $user, $packId, $seasonUserIds)) {
                    $skipped[] = $this->identifier($row);
                }

                $updated++;
            }

            return [
                'updated' => $updated,
                'skipped' => array_values(array_unique($skipped)),
                'unmatched' => array_values(array_unique($unmatched)),
            ];
        });
    }

    /**
     * Update (or create, if registered to the season) the member's assignment
     * within the plan. Returns false if the member could not be assigned
     * (not registered and not yet in the plan).
     *
     * @param  Collection<int, int>  $seasonUserIds
     */
    private function assignMember(TrainingPlan $plan, User $user, ?int $packId, Collection $seasonUserIds): bool
    {
        $assignment = $plan->assignments()->where('user_id', $user->id)->first();

        if ($assignment !== null) {
            $assignment->update(['training_plan_pack_id' => $packId]);

            return true;
        }

        // No assignment yet: only create one if the member is registered to the season.
        if (! $seasonUserIds->contains($user->id)) {
            return false;
        }

        $plan->assignments()->create([
            'training_plan_pack_id' => $packId,
            'user_id' => $user->id,
            'position' => 0,
        ]);

        return true;
    }

    /**
     * Human-readable identifier of a row for the report.
     */
    private function identifier(array $row): string
    {
        $licence = trim((string) ($row['licence'] ?? ''));

        if ($licence !== '') {
            return $licence;
        }

        $email = trim((string) ($row['email'] ?? ''));

        if ($email !== '') {
            return $email;
        }

        return trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    }

    /**
     * A row with no member identifier and no pack is treated as blank.
     */
    private function isBlank(array $row): bool
    {
        return trim((string) ($row['licence'] ?? '')) === ''
            && trim((string) ($row['email'] ?? '')) === ''
            && trim((string) ($row['last_name'] ?? '')) === ''
            && trim((string) ($row['first_name'] ?? '')) === '';
    }

    /**
     * Map a raw row to logical keys using the resolved header.
     *
     * @param  array<string, int>  $header
     * @param  array<int, string>  $raw
     * @return array<string, string>
     */
    private function mapRow(array $header, array $raw): array
    {
        $row = [];

        foreach ($header as $key => $index) {
            $row[$key] = $raw[$index] ?? '';
        }

        return $row;
    }

    /**
     * Match a member by licence first, then by email.
     */
    private function matchMember(array $row): ?User
    {
        $licence = trim((string) ($row['licence'] ?? ''));

        if ($licence !== '') {
            $user = User::query()->where('licence', $licence)->first();

            if ($user !== null) {
                return $user;
            }
        }

        $email = trim((string) ($row['email'] ?? ''));

        if ($email !== '') {
            return User::query()->where('email', $email)->first();
        }

        return null;
    }

    /**
     * Normalise the header into lowercase logical keys mapped to column indexes.
     *
     * @param  array<int, string>  $header
     * @return array<string, int>
     */
    private function normaliseHeader(array $header): array
    {
        $map = [
            mb_strtolower(__('Pack')) => 'pack',
            mb_strtolower(__('Licence')) => 'licence',
            mb_strtolower(__('Last name')) => 'last_name',
            mb_strtolower(__('First name')) => 'first_name',
            mb_strtolower(__('Ranking')) => 'ranking',
            mb_strtolower(__('Age category')) => 'age_category',
            mb_strtolower(__('Competitive')) => 'competitive',
            mb_strtolower(__('Can drive')) => 'can_drive',
            mb_strtolower(__('Captain')) => 'captain',
            mb_strtolower(__('Volunteer')) => 'volunteer',
            'email' => 'email',
        ];

        $resolved = [];

        foreach ($header as $index => $label) {
            $key = $map[mb_strtolower(trim($label))] ?? null;

            if ($key !== null) {
                $resolved[$key] = $index;
            }
        }

        return $resolved;
    }

    /**
     * Plan packs keyed by lowercased name for case-insensitive matching.
     *
     * @return Collection<string, TrainingPlanPack>
     */
    private function packsByName(TrainingPlan $plan): Collection
    {
        return $plan->packs()
            ->get()
            ->keyBy(fn (TrainingPlanPack $pack): string => mb_strtolower(trim($pack->name)));
    }

    /**
     * Parse raw CSV contents into rows via an in-memory stream (no temp file).
     *
     * @return array<int, array<int, string>>
     */
    private function parse(string $csvContents): array
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to open a stream for the import.');
        }

        // Strip a leading UTF-8 BOM so the first header matches cleanly.
        $csvContents = preg_replace('/^\xEF\xBB\xBF/', '', $csvContents) ?? $csvContents;

        $rows = [];

        try {
            fwrite($stream, $csvContents);
            rewind($stream);

            while (($cells = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                // fgetcsv yields [null] for a blank line — skip it.
                if ($cells === [null]) {
                    continue;
                }

                $rows[] = array_map(static fn ($cell): string => (string) ($cell ?? ''), $cells);
            }
        } finally {
            fclose($stream);
        }

        return $rows;
    }

    /**
     * User ids registered to the plan's season.
     *
     * @return Collection<int, int>
     */
    private function seasonUserIds(TrainingPlan $plan): Collection
    {
        return Subscription::query()
            ->where('season_id', $plan->season_id)
            ->distinct()
            ->pluck('user_id');
    }
}
