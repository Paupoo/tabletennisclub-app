<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Actions\SubmitExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Actions\UpdateExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Actions\WithdrawExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

it('lets the member withdraw a report still in progress', function (): void {
    $report = ExpenseReport::factory()->create();

    (new WithdrawExpenseReport)($report);

    expect($report->refresh()->status)->toBe(ExpenseReportStatus::Withdrawn);
});

it('refuses to withdraw a decided report', function (string $state): void {
    $report = ExpenseReport::factory()->{$state}()->create();

    (new WithdrawExpenseReport)($report);
})->with(['accepted', 'rejected', 'withdrawn'])->throws(DomainException::class);

it('lets the member correct a report in progress, adding and removing proofs', function (): void {
    $member = User::factory()->create();
    $report = (new SubmitExpenseReport)(
        author: $member,
        category: ExpenseCategory::Other,
        description: 'Scotch',
        amount: 6.8,
        spentOn: Carbon::parse('2026-09-12'),
        refundIban: 'BE68539007547034',
        files: [UploadedFile::fake()->image('flou.jpg'), UploadedFile::fake()->image('ticket.jpg')],
    );
    $blurred = $report->files->firstWhere('original_name', 'flou.jpg');

    (new UpdateExpenseReport)(
        report: $report,
        category: ExpenseCategory::Administrative,
        description: 'Rouleaux de scotch',
        amount: 7.8,
        spentOn: Carbon::parse('2026-09-13'),
        refundIban: 'BE71096123456769',
        newFiles: [UploadedFile::fake()->image('net.jpg')],
        removedFileIds: [$blurred->id],
    );

    $report->refresh();

    expect($report->category)->toBe(ExpenseCategory::Administrative)
        ->and($report->description)->toBe('Rouleaux de scotch')
        ->and($report->amount)->toBe(7.8)
        ->and($report->spent_on->toDateString())->toBe('2026-09-13')
        ->and($report->refund_iban)->toBe('BE71096123456769')
        ->and($report->files->pluck('original_name')->sort()->values()->all())->toBe(['net.jpg', 'ticket.jpg']);

    Storage::disk('local')->assertMissing($blurred->path);
});

it('never leaves a report without any proof', function (): void {
    $report = ExpenseReport::factory()->create();
    $file = $report->files()->create(['path' => 'x', 'original_name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'sha256' => str_repeat('a', 64)]);

    (new UpdateExpenseReport)(
        report: $report,
        category: $report->category,
        description: $report->description,
        amount: $report->amount,
        spentOn: $report->spent_on,
        refundIban: $report->refund_iban,
        newFiles: [],
        removedFileIds: [$file->id],
    );
})->throws(DomainException::class);

it('refuses to change a decided report', function (): void {
    $report = ExpenseReport::factory()->accepted()->create();

    (new UpdateExpenseReport)(
        report: $report,
        category: $report->category,
        description: 'Autre chose',
        amount: $report->amount,
        spentOn: $report->spent_on,
        refundIban: $report->refund_iban,
    );
})->throws(DomainException::class);
