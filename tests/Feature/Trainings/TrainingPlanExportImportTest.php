<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Models\TrainingPlan;
use App\Domains\Trainings\Models\TrainingPlanAssignment;
use App\Domains\Trainings\Models\TrainingPlanPack;
use App\Services\ClubAdmin\Planning\ExportTrainingPlanService;
use App\Services\ClubAdmin\Planning\ImportTrainingPlanService;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Load a binary spreadsheet payload through PhpSpreadsheet and return its rows.
 *
 * @return array<int, array<int, mixed>>
 */
function loadSpreadsheetRows(string $contents, string $extension): array
{
    $path = tempnam(sys_get_temp_dir(), 'plan_export_') . '.' . $extension;
    file_put_contents($path, $contents);

    try {
        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();
    } finally {
        @unlink($path);
    }

    return $rows;
}

beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
});

/**
 * Helper: assigned member with a subscription and a plan-pack assignment.
 */
function assignMemberToPack(TrainingPlan $plan, TrainingPlanPack $pack, array $userAttrs = [], array $subAttrs = []): User
{
    $user = User::factory()->create($userAttrs);
    Subscription::factory()->create(array_merge([
        'user_id' => $user->id,
        'season_id' => $plan->season_id,
        'status' => 'paid',
    ], $subAttrs));
    TrainingPlanAssignment::factory()->for($plan, 'plan')->for($pack, 'pack')->create([
        'user_id' => $user->id,
    ]);

    return $user;
}

describe('export', function (): void {
    it('produces a CSV with the header and one row per assigned and pooled member', function (): void {
        $pack = TrainingPlanPack::factory()->for($this->plan, 'plan')->create(['name' => 'Tuesday Advanced']);

        $assigned = assignMemberToPack($this->plan, $pack, [
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'licence' => 'L12345',
            'ranking' => 'B0',
            'birthdate' => now()->subYears(30),
        ], [
            'is_competitive' => true,
            'can_drive' => true,
            'wants_to_be_captain' => false,
            'volunteer_help' => true,
        ]);

        $pooled = User::factory()->create([
            'first_name' => 'Bob',
            'last_name' => 'Dupont',
            'licence' => 'L99999',
            'ranking' => 'C2',
        ]);
        Subscription::factory()->create([
            'user_id' => $pooled->id,
            'season_id' => $this->season->id,
            'status' => 'paid',
            'is_competitive' => false,
        ]);
        TrainingPlanAssignment::factory()->for($this->plan, 'plan')->inPool()->create([
            'user_id' => $pooled->id,
        ]);

        $result = app(ExportTrainingPlanService::class)->export($this->plan, 'csv');

        expect($result['filename'])->toEndWith('.csv')
            ->and($result['mime'])->toBe('text/csv');

        $rows = loadSpreadsheetRows($result['contents'], 'csv');

        // Header row (translated).
        expect($rows[0])->toBe([
            __('Pack'), __('Licence'), __('Last name'), __('First name'),
            __('Ranking'), __('Age category'), __('Competitive'),
            __('Can drive'), __('Captain'), __('Volunteer'),
        ]);

        // Two data rows.
        expect($rows)->toHaveCount(3);

        $byLicence = collect($rows)->keyBy(1);

        $aliceRow = $byLicence->get('L12345');
        expect($aliceRow[0])->toBe('Tuesday Advanced')
            ->and($aliceRow[2])->toBe('Martin')
            ->and($aliceRow[3])->toBe('Alice')
            ->and($aliceRow[4])->toBe('B0')
            ->and($aliceRow[6])->toBe(__('Yes'))  // competitive
            ->and($aliceRow[7])->toBe(__('Yes'))  // can drive
            ->and($aliceRow[8])->toBe(__('No'))   // captain
            ->and($aliceRow[9])->toBe(__('Yes')); // volunteer

        $bobRow = $byLicence->get('L99999');
        expect($bobRow[0])->toBe(__('Pool'))
            ->and($bobRow[6])->toBe(__('No'));
    });

    it('generates a readable ODS file', function (): void {
        $pack = TrainingPlanPack::factory()->for($this->plan, 'plan')->create(['name' => 'Ods Pack']);
        assignMemberToPack($this->plan, $pack, ['licence' => 'ODS01', 'last_name' => 'OdsMember']);

        $result = app(ExportTrainingPlanService::class)->export($this->plan, 'ods');

        expect($result['filename'])->toEndWith('.ods');

        $rows = loadSpreadsheetRows($result['contents'], 'ods');
        expect($rows[0][0])->toBe(__('Pack'))
            ->and(collect($rows)->pluck(0))->toContain('Ods Pack');
    });

    it('generates a readable XLSX file', function (): void {
        $pack = TrainingPlanPack::factory()->for($this->plan, 'plan')->create(['name' => 'Xlsx Pack']);
        assignMemberToPack($this->plan, $pack, ['licence' => 'XLSX1', 'last_name' => 'XlsxMember']);

        $result = app(ExportTrainingPlanService::class)->export($this->plan, 'xlsx');

        expect($result['filename'])->toEndWith('.xlsx');

        $rows = loadSpreadsheetRows($result['contents'], 'xlsx');
        expect($rows[0][0])->toBe(__('Pack'))
            ->and(collect($rows)->pluck(0))->toContain('Xlsx Pack');
    });

    it('rejects an unsupported format', function (): void {
        app(ExportTrainingPlanService::class)->export($this->plan, 'pdf');
    })->throws(InvalidArgumentException::class);
});

