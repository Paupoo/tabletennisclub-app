<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use App\Support\AccountProxy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const MEMBER_EXPENSES = 'pages::club-admin.users.user-space.expense-reports';

beforeEach(function (): void {
    Storage::fake('local');
    Carbon::setTestNow('2026-09-26 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('who reaches the page', function (): void {
    it('opens the page to an adult member', function (): void {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.user.expense-reports', $member))
            ->assertOk()
            ->assertSee(__('My expense reports'));
    });

    it('never opens it to a minor', function (): void {
        $minor = User::factory()->minor()->create();

        Livewire::actingAs($minor)->test(MEMBER_EXPENSES, ['user' => $minor])->assertForbidden();
    });

    it('never opens it on somebody else\'s space', function (): void {
        $member = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.user.expense-reports', $member))
            ->assertForbidden();
    });

    it('never opens it to a guardian holding a ward\'s seat', function (): void {
        $guardianMember = User::factory()->create();
        $ward = User::factory()->create(['email' => null]);
        $guardian = Guardian::factory()->create([
            'user_id' => $guardianMember->id,
            'first_name' => $guardianMember->first_name,
            'last_name' => $guardianMember->last_name,
            'email' => $guardianMember->email,
        ]);
        $ward->guardians()->attach($guardian->id);
        $this->actingAs($guardianMember);
        AccountProxy::start($ward);

        Livewire::test(MEMBER_EXPENSES, ['user' => $ward])->assertForbidden();
    });

    it('does not exist when the feature is off, nor when the treasury is', function (string $flag): void {
        config(["features.{$flag}" => false]);
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.user.expense-reports', $member))
            ->assertNotFound();
    })->with(['expense_reports', 'treasury']);

    it('shows the menu entry to adults only', function (): void {
        $adult = User::factory()->create();
        $minor = User::factory()->minor()->create();

        $this->actingAs($adult);
        $this->blade('<x-admin.navigation :user="$user" />', ['user' => $adult])
            ->assertSee(__('My expense reports'));

        $this->actingAs($minor);
        $this->blade('<x-admin.navigation :user="$user" />', ['user' => $minor])
            ->assertSee(__('My payments'))
            ->assertDontSee(__('My expense reports'));
    });
});

describe('declaring', function (): void {
    it('prefills the refund account from the member file', function (): void {
        $member = User::factory()->create(['iban' => 'BE68539007547034']);

        Livewire::actingAs($member)
            ->test(MEMBER_EXPENSES, ['user' => $member])
            ->call('openCreate')
            ->assertSet('formDrawer', true)
            ->assertSet('refundIban', 'BE68 5390 0754 7034');
    });

    it('submits a report with its proofs', function (): void {
        $member = User::factory()->create();

        Livewire::actingAs($member)
            ->test(MEMBER_EXPENSES, ['user' => $member])
            ->call('openCreate')
            ->set('category', ExpenseCategory::SportsEquipment->value)
            ->set('description', 'Balles Nittaku')
            ->set('amount', '42,50')
            ->set('spentOn', '2026-09-12')
            ->set('refundIban', 'BE68 5390 0754 7034')
            ->set('newFiles', [UploadedFile::fake()->image('ticket.jpg')])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('formDrawer', false);

        $report = ExpenseReport::sole();

        expect($report->user_id)->toBe($member->id)
            ->and($report->amount)->toBe(42.5)
            ->and($report->files)->toHaveCount(1);
    });

    it('validates what is declared', function (): void {
        $member = User::factory()->create();

        Livewire::actingAs($member)
            ->test(MEMBER_EXPENSES, ['user' => $member])
            ->call('openCreate')
            ->set('description', '')
            ->set('amount', '0')
            ->set('spentOn', '2026-10-01')
            ->set('refundIban', 'BE00 0000')
            ->set('newFiles', [UploadedFile::fake()->create('photo.heic', 100, 'image/heic')])
            ->call('save')
            ->assertHasErrors(['category', 'description', 'amount', 'spentOn', 'refundIban', 'newFiles.0']);

        expect(ExpenseReport::count())->toBe(0);
    });

    it('asks for at least one proof', function (): void {
        $member = User::factory()->create();

        Livewire::actingAs($member)
            ->test(MEMBER_EXPENSES, ['user' => $member])
            ->call('openCreate')
            ->set('category', ExpenseCategory::Other->value)
            ->set('description', 'Scotch')
            ->set('amount', '6.80')
            ->set('spentOn', '2026-09-12')
            ->set('refundIban', 'BE68539007547034')
            ->call('save')
            ->assertHasErrors(['newFiles']);
    });

    it('warns about a likely duplicate, and submits once the member confirms', function (): void {
        $member = User::factory()->create();
        ExpenseReport::factory()->for($member)->create(['amount' => 42.5, 'spent_on' => '2026-09-12']);

        $page = Livewire::actingAs($member)
            ->test(MEMBER_EXPENSES, ['user' => $member])
            ->call('openCreate')
            ->set('category', ExpenseCategory::Other->value)
            ->set('description', 'Balles')
            ->set('amount', '42.50')
            ->set('spentOn', '2026-09-13')
            ->set('refundIban', 'BE68539007547034')
            ->set('newFiles', [UploadedFile::fake()->image('ticket.jpg')])
            ->call('save')
            ->assertSet('formDrawer', true)
            ->assertSee(__('You already declared this amount around that date.'));

        expect(ExpenseReport::count())->toBe(1);

        $page->call('save', confirmDuplicate: true)->assertSet('formDrawer', false);

        expect(ExpenseReport::count())->toBe(2);
    });
});

describe('the member\'s own reports', function (): void {
    it('lists only the member\'s reports, with their status', function (): void {
        $member = User::factory()->create();
        ExpenseReport::factory()->for($member)->rejected('Ticket illisible')->create(['description' => 'Scotch refusé']);
        ExpenseReport::factory()->for($member)->create(['description' => 'Balles en cours']);
        ExpenseReport::factory()->create(['description' => 'Pas à moi']);

        Livewire::actingAs($member)
            ->test(MEMBER_EXPENSES, ['user' => $member])
            ->assertSee('Scotch refusé')
            ->assertSee('Ticket illisible')
            ->assertSee('Balles en cours')
            ->assertDontSee('Pas à moi');
    });

    it('filters on the status', function (): void {
        $member = User::factory()->create();
        ExpenseReport::factory()->for($member)->rejected()->create(['description' => 'Scotch refusé']);
        ExpenseReport::factory()->for($member)->create(['description' => 'Balles en cours']);

        Livewire::actingAs($member)
            ->test(MEMBER_EXPENSES, ['user' => $member])
            ->set('statusFilter', 'rejected')
            ->assertSee('Scotch refusé')
            ->assertDontSee('Balles en cours');
    });

    it('corrects a report in progress', function (): void {
        $member = User::factory()->create();
        $report = ExpenseReport::factory()->for($member)->create(['description' => 'Scoth']);
        $report->files()->create(['path' => 'expense-reports/x.jpg', 'original_name' => 'x.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'sha256' => str_repeat('a', 64)]);

        Livewire::actingAs($member)
            ->test(MEMBER_EXPENSES, ['user' => $member])
            ->call('openEdit', $report->id)
            ->assertSet('description', 'Scoth')
            ->set('description', 'Scotch')
            ->call('save')
            ->assertHasNoErrors();

        expect($report->refresh()->description)->toBe('Scotch');
    });

    it('withdraws a report in progress', function (): void {
        $member = User::factory()->create();
        $report = ExpenseReport::factory()->for($member)->create();

        Livewire::actingAs($member)
            ->test(MEMBER_EXPENSES, ['user' => $member])
            ->call('withdraw', $report->id);

        expect($report->refresh()->status)->toBe(ExpenseReportStatus::Withdrawn);
    });

    it('refuses to touch somebody else\'s report', function (): void {
        $member = User::factory()->create();
        $foreign = ExpenseReport::factory()->create();

        Livewire::actingAs($member)
            ->test(MEMBER_EXPENSES, ['user' => $member])
            ->call('withdraw', $foreign->id)
            ->assertForbidden();

        expect($foreign->refresh()->status)->toBe(ExpenseReportStatus::Submitted);
    });

    it('resumes a rejected report into a new one, filled in but without its proofs', function (): void {
        $member = User::factory()->create();
        $rejected = ExpenseReport::factory()->for($member)->rejected()->create([
            'category' => ExpenseCategory::Travel,
            'description' => 'Parking Namur',
            'amount' => 8,
            'spent_on' => '2026-09-10',
        ]);

        Livewire::actingAs($member)
            ->withQueryParams(['resume' => $rejected->id])
            ->test(MEMBER_EXPENSES, ['user' => $member])
            ->assertSet('formDrawer', true)
            ->assertSet('category', 'travel')
            ->assertSet('description', 'Parking Namur')
            ->assertSet('spentOn', '2026-09-10')
            ->set('newFiles', [UploadedFile::fake()->image('net.jpg')])
            ->call('save')
            ->assertHasNoErrors();

        expect(ExpenseReport::latest('id')->first()->resumed_from_id)->toBe($rejected->id)
            ->and($rejected->refresh()->status)->toBe(ExpenseReportStatus::Rejected);
    });
});

it('opens a report from the link in a notification, with its proofs', function (): void {
    $member = User::factory()->create();
    $report = ExpenseReport::factory()->for($member)->rejected('Il manque la preuve de paiement')->create();
    $proof = $report->files()->create(['path' => 'expense-reports/x.jpg', 'original_name' => 'ticket-carrefour.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'sha256' => str_repeat('a', 64)]);

    Livewire::actingAs($member)
        ->withQueryParams(['report' => $report->id])
        ->test(MEMBER_EXPENSES, ['user' => $member])
        ->assertSet('readerDrawer', true)
        ->assertSee('ticket-carrefour.jpg')
        ->assertSee(route('admin.expense-reports.file', $proof))
        ->assertSee(__('Resume this report'))
        ->set('readerDrawer', false)
        ->assertSet('shownId', null);
});

it('lists the reports as cards on a phone and as a table on a desktop', function (): void {
    $member = User::factory()->create();
    ExpenseReport::factory()->for($member)->create(['description' => 'Balles Nittaku', 'amount' => 42.5]);

    $html = Livewire::actingAs($member)
        ->test(MEMBER_EXPENSES, ['user' => $member])
        ->html();

    expect($html)->toContain('data-mobile-list')
        ->and(substr_count($html, 'Balles Nittaku'))->toBeGreaterThanOrEqual(2);

    Livewire::actingAs($member)
        ->test(MEMBER_EXPENSES, ['user' => $member])
        ->assertSeeHtml('<table')
        ->assertSee(__('Date of the expense'))
        ->assertSee(__('Declared on'));
});
