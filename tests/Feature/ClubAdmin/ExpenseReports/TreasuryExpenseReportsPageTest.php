<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\ExpenseReports\Actions\AcceptExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

const TREASURY_EXPENSES = 'pages::club-admin.treasury.expense-reports';

beforeEach(function (): void {
    Notification::fake();
});

function expenseTreasurer(): User
{
    return User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create();
}

/** A report accepted by someone else and paid by a debit dated $date. */
function paidExpenseReport(string $date, array $attributes = []): ExpenseReport
{
    $report = ExpenseReport::factory()->create($attributes);
    (new AcceptExpenseReport)($report, User::factory()->create());
    $refund = $report->refresh()->refund;
    $debit = Transaction::create([
        'date' => $date,
        'description' => 'VIREMENT EUROPEEN',
        'amount' => -$refund->amount_due,
        'counterparty_name' => 'Membre',
    ]);
    (new AllocateTransactionAction)($debit, [$refund->id => $refund->amount_due]);

    return $report->refresh();
}

describe('who reaches the page', function (): void {
    it('opens to the treasury', function (): void {
        $this->actingAs(expenseTreasurer())
            ->get(route('admin.treasury.expense-reports'))
            ->assertOk()
            ->assertSee(__('Expense reports'));
    });

    it('opens read-only to the committee', function (): void {
        $report = ExpenseReport::factory()->create();

        Livewire::actingAs(User::factory()->isCommitteeMember()->create())
            ->test(TREASURY_EXPENSES)
            ->call('show', $report->id)
            ->assertSee($report->description)
            ->assertDontSeeHtml('wire:click="openAccept"')
            ->assertDontSeeHtml('wire:click="openReject"')
            ->call('openAccept')
            ->assertForbidden();
    });

    it('is closed to a plain member', function (): void {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.treasury.expense-reports'))
            ->assertForbidden();
    });

    it('does not exist when the feature is off', function (): void {
        config(['features.expense_reports' => false]);

        $this->actingAs(expenseTreasurer())
            ->get(route('admin.treasury.expense-reports'))
            ->assertNotFound();
    });

    it('shows the treasury menu entry to the readers', function (): void {
        $treasurer = expenseTreasurer();
        $this->actingAs($treasurer);

        $this->blade('<x-admin.navigation :user="$user" />', ['user' => $treasurer])
            ->assertSee(route('admin.treasury.expense-reports'));
    });
});

describe('the list', function (): void {
    it('opens on what is left to decide', function (): void {
        ExpenseReport::factory()->create(['description' => 'Balles à traiter']);
        ExpenseReport::factory()->rejected()->create(['description' => 'Scotch refusé']);

        Livewire::actingAs(expenseTreasurer())
            ->test(TREASURY_EXPENSES)
            ->assertSet('statusFilter', 'submitted')
            ->assertSee('Balles à traiter')
            ->assertDontSee('Scotch refusé');
    });

    it('tells accepted reports from paid ones', function (): void {
        $accepted = ExpenseReport::factory()->create(['description' => 'Accepté pas payé']);
        (new AcceptExpenseReport)($accepted, User::factory()->create());
        paidExpenseReport('2026-03-10', ['description' => 'Déjà payé']);

        Livewire::actingAs(expenseTreasurer())
            ->test(TREASURY_EXPENSES)
            ->set('statusFilter', 'accepted')
            ->assertSee('Accepté pas payé')
            ->assertDontSee('Déjà payé')
            ->set('statusFilter', 'paid')
            ->assertSee('Déjà payé')
            ->assertDontSee('Accepté pas payé');
    });

    it('filters on the financial year the money left in', function (): void {
        paidExpenseReport('2025-12-30', ['description' => 'Payé fin 2025', 'spent_on' => '2025-12-01']);
        paidExpenseReport('2026-01-10', ['description' => 'Payé début 2026', 'spent_on' => '2025-12-28']);

        Livewire::actingAs(expenseTreasurer())
            ->test(TREASURY_EXPENSES)
            ->set('statusFilter', 'all')
            ->set('fiscalYear', 2026)
            ->assertSee('Payé début 2026')
            ->assertDontSee('Payé fin 2025');
    });

    it('filters on the member, the nature and what is still to archive', function (): void {
        $member = User::factory()->create(['first_name' => 'Zébulon', 'last_name' => 'Trésor']);
        ExpenseReport::factory()->for($member)->create(['description' => 'Parking Zébulon', 'category' => ExpenseCategory::Travel]);
        ExpenseReport::factory()->create(['description' => 'Balles autre', 'category' => ExpenseCategory::SportsEquipment]);
        paidExpenseReport('2026-03-10', ['description' => 'Archivée', 'archived_at' => now()]);
        paidExpenseReport('2026-03-11', ['description' => 'Pas archivée']);

        $page = Livewire::actingAs(expenseTreasurer())->test(TREASURY_EXPENSES);

        $page->set('search', 'Zébulon')->assertSee('Parking Zébulon')->assertDontSee('Balles autre')
            ->set('search', '')
            ->set('categoryFilter', 'sports_equipment')->assertSee('Balles autre')->assertDontSee('Parking Zébulon')
            ->set('categoryFilter', '')
            ->set('statusFilter', 'paid')
            ->set('unarchivedOnly', true)->assertSee('Pas archivée')->assertDontSee('>Archivée<', false);
    });
});

