<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Carbon;

function expenseProofWithFingerprint(ExpenseReport $report, string $fingerprint): void
{
    $report->files()->create([
        'path' => "expense-reports/{$report->id}/x.jpg",
        'original_name' => 'x.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 10,
        'sha256' => $fingerprint,
    ]);
}

it('flags a report of the same member with the same amount within three days', function (): void {
    $member = User::factory()->create();
    $earlier = ExpenseReport::factory()->for($member)->create(['amount' => 42.5, 'spent_on' => '2026-09-12']);
    ExpenseReport::factory()->for($member)->create(['amount' => 42.5, 'spent_on' => '2026-09-20']);
    ExpenseReport::factory()->for($member)->create(['amount' => 40.0, 'spent_on' => '2026-09-12']);
    ExpenseReport::factory()->create(['amount' => 42.5, 'spent_on' => '2026-09-12']);

    $duplicates = ExpenseReport::possibleDuplicatesOf($member->id, 42.5, Carbon::parse('2026-09-15'));

    expect($duplicates->modelKeys())->toBe([$earlier->id]);
});

it('ignores withdrawn and rejected reports, and the report itself', function (): void {
    $member = User::factory()->create();
    ExpenseReport::factory()->for($member)->withdrawn()->create(['amount' => 42.5, 'spent_on' => '2026-09-12']);
    ExpenseReport::factory()->for($member)->rejected()->create(['amount' => 42.5, 'spent_on' => '2026-09-12']);
    $report = ExpenseReport::factory()->for($member)->create(['amount' => 42.5, 'spent_on' => '2026-09-12']);

    expect($report->possibleDuplicates())->toBeEmpty();
});

it('flags the same proof already used on another accepted report, whoever sent it', function (): void {
    $fingerprint = str_repeat('f', 64);
    $accepted = ExpenseReport::factory()->accepted()->create();
    expenseProofWithFingerprint($accepted, $fingerprint);
    $pending = ExpenseReport::factory()->create();
    expenseProofWithFingerprint($pending, str_repeat('0', 64));
    $report = ExpenseReport::factory()->create();
    expenseProofWithFingerprint($report, $fingerprint);

    expect($report->reportsSharingAProof()->modelKeys())->toBe([$accepted->id]);
});
