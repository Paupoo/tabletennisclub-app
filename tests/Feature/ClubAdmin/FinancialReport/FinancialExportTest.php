<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\ExpenseReports\Actions\AcceptExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Finance\Jobs\GenerateFinancialExport;
use App\Domains\ClubAdmin\Finance\Models\FinancialExport;
use App\Domains\ClubAdmin\Finance\Notifications\FinancialExportFailedNotification;
use App\Domains\ClubAdmin\Finance\Notifications\FinancialExportReadyNotification;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\LinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\FinancialExportScope;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Domains\Shared\Enums\Role;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Mpdf\Mpdf;

/**
 * The export of a financial year, asked for from the « Pièces & exports »
 * tab of the financial report: a PDF for the general assembly, a ZIP of the
 * originals for the archive. It replaced the expense reports export, whose
 * guarantees it keeps — built in the background, handed to its requester
 * only, for a week, and a ZIP downloaded by a decider archives the expense
 * reports it holds.
 */
beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    Carbon::setTestNow('2026-09-30 10:00:00');
    Club::factory()->ownClub()->create(['fiscal_year_start_month' => 1, 'name' => 'CTT Ottignies-Blocry']);
    Club::forgetOwnClub();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** A paid expense report carrying one real proof on the fake disk, refunded on the given day. */
function fxPaidReport(string $description, string $proofBytes = 'JPEGDATA', string $paidOn = '2026-09-20', ExpenseCategory $category = ExpenseCategory::SportsEquipment): ExpenseReport
{
    $member = User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);
    $report = ExpenseReport::factory()->for($member)->create(['description' => $description, 'amount' => 42.5, 'spent_on' => '2026-09-12', 'category' => $category]);
    $path = "expense-reports/{$report->id}/ticket.jpg";
    Storage::disk('local')->put($path, $proofBytes);
    $report->files()->create(['path' => $path, 'original_name' => 'ticket.jpg', 'mime_type' => 'image/jpeg', 'size' => strlen($proofBytes), 'sha256' => hash('sha256', $proofBytes)]);

    (new AcceptExpenseReport)($report, User::factory()->create());
    $refund = $report->refresh()->refund;
    $debit = Transaction::create(['date' => $paidOn, 'description' => 'VIREMENT', 'amount' => -42.5, 'counterparty_name' => 'Jean Dupont']);
    (new AllocateTransactionAction)($debit, [$refund->id => 42.5]);

    return $report->refresh();
}

/** A supporting document with one original file, paid by a bank line of the same day. */
function fxDocument(string $counterparty, float $amount, string $date, ExpenseCategory|IncomeCategory $category, string $fileBytes = 'SCAN-BYTES'): SupportingDocument
{
    $state = $category instanceof ExpenseCategory
        ? ['expense_category' => $category, 'income_category' => null]
        : ['expense_category' => null, 'income_category' => $category];

    $document = SupportingDocument::factory()->create([...$state, 'amount' => $amount, 'date' => $date, 'counterparty' => $counterparty, 'label' => 'Facture']);
    $document->files()->delete();
    $path = "supporting-documents/{$document->id}/facture.jpg";
    Storage::disk('local')->put($path, $fileBytes);
    $document->files()->create(['path' => $path, 'original_name' => 'facture.jpg', 'mime_type' => 'image/jpeg', 'size' => strlen($fileBytes), 'sha256' => hash('sha256', $fileBytes)]);

    (new LinkSupportingDocument)($document, Transaction::create([
        'date' => $date,
        'description' => 'Facture ' . $counterparty,
        'amount' => $category instanceof ExpenseCategory ? -$amount : $amount,
        'counterparty_name' => $counterparty,
    ]));

    return $document->refresh();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function fxRun(User $requester, string $format, array $attributes = []): FinancialExport
{
    $export = FinancialExport::create([
        'requested_by' => $requester->id,
        'format' => $format,
        'fiscal_year' => 2026,
        'scope' => FinancialExportScope::All,
        'report_ids' => [],
        'status' => 'pending',
        ...$attributes,
    ]);

    new GenerateFinancialExport($export->id)->handle();

    return $export->refresh();
}

/**
 * @return array<string, string> every entry of the ZIP, by name
 */
function fxZipEntries(FinancialExport $export): array
{
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path((string) $export->path));
    $entries = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = (string) $zip->getNameIndex($index);
        $entries[$name] = (string) $zip->getFromIndex($index);
    }

    $zip->close();

    return $entries;
}

