<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Actions\SubmitExpenseReport;
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

it('records a submitted expense report with its proofs kept private', function (): void {
    $member = User::factory()->create(['iban' => 'BE68539007547034']);

    $report = (new SubmitExpenseReport)(
        author: $member,
        category: ExpenseCategory::SportsEquipment,
        description: 'Trois boîtes de balles pour l\'école de jeunes',
        amount: 42.5,
        spentOn: Carbon::parse('2026-09-12'),
        refundIban: 'be68 5390 0754 7034',
        files: [
            UploadedFile::fake()->image('ticket.jpg'),
            UploadedFile::fake()->create('extrait.pdf', 120, 'application/pdf'),
        ],
    );

    expect($report->status)->toBe(ExpenseReportStatus::Submitted)
        ->and($report->user_id)->toBe($member->id)
        ->and($report->category)->toBe(ExpenseCategory::SportsEquipment)
        ->and($report->amount)->toBe(42.5)
        ->and($report->accepted_amount)->toBeNull()
        ->and($report->spent_on->toDateString())->toBe('2026-09-12')
        ->and($report->refund_iban)->toBe('BE68539007547034')
        ->and($report->files)->toHaveCount(2);

    $proof = $report->files->firstWhere('original_name', 'ticket.jpg');

    expect($proof->sha256)->toHaveLength(64);
    Storage::disk('local')->assertExists($proof->path);
});

it('refuses a member whose birthdate is unknown', function (): void {
    $member = User::factory()->create(['birthdate' => null]);

    (new SubmitExpenseReport)(
        author: $member,
        category: ExpenseCategory::Other,
        description: 'Rouleaux de scotch',
        amount: 6.8,
        spentOn: Carbon::parse('2026-09-12'),
        refundIban: 'BE68539007547034',
        files: [UploadedFile::fake()->image('ticket.jpg')],
    );
})->throws(DomainException::class);

it('refuses a minor', function (): void {
    $member = User::factory()->minor()->create();

    (new SubmitExpenseReport)(
        author: $member,
        category: ExpenseCategory::Other,
        description: 'Rouleaux de scotch',
        amount: 6.8,
        spentOn: Carbon::parse('2026-09-12'),
        refundIban: 'BE68539007547034',
        files: [UploadedFile::fake()->image('ticket.jpg')],
    );
})->throws(DomainException::class);

it('refuses a report without any proof', function (): void {
    $member = User::factory()->create();

    (new SubmitExpenseReport)(
        author: $member,
        category: ExpenseCategory::Other,
        description: 'Rouleaux de scotch',
        amount: 6.8,
        spentOn: Carbon::parse('2026-09-12'),
        refundIban: 'BE68539007547034',
        files: [],
    );
})->throws(DomainException::class);

it('remembers the rejected report it was resumed from', function (): void {
    $member = User::factory()->create();
    $rejected = ExpenseReport::factory()->for($member)->rejected()->create();

    $report = (new SubmitExpenseReport)(
        author: $member,
        category: ExpenseCategory::Other,
        description: 'Rouleaux de scotch',
        amount: 6.8,
        spentOn: Carbon::parse('2026-09-12'),
        refundIban: 'BE68539007547034',
        files: [UploadedFile::fake()->image('ticket.jpg')],
        resumedFrom: $rejected,
    );

    expect($report->resumed_from_id)->toBe($rejected->id);
});