describe('import', function (): void {
    /**
     * Build a CSV string from a header and rows (logical, using translated headers).
     */
    function makeCsv(array $rows): string
    {
        $header = [
            __('Pack'), __('Licence'), __('Last name'), __('First name'),
            __('Ranking'), __('Age category'), __('Competitive'),
            __('Can drive'), __('Captain'), __('Volunteer'),
        ];

        $lines = [implode(',', $header)];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($v): string => '"' . str_replace('"', '""', (string) $v) . '"', $row));
        }

        return implode("\n", $lines);
    }

    it('moves a member to another pack', function (): void {
        $packA = TrainingPlanPack::factory()->for($this->plan, 'plan')->create(['name' => 'Pack A']);
        $packB = TrainingPlanPack::factory()->for($this->plan, 'plan')->create(['name' => 'Pack B']);

        $user = assignMemberToPack($this->plan, $packA, ['licence' => 'MOVE1']);
        $assignment = $this->plan->assignments()->where('user_id', $user->id)->first();
        expect($assignment->training_plan_pack_id)->toBe($packA->id);

        $csv = makeCsv([
            ['Pack B', 'MOVE1', '', '', '', '', '', '', '', ''],
        ]);

        $result = app(ImportTrainingPlanService::class)->import($this->plan, $csv);

        expect($result['updated'])->toBe(1)
            ->and($result['skipped'])->toBe([])
            ->and($result['unmatched'])->toBe([]);
        expect($assignment->fresh()->training_plan_pack_id)->toBe($packB->id);
    });

    it('moves a member to the pool when the pack column says Pool', function (): void {
        $pack = TrainingPlanPack::factory()->for($this->plan, 'plan')->create(['name' => 'Pack A']);
        $user = assignMemberToPack($this->plan, $pack, ['licence' => 'POOL1']);
        $assignment = $this->plan->assignments()->where('user_id', $user->id)->first();

        $csv = makeCsv([[__('Pool'), 'POOL1', '', '', '', '', '', '', '', '']]);

        $result = app(ImportTrainingPlanService::class)->import($this->plan, $csv);

        expect($result['updated'])->toBe(1);
        expect($assignment->fresh()->training_plan_pack_id)->toBeNull();
    });

    it('reports an unknown licence as unmatched', function (): void {
        TrainingPlanPack::factory()->for($this->plan, 'plan')->create(['name' => 'Pack A']);

        $csv = makeCsv([['Pack A', 'GHOST', 'Nobody', 'Here', '', '', '', '', '', '']]);

        $result = app(ImportTrainingPlanService::class)->import($this->plan, $csv);

        expect($result['updated'])->toBe(0)
            ->and($result['unmatched'])->toBe(['GHOST']);
    });

    it('reports an unknown pack as skipped without creating it', function (): void {
        $pack = TrainingPlanPack::factory()->for($this->plan, 'plan')->create(['name' => 'Pack A']);
        $user = assignMemberToPack($this->plan, $pack, ['licence' => 'SKIP1']);

        $csv = makeCsv([['Unknown Pack', 'SKIP1', '', '', '', '', '', '', '', '']]);

        $result = app(ImportTrainingPlanService::class)->import($this->plan, $csv);

        expect($result['skipped'])->toBe(['Unknown Pack']);
        // No new pack created in the plan.
        expect($this->plan->packs()->count())->toBe(1);
        // Member untouched (still in Pack A).
        $assignment = $this->plan->assignments()->where('user_id', $user->id)->first();
        expect($assignment->training_plan_pack_id)->toBe($pack->id);
    });

    it('matches a member by email when no licence is given', function (): void {
        $packA = TrainingPlanPack::factory()->for($this->plan, 'plan')->create(['name' => 'Pack A']);
        $packB = TrainingPlanPack::factory()->for($this->plan, 'plan')->create(['name' => 'Pack B']);

        $user = assignMemberToPack($this->plan, $packA, [
            'licence' => null,
            'email' => 'match@example.test',
        ]);
        $assignment = $this->plan->assignments()->where('user_id', $user->id)->first();

        // Email column is appended to the CSV header explicitly.
        $header = implode(',', [
            __('Pack'), __('Licence'), __('Last name'), __('First name'),
            __('Ranking'), __('Age category'), __('Competitive'),
            __('Can drive'), __('Captain'), __('Volunteer'), 'email',
        ]);
        $csv = $header . "\n" . 'Pack B,,,,,,,,,,match@example.test';

        $result = app(ImportTrainingPlanService::class)->import($this->plan, $csv);

        expect($result['updated'])->toBe(1)
            ->and($result['unmatched'])->toBe([]);
        expect($assignment->fresh()->training_plan_pack_id)->toBe($packB->id);
    });

    it('never touches the real training packs or enrolments', function (): void {
        $realPack = TrainingPack::factory()->create(['season_id' => $this->season->id, 'name' => 'Real Pack', 'is_active' => true]);
        $planPack = TrainingPlanPack::factory()->for($this->plan, 'plan')->fromPack($realPack)->create(['name' => 'Pack A']);
        $otherPlanPack = TrainingPlanPack::factory()->for($this->plan, 'plan')->create(['name' => 'Pack B']);

        $user = assignMemberToPack($this->plan, $planPack, ['licence' => 'REAL1']);

        $csv = makeCsv([['Pack B', 'REAL1', '', '', '', '', '', '', '', '']]);

        app(ImportTrainingPlanService::class)->import($this->plan, $csv);

        // Real pack untouched.
        expect($realPack->fresh()->name)->toBe('Real Pack')
            ->and($realPack->fresh()->is_active)->toBeTrue();
        // No enrolment created on the real pack pivot.
        expect(DB::table('subscription_training_pack')->where('training_pack_id', $realPack->id)->count())->toBe(0);
    });

    it('is idempotent on a round-trip export then import', function (): void {
        $packA = TrainingPlanPack::factory()->for($this->plan, 'plan')->create(['name' => 'Pack A']);
        $packB = TrainingPlanPack::factory()->for($this->plan, 'plan')->create(['name' => 'Pack B']);

        $u1 = assignMemberToPack($this->plan, $packA, ['licence' => 'RT001']);
        $u2 = assignMemberToPack($this->plan, $packB, ['licence' => 'RT002']);
        $pooled = User::factory()->create(['licence' => 'RT003']);
        Subscription::factory()->create(['user_id' => $pooled->id, 'season_id' => $this->season->id, 'status' => 'paid']);
        TrainingPlanAssignment::factory()->for($this->plan, 'plan')->inPool()->create(['user_id' => $pooled->id]);

        $before = $this->plan->assignments()->orderBy('user_id')->pluck('training_plan_pack_id', 'user_id')->toArray();

        $csv = app(ExportTrainingPlanService::class)->export($this->plan, 'csv')['contents'];
        $result = app(ImportTrainingPlanService::class)->import($this->plan, $csv);

        $after = $this->plan->fresh()->assignments()->orderBy('user_id')->pluck('training_plan_pack_id', 'user_id')->toArray();

        expect($after)->toBe($before)
            ->and($result['unmatched'])->toBe([])
            ->and($result['skipped'])->toBe([]);
    });
});
