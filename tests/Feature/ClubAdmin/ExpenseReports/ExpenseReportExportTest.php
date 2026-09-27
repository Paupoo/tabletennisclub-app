<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\ExpenseReports\Actions\AcceptExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Jobs\GenerateExpenseReportExport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReportExport;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportExportReadyNotification;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mpdf\Mpdf;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

/** A paid report carrying one real proof on the fake disk. */
function exportablePaidReport(string $description, string $proofBytes = 'JPEGDATA'): ExpenseReport
{
    $member = User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);
    $report = ExpenseReport::factory()->for($member)->create(['description' => $description, 'amount' => 42.5, 'spent_on' => '2026-09-12']);
    $path = "expense-reports/{$report->id}/ticket.jpg";
    Storage::disk('local')->put($path, $proofBytes);
    $report->files()->create(['path' => $path, 'original_name' => 'ticket.jpg', 'mime_type' => 'image/jpeg', 'size' => strlen($proofBytes), 'sha256' => hash('sha256', $proofBytes)]);

    (new AcceptExpenseReport)($report, User::factory()->create());
    $refund = $report->refresh()->refund;
    $debit = Transaction::create(['date' => '2026-09-20', 'description' => 'VIREMENT', 'amount' => -42.5, 'counterparty_name' => 'Jean Dupont']);
    (new AllocateTransactionAction)($debit, [$refund->id => 42.5]);

    return $report->refresh();
}

function runExpenseReportExport(User $requester, string $format, array $reportIds): ExpenseReportExport
{
    $export = ExpenseReportExport::create([
        'requested_by' => $requester->id,
        'format' => $format,
        'report_ids' => $reportIds,
        'status' => 'pending',
    ]);

    new GenerateExpenseReportExport($export->id)->handle();

    return $export->refresh();
}

describe('asking for an export', function (): void {
    it('queues an export of exactly what the screen shows', function (): void {
        Queue::fake();
        $paid = exportablePaidReport('Balles');
        ExpenseReport::factory()->create(['description' => 'En cours']);
        $treasurer = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create();

        Livewire::actingAs($treasurer)
            ->test('pages::club-admin.treasury.expense-reports')
            ->set('statusFilter', 'paid')
            ->call('export', 'zip');

        $export = ExpenseReportExport::sole();

        expect($export->requested_by)->toBe($treasurer->id)
            ->and($export->format)->toBe('zip')
            ->and($export->report_ids)->toBe([$paid->id]);
        Queue::assertPushed(GenerateExpenseReportExport::class);
    });

    it('lets the committee export too', function (): void {
        Queue::fake();
        ExpenseReport::factory()->create();

        Livewire::actingAs(User::factory()->isCommitteeMember()->create())
            ->test('pages::club-admin.treasury.expense-reports')
            ->call('export', 'pdf')
            ->assertHasNoErrors();

        expect(ExpenseReportExport::count())->toBe(1);
    });

    it('refuses an unknown format', function (): void {
        Livewire::actingAs(User::factory()->isCommitteeMember()->create())
            ->test('pages::club-admin.treasury.expense-reports')
            ->call('export', 'docx');

        expect(ExpenseReportExport::count())->toBe(0);
    });
});

describe('what the export holds', function (): void {
    it('zips a summary, a spreadsheet and every original proof in a folder per report', function (): void {
        $report = exportablePaidReport('Balles Nittaku', 'ORIGINAL-BYTES');
        $requester = User::factory()->isCommitteeMember()->create();

        $export = runExpenseReportExport($requester, 'zip', [$report->id]);

        expect($export->status)->toBe('ready')
            ->and($export->expires_at->isAfter(now()->addDays(6)))->toBeTrue();

        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($export->path));
        $names = collect(range(0, $zip->numFiles - 1))->map(fn (int $i): string => $zip->getNameIndex($i));
        $folder = "2026-09-12_Dupont-Jean_42,50€_#{$report->id}/";

        expect($names)->toContain('notes-de-frais.csv', 'recapitulatif.pdf', $folder . 'ticket.jpg')
            ->and($zip->getFromName($folder . 'ticket.jpg'))->toBe('ORIGINAL-BYTES')
            ->and($zip->getFromName('notes-de-frais.csv'))->toContain('Balles Nittaku')
            ->toContain('2026-09-20')
            ->toContain('42,50');

        Notification::assertSentTo($requester, ExpenseReportExportReadyNotification::class);
    });

    it('prints a PDF that holds every report, even with a proof it cannot read', function (): void {
        $report = exportablePaidReport('Balles Nittaku', 'not-really-a-jpeg');

        $export = runExpenseReportExport(User::factory()->isCommitteeMember()->create(), 'pdf', [$report->id]);

        expect($export->status)->toBe('ready')
            ->and(Storage::disk('local')->get($export->path))->toStartWith('%PDF');
    });
});

