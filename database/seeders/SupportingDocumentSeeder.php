<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\ClubAdmin\Payments\LinkCashDepositAction;
use App\Domains\ClubAdmin\Payment\Models\BankAccount;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\CreateSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\LinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\BankAccountType;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Domains\Shared\Support\IbanNormalizer;
use App\Domains\Shared\ValueObjects\FiscalYear;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

/**
 * The money the website never sees, and its proofs — two financial years of
 * it, the current one up to today and the whole previous one.
 *
 * Development only: every file is watermarked SPECIMEN, and the seeder
 * refuses to run in production. Re-runnable alone —
 * `php artisan db:seed --class=SupportingDocumentSeeder` wipes every
 * supporting document and the bank lines and till movements it created
 * (fingerprinted `demo-sd-…`, noted {@see self::MARKER}), then seeds again.
 *
 * What it leaves:
 *
 * - a savings account next to the current one, and one transfer to it
 *   (internal on both sides);
 * - about forty documents — federation, provincial committee, the Blocry hall
 *   every quarter, balls, coaches' allowances, insurance, bank fees, a
 *   municipal subsidy, a sponsor — each paid by a bank line of its own;
 * - a bar ticket paid from the till, and a till deposit linked to its bank
 *   credit;
 * - two invoices still to pay (debts) and a subsidy promised but not
 *   received (a receivable);
 * - a handful of bank lines nobody explained yet — about 15 % of what it
 *   creates — so the list to handle is never empty.
 *
 * The balances of both accounts are then recomputed over every line, in date
 * order, from {@see self::OPENING_BALANCES}. A seeder that adds bank lines
 * after this one (the financial history of lot 3) calls
 * {@see self::fillBalances()} again.
 */
class SupportingDocumentSeeder extends Seeder
{
    /** Written in the notes of every till movement this seeder creates. */
    public const string MARKER = 'Démo pièces justificatives';

    /**
     * Opening balances, in cents: the current account's is TreasurySeeder's.
     *
     * @var array<string, int>
     */
    public const array OPENING_BALANCES = [
        'current' => 800_000,
        'savings' => 1_500_000,
    ];

    /**
     * The documents of a year: counterparty, label, category, amount in euros
     * for the previous year then the current one (null: none that year), the
     * month offsets from the start of the year, how it was settled (bank,
     * cash, open) and the kind of file.
     *
     * The differences between the two years are chosen: the hall costs 12 %
     * more, fewer balls were bought, a new subsidy appears.
     *
     * @var list<array{counterparty: string, label: string, category: ExpenseCategory|IncomeCategory, amounts: array{0: float|null, 1: float|null}, months: list<int>, settled: string, file: string}>
     */
    private array $catalogue;

    private int $sequence = 0;

    /**
     * Balances in date order, from the opening balance of the account's type.
     *
     * Written straight to the table: these are the bank's figures, nothing the
     * audit log should narrate.
     */
    public static function fillBalances(BankAccount $account): void
    {
        $balance = self::OPENING_BALANCES[$account->type->value] ?? 0;

        DB::table('transactions')
            ->where('bank_account_id', $account->id)
            ->whereNull('deleted_at')
            ->orderBy('date')
            ->orderBy('id')
            ->get(['id', 'date', 'amount'])
            ->each(function (object $line) use (&$balance): void {
                $balance += (int) $line->amount;
                $date = CarbonImmutable::parse($line->date);

                DB::table('transactions')->where('id', $line->id)->update([
                    'balance_after' => $balance,
                    'statement_number' => $date->format('Y') . str_pad((string) $date->month, 3, '0', STR_PAD_LEFT),
                ]);
            });
    }

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('SupportingDocumentSeeder ne tourne jamais en production.');