function fxReportTab(User $user): Testable
{
    return Livewire::actingAs($user)
        ->test('pages::club-admin.treasury.report')
        ->set('tab', 'pieces');
}

function fxTreasurer(): User
{
    return User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create();
}

describe('asking for an export', function (): void {
    it('queues the export of the year shown, with the poste and the pieces chosen', function (): void {
        Queue::fake();
        $treasurer = fxTreasurer();

        fxReportTab($treasurer)
            ->set('fiscalYear', 2025)
            ->set('exportPoste', 'expense:hall')
            ->set('exportScope', 'documents')
            ->call('export', 'zip')
            ->assertHasNoErrors();

        $export = FinancialExport::sole();

        expect($export->requested_by)->toBe($treasurer->id)
            ->and($export->format)->toBe('zip')
            ->and($export->fiscal_year)->toBe(2025)
            ->and($export->poste)->toBe('expense:hall')
            ->and($export->scope)->toBe(FinancialExportScope::Documents);
        Queue::assertPushed(GenerateFinancialExport::class);
    });

    it('lets whoever reads the report export', function (Role $role): void {
        Queue::fake();

        fxReportTab(User::factory()->withRole($role)->create())
            ->call('export', 'pdf')
            ->assertHasNoErrors();

        expect(FinancialExport::sole()->fiscal_year)->toBe(2026);
    })->with([Role::COMMITTEE, Role::ACCOUNTS_AUDIT]);

    it('refuses an unknown format or poste', function (): void {
        Queue::fake();

        fxReportTab(fxTreasurer())
            ->call('export', 'docx')
            ->set('exportPoste', 'expense:caviar')
            ->call('export', 'pdf')
            ->assertHasErrors('exportPoste');

        expect(FinancialExport::count())->toBe(0);
    });

    it('offers no expense reports when they are switched off', function (): void {
        Queue::fake();
        config(['features.expense_reports' => false]);

        fxReportTab(fxTreasurer())
            ->assertDontSee(__('Expense reports only'))
            ->set('exportScope', 'expense_reports')
            ->call('export', 'zip')
            ->assertHasErrors('exportScope');
    });
});