describe('fetching the export', function (): void {
    it('hands it to whoever asked for it, and to nobody else', function (): void {
        $report = exportablePaidReport('Balles');
        $requester = User::factory()->isCommitteeMember()->create();
        $export = runExpenseReportExport($requester, 'pdf', [$report->id]);

        $this->actingAs(User::factory()->isCommitteeMember()->create())
            ->get(route('admin.expense-reports.export', $export))
            ->assertForbidden();

        $this->actingAs($requester)
            ->get(route('admin.expense-reports.export', $export))
            ->assertOk()
            ->assertDownload();
    });

    it('marks the reports archived when a treasurer downloads the ZIP', function (): void {
        $report = exportablePaidReport('Balles');
        $treasurer = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create();
        $export = runExpenseReportExport($treasurer, 'zip', [$report->id]);

        $this->actingAs($treasurer)->get(route('admin.expense-reports.export', $export))->assertOk();

        expect($report->refresh()->archived_at)->not->toBeNull();
    });

    it('does not count as archiving when a reader downloads it, nor for a PDF', function (): void {
        $report = exportablePaidReport('Balles');
        $reader = User::factory()->isCommitteeMember()->create();
        $treasurer = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create();

        $this->actingAs($reader)->get(route('admin.expense-reports.export', runExpenseReportExport($reader, 'zip', [$report->id])))->assertOk();
        $this->actingAs($treasurer)->get(route('admin.expense-reports.export', runExpenseReportExport($treasurer, 'pdf', [$report->id])))->assertOk();

        expect($report->refresh()->archived_at)->toBeNull();
    });

    it('says the export has expired after seven days', function (): void {
        // Debug mode renders the exception page, which prints the message whatever
        // the error views hold: the test must see the page production serves.
        config(['app.debug' => false]);
        $report = exportablePaidReport('Balles');
        $requester = User::factory()->isCommitteeMember()->create();
        $export = runExpenseReportExport($requester, 'pdf', [$report->id]);

        $this->travel(8)->days();
        $this->artisan('expense-reports:prune-exports')->assertSuccessful();

        Storage::disk('local')->assertMissing($export->path);
        $this->actingAs($requester)
            ->get(route('admin.expense-reports.export', $export))
            ->assertStatus(410)
            ->assertSee(__('This export has expired: run it again from the expense reports.'));
    });

    it('prunes every night, whether the feature is on or not', function (): void {
        config(['features.expense_reports' => false]);

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'expense-reports:prune-exports'));

        expect($event)->not->toBeNull()
            ->and($event->filtersPass(app()))->toBeTrue();
    });
});

it('prints the pages of a PDF proof, and falls back on a note for a broken one', function (): void {
    $report = exportablePaidReport('Balles');
    $mpdf = new Mpdf(['tempDir' => storage_path('app/mpdf')]);
    $mpdf->WriteHTML('<p>Facture Decathlon</p>');
    Storage::disk('local')->put("expense-reports/{$report->id}/facture.pdf", $mpdf->Output('', 'S'));
    Storage::disk('local')->put("expense-reports/{$report->id}/casse.pdf", 'not a pdf at all');
    foreach (['facture.pdf', 'casse.pdf'] as $name) {
        $report->files()->create(['path' => "expense-reports/{$report->id}/{$name}", 'original_name' => $name, 'mime_type' => 'application/pdf', 'size' => 1, 'sha256' => hash('sha256', $name)]);
    }

    $export = runExpenseReportExport(User::factory()->isCommitteeMember()->create(), 'pdf', [$report->id]);
    $printed = Storage::disk('local')->get($export->path);

    // Summary, report page, the imported invoice page, the note about the broken file.
    expect($export->status)->toBe('ready')
        ->and(preg_match_all('#/Type\s*/Page[^s]#', (string) $printed))->toBeGreaterThanOrEqual(3);
});