            return;
        }

        $this->catalogue = $this->catalogue();
        $this->wipe();

        $current = $this->currentAccount();
        $savings = BankAccount::firstOrCreate(
            ['iban' => 'BE71096123456769'],
            ['name' => 'Compte épargne', 'type' => BankAccountType::Savings],
        );
        $register = CashRegister::query()->orderBy('id')->first() ?? CashRegister::create(['name' => 'Caisse du club']);
        $author = User::where('committee_role', CommitteeRolesEnum::TREASURER->value)->first() ?? User::query()->orderBy('id')->first();

        $today = CarbonImmutable::today();
        $years = [FiscalYear::current()->previous(), FiscalYear::current()];

        foreach ($years as $index => $year) {
            foreach ($this->catalogue as $entry) {
                $amount = $entry['amounts'][$index];

                if ($amount === null) {
                    continue;
                }

                foreach ($entry['months'] as $position => $month) {
                    $date = $year->start()->addMonths($month)->addDays(4 + $position);

                    if ($date->greaterThan($today)) {
                        continue;
                    }

                    $this->seedDocument($entry, $amount, $date, $current, $register, $author);
                }
            }
        }

        $this->seedUnexplainedLines($current, $today);
        $this->seedSavingsTransfer($current, $savings, $today);
        $this->seedTillDeposit($current, $register, $author, $today);

        self::fillBalances($current);
        self::fillBalances($savings);
    }

    private function ascii(string $text): string
    {
        return (string) iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    }

    /**
     * @return list<array{counterparty: string, label: string, category: ExpenseCategory|IncomeCategory, amounts: array{0: float|null, 1: float|null}, months: list<int>, settled: string, file: string}>
     */
    private function catalogue(): array
    {
        return [
            ['counterparty' => 'Complexe sportif de Blocry', 'label' => 'Location de la salle — trimestre', 'category' => ExpenseCategory::Hall, 'amounts' => [1250.0, 1400.0], 'months' => [0, 3, 6, 9], 'settled' => 'bank', 'file' => 'pdf'],
            ['counterparty' => 'AFTT', 'label' => 'Affiliation du club et licences', 'category' => ExpenseCategory::Federation, 'amounts' => [1840.0, 1905.0], 'months' => [8], 'settled' => 'bank', 'file' => 'pdf'],
            ['counterparty' => 'Comité provincial BBW', 'label' => 'Cotisation au comité provincial', 'category' => ExpenseCategory::Federation, 'amounts' => [150.0, 150.0], 'months' => [9], 'settled' => 'bank', 'file' => 'pdf'],
            ['counterparty' => 'Tibhar Belgium', 'label' => "Balles d'entraînement (×144)", 'category' => ExpenseCategory::SportsEquipment, 'amounts' => [180.0, 180.0], 'months' => [1, 7], 'settled' => 'bank', 'file' => 'pdf'],
            ['counterparty' => 'Decathlon Wavre', 'label' => 'Filets et poteaux', 'category' => ExpenseCategory::SportsEquipment, 'amounts' => [260.0, null], 'months' => [4], 'settled' => 'bank', 'file' => 'jpg'],
            ['counterparty' => 'Entraîneurs du club', 'label' => 'Défraiement des entraîneurs', 'category' => ExpenseCategory::Training, 'amounts' => [600.0, 640.0], 'months' => [2, 5, 9], 'settled' => 'bank', 'file' => 'pdf'],
            ['counterparty' => 'Ethias', 'label' => 'Assurance responsabilité civile du club', 'category' => ExpenseCategory::Operations, 'amounts' => [420.0, 435.0], 'months' => [1], 'settled' => 'bank', 'file' => 'pdf'],
            ['counterparty' => 'CBC Banque', 'label' => 'Frais de tenue de compte annuels', 'category' => ExpenseCategory::Operations, 'amounts' => [36.0, 36.0], 'months' => [0], 'settled' => 'bank', 'file' => 'jpg'],
            ['counterparty' => 'Bureau Vallée', 'label' => 'Fournitures de bureau', 'category' => ExpenseCategory::Operations, 'amounts' => [42.3, 38.9], 'months' => [2], 'settled' => 'bank', 'file' => 'jpg'],
            ['counterparty' => 'Trophées Leroy', 'label' => 'Coupes et médailles du tournoi', 'category' => ExpenseCategory::Event, 'amounts' => [145.0, 162.0], 'months' => [4], 'settled' => 'bank', 'file' => 'pdf'],
            ['counterparty' => 'Colruyt Ottignies', 'label' => 'Courses du bar', 'category' => ExpenseCategory::Bar, 'amounts' => [null, 85.6], 'months' => [3], 'settled' => 'cash', 'file' => 'jpg'],
            ['counterparty' => "Ville d'Ottignies-Louvain-la-Neuve", 'label' => 'Subside sportif communal', 'category' => IncomeCategory::Subsidies, 'amounts' => [800.0, null], 'months' => [5], 'settled' => 'bank', 'file' => 'pdf'],
            ['counterparty' => 'Fédération Wallonie-Bruxelles — ADEPS', 'label' => "Subside pour l'achat de matériel", 'category' => IncomeCategory::Subsidies, 'amounts' => [null, 500.0], 'months' => [3], 'settled' => 'bank', 'file' => 'pdf'],
            ['counterparty' => 'Brasserie du Blocry', 'label' => 'Sponsoring des maillots', 'category' => IncomeCategory::Sponsorship, 'amounts' => [750.0, 750.0], 'months' => [1], 'settled' => 'bank', 'file' => 'pdf'],
            // Still open: two invoices the club owes, a subsidy it is owed.
            ['counterparty' => 'Imprimerie Hayez', 'label' => 'Affiches du tournoi', 'category' => ExpenseCategory::Event, 'amounts' => [null, 95.0], 'months' => [7], 'settled' => 'open', 'file' => 'pdf'],
            ['counterparty' => 'Sport Réparation SA', 'label' => 'Réparation de la table n°3', 'category' => ExpenseCategory::SportsEquipment, 'amounts' => [null, 210.0], 'months' => [8], 'settled' => 'open', 'file' => 'pdf'],
            ['counterparty' => "Ville d'Ottignies-Louvain-la-Neuve", 'label' => 'Subside sportif communal — promis', 'category' => IncomeCategory::Subsidies, 'amounts' => [null, 950.0], 'months' => [5], 'settled' => 'open', 'file' => 'pdf'],
        ];
    }

    private function currentAccount(): BankAccount
    {
        $account = BankAccount::query()->current()->orderBy('id')->first()
            ?? BankAccount::firstOrCreate(
                ['iban' => IbanNormalizer::normalize(Club::ourClub()->value('bank_account')) ?? 'BE23732333208791'],
                ['name' => 'Compte courant', 'type' => BankAccountType::Current],
            );

        if ($account->name !== 'Compte courant') {
            $account->update(['name' => 'Compte courant']);
        }

        return $account;
    }

    /**
     * A small watermarked file standing for the real one: a PDF invoice, or
     * a JPEG photo of a ticket or a screenshot of the statement.
     */
    private function file(string $kind, string $counterparty, string $label, float $amount, CarbonImmutable $date): UploadedFile
    {
        $directory = storage_path('app/seeders/supporting-documents');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $slug = str($counterparty . ' ' . $label)->slug()->limit(40, '')->toString();
        $path = $directory . '/' . $slug . '-' . $date->format('Ymd') . '.' . $kind;
        $amountText = number_format($amount, 2, ',', ' ') . ' EUR';

        if ($kind === 'pdf') {
            $pdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A5', 'tempDir' => storage_path('app/mpdf')]);
            $pdf->SetWatermarkText('SPECIMEN', 0.15);
            $pdf->showWatermarkText = true;
            $pdf->WriteHTML(sprintf(
                '<h2>%s</h2><p>%s</p><p>Date : %s</p><p><strong>Montant : %s</strong></p><p style="color:#b91c1c">SPECIMEN — document de démonstration</p>',
                e($counterparty),
                e($label),
                $date->format('d/m/Y'),
                $amountText,
            ));
            file_put_contents($path, $pdf->Output('', 'S'));

            return new UploadedFile($path, basename($path), 'application/pdf', null, true);
        }

        $image = imagecreatetruecolor(420, 300);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 250, 250, 245));
        $ink = (int) imagecolorallocate($image, 30, 30, 30);
        $red = (int) imagecolorallocate($image, 200, 30, 30);
        imagestring($image, 5, 20, 20, $this->ascii($counterparty), $ink);
        imagestring($image, 3, 20, 60, $this->ascii($label), $ink);
        imagestring($image, 3, 20, 90, $date->format('d/m/Y'), $ink);
        imagestring($image, 5, 20, 130, $amountText, $ink);
        imagestring($image, 5, 150, 230, 'SPECIMEN', $red);
        imagejpeg($image, $path, 80);
        imagedestroy($image);

        return new UploadedFile($path, basename($path), 'image/jpeg', null, true);
    }

    /**
     * A bank line of the demo, fingerprinted so the next run finds it.
     */
    private function line(BankAccount $account, CarbonImmutable $date, float $amount, string $description, ?string $counterparty, bool $internal = false): Transaction
    {
        return Transaction::create([
            'bank_account_id' => $account->id,
            'date' => $date->toDateString(),
            'description' => $description,
            'amount' => $amount,
            'counterparty_name' => $counterparty,
            'import_fingerprint' => sprintf('demo-sd-%04d', ++$this->sequence),
            'is_internal' => $internal,
        ]);
    }

    /**
     * @param  array{counterparty: string, label: string, category: ExpenseCategory|IncomeCategory, amounts: array{0: float|null, 1: float|null}, months: list<int>, settled: string, file: string}  $entry
     */
    private function seedDocument(array $entry, float $amount, CarbonImmutable $date, BankAccount $account, CashRegister $register, ?User $author): void
    {
        $document = (new CreateSupportingDocument)(
            category: $entry['category'],
            date: $date,
            amount: $amount,
            counterparty: $entry['counterparty'],
            label: $entry['label'],
            files: [$this->file($entry['file'], $entry['counterparty'], $entry['label'], $amount, $date)],
            author: $author,
        );
        $document->forceFill(['created_at' => $date->addDay(), 'updated_at' => $date->addDay()])->saveQuietly();

        $isExpense = $entry['category'] instanceof ExpenseCategory;
        $paidOn = CarbonImmutable::parse(min($date->addDays(6)->toDateString(), CarbonImmutable::today()->toDateString()));

        if ($entry['settled'] === 'bank') {
            $line = $this->line(
                $account,
                $paidOn,
                $isExpense ? -$amount : $amount,
                mb_strtoupper(($isExpense ? 'Virement européen vers ' : 'Virement européen de ') . $this->ascii($entry['counterparty'])),
                $entry['counterparty'],
            );

            (new LinkSupportingDocument)($document, $line);
        }

        if ($entry['settled'] === 'cash' && $author instanceof User) {
            $cents = (int) round($amount * 100);
            $cashEntry = CashRegisterEntry::create([
                'cash_register_id' => $register->id,
                'amount' => $isExpense ? -$cents : $cents,
                'reason' => $entry['counterparty'] . ' — ' . $entry['label'],
                'recorded_by_id' => $author->id,
                'notes' => self::MARKER,
            ]);
            $cashEntry->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();

            (new LinkSupportingDocument)($document, $cashEntry);
        }
    }

    /**
     * Money moving to the savings account: internal on both sides, the way
     * the import marks a transfer to another account of the club.
     */
    private function seedSavingsTransfer(BankAccount $current, BankAccount $savings, CarbonImmutable $today): void
    {
        $date = FiscalYear::current()->start()->addMonths(6)->addDays(1);
        $date = $date->greaterThan($today) ? $today : $date;

        $this->line($current, $date, -2000.0, 'VIREMENT VERS COMPTE EPARGNE', 'Compte épargne', internal: true);
        $this->line($savings, $date, 2000.0, 'VIREMENT DU COMPTE COURANT', 'Compte courant', internal: true);
        $this->line($savings, FiscalYear::current()->start()->addDays(1), 22.5, 'INTERETS CREDITEURS', 'CBC Banque');
    }

    /**
     * The till taken to the bank, linked on both sides.
     */
    private function seedTillDeposit(BankAccount $current, CashRegister $register, ?User $author, CarbonImmutable $today): void
    {
        if (! $author instanceof User) {
            return;
        }

        $date = $today->subDays(12);
        $entry = CashRegisterEntry::create([
            'cash_register_id' => $register->id,
            'amount' => -30000,
            'reason' => 'Versement de la caisse à la banque',
            'recorded_by_id' => $author->id,
            'notes' => self::MARKER,
        ]);
        $entry->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();

        $credit = $this->line($current, $date->addDay(), 300.0, 'VERSEMENT ESPECES', null);

        (new LinkCashDepositAction)($credit, $entry);
    }

    /**
     * Lines nobody explained yet: the treasurer's work for the next evening.
     */
    private function seedUnexplainedLines(BankAccount $current, CarbonImmutable $today): void
    {
        foreach ([
            [40, -12.5, 'FRAIS DE GESTION TRIMESTRIELS', 'CBC Banque'],
            [33, -64.9, 'PAIEMENT BANCONTACT BRICO WAVRE', 'Brico Wavre'],
            [25, 50.0, 'VIREMENT DE DUPONT-LATOUR P. — DON', 'Dupont-Latour Pierre'],
            [18, -120.0, 'VIREMENT EUROPEEN VERS CTT LIMAL-WAVRE', 'CTT Limal-Wavre'],
            [9, -27.8, 'PAIEMENT BANCONTACT COLRUYT', 'Colruyt Ottignies'],
            [4, 200.0, 'VIREMENT DE SPORTS ET LOISIRS ASBL', 'Sports et Loisirs ASBL'],
        ] as [$daysAgo, $amount, $description, $counterparty]) {
            $this->line($current, $today->subDays($daysAgo), $amount, $description, $counterparty);
        }
    }

    /**
     * Everything this seeder owns: every supporting document and its files,
     * and the bank lines and till movements it created.
     */
    private function wipe(): void
    {
        SupportingDocument::withTrashed()->get()->each(function (SupportingDocument $document): void {
            Storage::disk('local')->deleteDirectory("supporting-documents/{$document->id}");
            $document->forceDelete();
        });

        CashRegisterEntry::query()->where('notes', self::MARKER)->delete();

        Transaction::withTrashed()->where('import_fingerprint', 'like', 'demo-sd-%')->forceDelete();
    }
}