describe('what the ZIP holds', function (): void {
    it('holds the report, the journal, the documents and the expense reports as they were sent', function (): void {
        $document = fxDocument('AFTT', 350.0, '2026-02-10', ExpenseCategory::Federation, 'AFTT-ORIGINAL');
        $report = fxPaidReport('Balles Nittaku', 'TICKET-ORIGINAL');
        $requester = User::factory()->isCommitteeMember()->create();

        $export = fxRun($requester, 'zip');
        $entries = fxZipEntries($export);
        $piece = sprintf('pieces/P-2026-%04d — AFTT/facture.jpg', $document->id);
        $folder = "notes-de-frais/2026-09-12_Dupont-Jean_42,50€_#{$report->id}/";

        expect($export->status)->toBe('ready')
            ->and($export->expires_at->isAfter(now()->addDays(6)))->toBeTrue()
            ->and(array_keys($entries))->toContain('rapport-financier.pdf', 'journal.csv', $piece, $folder . 'ticket.jpg', 'notes-de-frais/recapitulatif.pdf', 'notes-de-frais/notes-de-frais.csv')
            ->and($entries[$piece])->toBe('AFTT-ORIGINAL')
            ->and($entries[$folder . 'ticket.jpg'])->toBe('TICKET-ORIGINAL')
            ->and($entries['rapport-financier.pdf'])->toStartWith('%PDF')
            ->and($entries['notes-de-frais/notes-de-frais.csv'])->toContain('Balles Nittaku')
            ->and($export->report_ids)->toBe([$report->id]);

        Notification::assertSentTo($requester, FinancialExportReadyNotification::class);
    });

    it('writes a journal Excel opens: byte-order mark, semicolons, decimal commas', function (): void {
        $document = fxDocument('AFTT', 350.0, '2026-02-10', ExpenseCategory::Federation);

        $csv = fxZipEntries(fxRun(fxTreasurer(), 'zip'))['journal.csv'];
        $lines = explode("\n", trim(substr($csv, 3)));

        expect($csv)->toStartWith("\u{FEFF}")
            ->and($lines)->toHaveCount(2)
            ->and($lines[1])->toBe(sprintf('2026-02-10;Banque;;AFTT;"Facture AFTT";-350,00;"Fédération & compétitions";"Justifiée par une pièce";P-2026-%04d;', $document->id));
    });

    it('keeps to the poste and the pieces asked for', function (): void {
        fxDocument('AFTT', 350.0, '2026-02-10', ExpenseCategory::Federation);
        $hall = fxDocument('Blocry', 900.0, '2026-04-01', ExpenseCategory::Hall);
        fxPaidReport('Balles');
        // Dated in 2025: not a piece of 2026.
        fxDocument('Blocry', 800.0, '2025-12-01', ExpenseCategory::Hall);

        $export = fxRun(fxTreasurer(), 'zip', ['poste' => 'expense:hall', 'scope' => FinancialExportScope::Documents]);
        $names = array_keys(fxZipEntries($export));

        expect(array_values(array_filter($names, fn (string $name): bool => str_starts_with($name, 'pieces/') && ! str_ends_with($name, '/'))))
            ->toBe([sprintf('pieces/P-2026-%04d — Blocry/facture.jpg', $hall->id)])
            ->and(array_filter($names, fn (string $name): bool => str_starts_with($name, 'notes-de-frais/')))->toBe([])
            ->and($export->report_ids)->toBe([]);
    });

    it('leaves the expense reports out when they are switched off', function (): void {
        fxPaidReport('Balles');
        config(['features.expense_reports' => false]);

        $export = fxRun(fxTreasurer(), 'zip');

        expect(array_filter(array_keys(fxZipEntries($export)), fn (string $name): bool => str_starts_with($name, 'notes-de-frais/')))->toBe([])
            ->and($export->report_ids)->toBe([]);
    });
});

describe('what the PDF holds', function (): void {
    it('prints the report, the journal and a page per piece, even with a proof it cannot read', function (): void {
        fxDocument('AFTT', 350.0, '2026-02-10', ExpenseCategory::Federation, 'not-really-a-jpeg');
        fxPaidReport('Balles Nittaku', 'not-really-a-jpeg');

        $export = fxRun(User::factory()->isCommitteeMember()->create(), 'pdf');
        $printed = (string) Storage::disk('local')->get((string) $export->path);

        expect($export->status)->toBe('ready')
            ->and($printed)->toStartWith('%PDF')
            ->and(preg_match_all('#/Type\s*/Page[^s]#', $printed))->toBeGreaterThanOrEqual(4);
    });

    it('prints the pages of a PDF proof, and falls back on a note for a broken one', function (): void {
        $document = fxDocument('Decathlon', 80.0, '2026-03-01', ExpenseCategory::SportsEquipment);
        $mpdf = new Mpdf(['tempDir' => storage_path('app/mpdf')]);
        $mpdf->WriteHTML('<p>Facture Decathlon</p>');
        Storage::disk('local')->put("supporting-documents/{$document->id}/facture.pdf", $mpdf->Output('', 'S'));
        Storage::disk('local')->put("supporting-documents/{$document->id}/casse.pdf", 'not a pdf at all');
        foreach (['facture.pdf', 'casse.pdf'] as $name) {
            $document->files()->create(['path' => "supporting-documents/{$document->id}/{$name}", 'original_name' => $name, 'mime_type' => 'application/pdf', 'size' => 1, 'sha256' => hash('sha256', $name)]);
        }

        $export = fxRun(fxTreasurer(), 'pdf', ['scope' => FinancialExportScope::Documents]);

        expect($export->status)->toBe('ready');
    });

    it('names the file after the financial year', function (): void {
        Club::own()->update(['fiscal_year_start_month' => 9]);
        Club::forgetOwnClub();
        $requester = fxTreasurer();

        $export = fxRun($requester, 'pdf', ['fiscal_year' => 2025]);

        $this->actingAs($requester)
            ->get(route('admin.treasury.exports.download', $export))
            ->assertOk()
            ->assertDownload('rapport-financier-2025-2026.pdf');
    });
});