describe('deciding', function (): void {
    it('accepts the declared amount from the drawer', function (): void {
        $report = ExpenseReport::factory()->create(['amount' => 42.5]);

        Livewire::actingAs(expenseTreasurer())
            ->test(TREASURY_EXPENSES)
            ->call('show', $report->id)
            ->assertSeeHtml('wire:click="openAccept"')
            ->call('openAccept')
            ->assertSet('acceptedAmount', '42.50')
            ->call('confirmAccept')
            ->assertHasNoErrors();

        expect($report->refresh()->status)->toBe(ExpenseReportStatus::Accepted)
            ->and($report->refund->amount_due)->toBe(42.5);
    });

    it('asks why when accepting less, and never more', function (): void {
        $report = ExpenseReport::factory()->create(['amount' => 42.5]);

        Livewire::actingAs(expenseTreasurer())
            ->test(TREASURY_EXPENSES)
            ->call('show', $report->id)
            ->call('openAccept')
            ->set('acceptedAmount', '34,50')
            ->call('confirmAccept')
            ->assertHasErrors(['decisionReason'])
            ->set('acceptedAmount', '50')
            ->set('decisionReason', 'Faute de frappe')
            ->call('confirmAccept')
            ->assertHasErrors(['acceptedAmount'])
            ->set('acceptedAmount', '34.50')
            ->call('confirmAccept')
            ->assertHasNoErrors();

        expect($report->refresh()->accepted_amount)->toBe(34.5)
            ->and($report->decision_reason)->toBe('Faute de frappe');
    });

    it('rejects with a reason', function (): void {
        $report = ExpenseReport::factory()->create();

        Livewire::actingAs(expenseTreasurer())
            ->test(TREASURY_EXPENSES)
            ->call('show', $report->id)
            ->call('openReject')
            ->call('confirmReject')
            ->assertHasErrors(['decisionReason'])
            ->set('decisionReason', 'Ticket illisible')
            ->call('confirmReject')
            ->assertHasNoErrors();

        expect($report->refresh()->status)->toBe(ExpenseReportStatus::Rejected);
    });

    it('lets nobody decide on their own report, and says who should', function (): void {
        $treasurer = expenseTreasurer();
        $report = ExpenseReport::factory()->for($treasurer)->create();

        Livewire::actingAs($treasurer)
            ->test(TREASURY_EXPENSES)
            ->call('show', $report->id)
            ->assertSee(__('To be decided by another member of the treasury.'))
            ->call('openAccept')
            ->assertForbidden();
    });

    it('undoes an acceptance while nothing was paid', function (): void {
        $report = ExpenseReport::factory()->create();
        (new AcceptExpenseReport)($report, User::factory()->create());

        Livewire::actingAs(expenseTreasurer())
            ->test(TREASURY_EXPENSES)
            ->set('statusFilter', 'accepted')
            ->call('show', $report->id)
            ->call('cancelAcceptance');

        expect($report->refresh()->status)->toBe(ExpenseReportStatus::Submitted);
    });

    it('flags a likely duplicate and a proof already used', function (): void {
        $member = User::factory()->create();
        $first = ExpenseReport::factory()->for($member)->accepted()->create(['amount' => 42.5, 'spent_on' => '2026-09-12']);
        $first->files()->create(['path' => 'a', 'original_name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'sha256' => str_repeat('c', 64)]);
        $second = ExpenseReport::factory()->for($member)->create(['amount' => 42.5, 'spent_on' => '2026-09-13']);
        $second->files()->create(['path' => 'b', 'original_name' => 'b.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'sha256' => str_repeat('c', 64)]);

        Livewire::actingAs(expenseTreasurer())
            ->test(TREASURY_EXPENSES)
            ->call('show', $second->id)
            ->assertSee(__('Possible duplicate of report #:id', ['id' => $first->id]))
            ->assertSee(__('The same file was already used on report #:id', ['id' => $first->id]));
    });
});

describe('what the drawer shows', function (): void {
    it('shows the proofs inline', function (): void {
        $report = ExpenseReport::factory()->create();
        $image = $report->files()->create(['path' => 'a', 'original_name' => 'ticket.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'sha256' => str_repeat('a', 64)]);
        $pdf = $report->files()->create(['path' => 'b', 'original_name' => 'extrait.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'sha256' => str_repeat('b', 64)]);

        Livewire::actingAs(expenseTreasurer())
            ->test(TREASURY_EXPENSES)
            ->call('show', $report->id)
            ->assertSeeHtml('<img')
            ->assertSeeHtml('<iframe')
            ->assertSee(route('admin.expense-reports.file', $image))
            ->assertSee(route('admin.expense-reports.file', $pdf));
    });

    it('masks the IBAN from whoever does not wire refunds', function (): void {
        $report = ExpenseReport::factory()->create(['refund_iban' => 'BE68539007547034']);

        Livewire::actingAs(expenseTreasurer())
            ->test(TREASURY_EXPENSES)
            ->call('show', $report->id)
            ->assertSee('BE68 5390 0754 7034');

        Livewire::actingAs(User::factory()->withRole(Role::EXPENSE_REPORTS)->create())
            ->test(TREASURY_EXPENSES)
            ->call('show', $report->id)
            ->assertDontSee('BE68 5390 0754 7034')
            ->assertSee('7034');
    });
});
