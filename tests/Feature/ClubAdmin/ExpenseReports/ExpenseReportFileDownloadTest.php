<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Actions\SubmitExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReportFile;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

function storedProof(User $author): ExpenseReportFile
{
    $report = (new SubmitExpenseReport)(
        author: $author,
        category: ExpenseCategory::Other,
        description: 'Scotch',
        amount: 6.8,
        spentOn: Carbon::parse('2026-09-12'),
        refundIban: 'BE68539007547034',
        files: [UploadedFile::fake()->create('ticket.pdf', 20, 'application/pdf')],
    );

    return $report->files->sole();
}

it('serves the proof inline to its author, locked down', function (): void {
    $author = User::factory()->create();
    $proof = storedProof($author);

    $this->actingAs($author)
        ->get(route('admin.expense-reports.file', $proof))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeaderContains('Content-Security-Policy', 'sandbox');
});

it('hands the original over as a file on request', function (): void {
    $author = User::factory()->create();
    $proof = storedProof($author);

    $this->actingAs($author)
        ->get(route('admin.expense-reports.file', $proof) . '?download=1')
        ->assertOk()
        ->assertDownload('ticket.pdf');
});

it('serves it to the treasury readers', function (Role $role): void {
    $proof = storedProof(User::factory()->create());

    $this->actingAs(User::factory()->withRole($role)->create())
        ->get(route('admin.expense-reports.file', $proof))
        ->assertOk();
})->with([Role::COMMITTEE, Role::TREASURY, Role::ACCOUNTS_AUDIT, Role::EXPENSE_REPORTS]);

it('refuses any other member', function (): void {
    $proof = storedProof(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->get(route('admin.expense-reports.file', $proof))
        ->assertForbidden();
});

it('refuses a guest', function (): void {
    $proof = storedProof(User::factory()->create());

    $this->get(route('admin.expense-reports.file', $proof))->assertRedirect(route('login'));
});