/*
 * The bell does not refresh on its own, and nothing else pointed at the file:
 * a treasurer waiting on the page never learnt the export was ready.
 */
describe('telling the requester', function (): void {
    it('mails the link as well as ringing the bell', function (): void {
        $requester = fxTreasurer();

        $export = fxRun($requester, 'zip');

        Notification::assertSentTo(
            $requester,
            FinancialExportReadyNotification::class,
            fn (FinancialExportReadyNotification $n, array $channels): bool => $channels === ['mail', 'database'],
        );

        $mail = new FinancialExportReadyNotification($export)->toMail($requester);
        expect($mail->actionUrl)->toBe(route('admin.treasury.exports.download', $export))
            ->and((string) $mail->subject)->toContain('2026');
    });

    it('marks a failed export and says so, instead of leaving the requester waiting', function (): void {
        $requester = fxTreasurer();
        $export = FinancialExport::create(['requested_by' => $requester->id, 'format' => 'pdf', 'fiscal_year' => 2026, 'report_ids' => [], 'status' => 'pending']);

        new GenerateFinancialExport($export->id)->failed(new RuntimeException('mPDF ran out of memory'));

        expect($export->refresh()->status)->toBe('failed');
        Notification::assertSentTo(
            $requester,
            FinancialExportFailedNotification::class,
            fn (FinancialExportFailedNotification $n, array $channels): bool => in_array('mail', $channels, true)
                && $n->toMail($requester)->actionUrl === route('admin.treasury.report', ['year' => 2026, 'tab' => 'pieces']),
        );
    });

    it('lists the requester\'s own exports on the tab, with their state', function (): void {
        $requester = fxTreasurer();
        $ready = FinancialExport::create(['requested_by' => $requester->id, 'format' => 'zip', 'fiscal_year' => 2026, 'report_ids' => [], 'status' => 'ready', 'path' => 'x.zip', 'expires_at' => now()->addDays(7)]);
        $failed = FinancialExport::create(['requested_by' => $requester->id, 'format' => 'pdf', 'fiscal_year' => 2025, 'report_ids' => [], 'status' => 'failed']);
        $someoneElses = FinancialExport::create(['requested_by' => User::factory()->create()->id, 'format' => 'pdf', 'fiscal_year' => 2026, 'report_ids' => [], 'status' => 'ready', 'path' => 'y.pdf', 'expires_at' => now()->addDays(7)]);

        fxReportTab($requester)
            ->assertSee(__('My exports'))
            ->assertSee(__('Financial year :year', ['year' => '2025']))
            ->assertSeeHtml(route('admin.treasury.exports.download', $ready))
            ->assertSeeHtml('retryExport(' . $failed->id . ')')
            ->assertDontSeeHtml(route('admin.treasury.exports.download', $someoneElses))
            ->assertDontSeeHtml('wire:poll');
    });

    it('keeps polling while an export is being prepared', function (): void {
        $requester = fxTreasurer();
        FinancialExport::create(['requested_by' => $requester->id, 'format' => 'pdf', 'fiscal_year' => 2026, 'report_ids' => [], 'status' => 'pending']);

        fxReportTab($requester)
            ->assertSee(__('Being prepared…'))
            ->assertSeeHtml('wire:poll');
    });

    it('builds a failed export again with the same year, poste and pieces', function (): void {
        Queue::fake();
        $requester = fxTreasurer();
        $failed = FinancialExport::create(['requested_by' => $requester->id, 'format' => 'zip', 'fiscal_year' => 2025, 'poste' => 'expense:hall', 'scope' => 'documents', 'report_ids' => [], 'status' => 'failed']);

        fxReportTab($requester)->call('retryExport', $failed->id);

        $retry = FinancialExport::latest('id')->first();
        expect($retry->id)->not->toBe($failed->id)
            ->and([$retry->format, $retry->fiscal_year, $retry->poste, $retry->scope, $retry->status])
            ->toBe(['zip', 2025, 'expense:hall', FinancialExportScope::Documents, 'pending']);
        Queue::assertPushed(GenerateFinancialExport::class);
    });

    it('never retries someone else\'s export', function (): void {
        Queue::fake();
        $failed = FinancialExport::create(['requested_by' => User::factory()->create()->id, 'format' => 'zip', 'fiscal_year' => 2026, 'report_ids' => [], 'status' => 'failed']);

        fxReportTab(fxTreasurer())
            ->call('retryExport', $failed->id)
            ->assertNotFound();

        Queue::assertNothingPushed();
    });
});

describe('fetching the export', function (): void {
    it('hands it to whoever asked for it, and to nobody else', function (): void {
        $requester = fxTreasurer();
        $export = fxRun($requester, 'pdf');

        $this->actingAs(User::factory()->isCommitteeMember()->create())
            ->get(route('admin.treasury.exports.download', $export))
            ->assertForbidden();

        $this->actingAs($requester)
            ->get(route('admin.treasury.exports.download', $export))
            ->assertOk()
            ->assertDownload();
    });

    it('marks the expense reports it holds archived when a treasurer downloads the ZIP', function (): void {
        $report = fxPaidReport('Balles');
        // Paid in 2025: not in the 2026 file, so not archived by it.
        $earlier = fxPaidReport('Filets', paidOn: '2025-11-20');
        $treasurer = fxTreasurer();
        $export = fxRun($treasurer, 'zip');

        $this->actingAs($treasurer)->get(route('admin.treasury.exports.download', $export))->assertOk();

        expect($report->refresh()->archived_at)->not->toBeNull()
            ->and($earlier->refresh()->archived_at)->toBeNull();
    });

    it('does not count as archiving when a reader downloads it, nor for a PDF', function (): void {
        $report = fxPaidReport('Balles');
        $reader = User::factory()->isCommitteeMember()->create();
        $treasurer = fxTreasurer();

        $this->actingAs($reader)->get(route('admin.treasury.exports.download', fxRun($reader, 'zip')))->assertOk();
        $this->actingAs($treasurer)->get(route('admin.treasury.exports.download', fxRun($treasurer, 'pdf')))->assertOk();

        expect($report->refresh()->archived_at)->toBeNull();
    });

    it('still serves an expense reports export made before, from its old link', function (): void {
        $requester = fxTreasurer();
        Storage::disk('local')->put('expense-report-exports/7/notes-de-frais.zip', 'OLD-ZIP');
        $export = FinancialExport::create(['requested_by' => $requester->id, 'format' => 'zip', 'report_ids' => [], 'status' => 'ready', 'path' => 'expense-report-exports/7/notes-de-frais.zip', 'expires_at' => now()->addDays(3)]);

        $this->actingAs($requester)
            ->get('/admin/my-space/expense-reports/exports/' . $export->id)
            ->assertRedirect(route('admin.treasury.exports.download', $export));
        $this->actingAs($requester)
            ->get(route('admin.treasury.exports.download', $export))
            ->assertDownload('notes-de-frais.zip');
    });

    it('says the export has expired after seven days', function (): void {
        // Debug mode renders the exception page, which prints the message whatever
        // the error views hold: the test must see the page production serves.
        config(['app.debug' => false]);
        $requester = fxTreasurer();
        $export = fxRun($requester, 'pdf');

        $this->travel(8)->days();
        $this->artisan('financial-exports:prune')->assertSuccessful();

        Storage::disk('local')->assertMissing((string) $export->path);
        $this->actingAs($requester)
            ->get(route('admin.treasury.exports.download', $export))
            ->assertStatus(410)
            ->assertSee(__('This export has expired: run it again from the financial report.'));
    });

    it('prunes every night', function (): void {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'financial-exports:prune'));

        expect($event)->not->toBeNull()
            ->and($event->filtersPass(app()))->toBeTrue();
    });
});
