<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\OpenRefundAction;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Payment\Models\BankAccount;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\BankAccountType;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use App\Domains\Shared\Support\IbanNormalizer;
use App\Domains\Shared\ValueObjects\FiscalYear;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * The website's money over two financial years, so the financial report has
 * a year to compare with: the previous financial year complete, the current
 * one up to today.
 *
 * - **Affiliations** paid by transfer at the start of the season, licence
 *   and trainings together — the report splits them — a few with a family
 *   credit; this year's still unpaid ones stay pending, and their members
 *   owe the club.
 * - A couple of **affiliation refunds**, deducted from the income.
 * - **Expense reports** accepted and refunded by transfer.
 * - A **tournament**'s entry fees taken at the till.
 *
 * The years differ on purpose, next to what {@see SupportingDocumentSeeder}
 * already varies (the hall 12 % dearer, fewer balls, a new subsidy): more
 * members this year, and a dearer licence.
 *
 * Development only: refuses to run in production. Re-runnable alone —
 * `php artisan db:seed --class=FinancialHistorySeeder` wipes what it seeded
 * (bank lines fingerprinted `demo-fh-…`, payments whose structured reference
 * starts with {@see self::REFERENCE_PREFIX}, their affiliations and expense
 * reports, till movements noted {@see self::MARKER}), seeds again, then
 * recomputes the current account's balances through
 * {@see SupportingDocumentSeeder::fillBalances()}.
 */
class FinancialHistorySeeder extends Seeder
{
    /** Written in the notes of every till movement this seeder creates. */
    public const string MARKER = 'Démo historique financier';

    /**
     * The first digit of every structured reference this seeder writes; the
     * application's own start with 0 (the date follows).
     */
    public const string REFERENCE_PREFIX = '9';

    /**
     * What members declared: category, description, amount.
     *
     * @var list<array{0: ExpenseCategory, 1: string, 2: float}>
     */
    private const array EXPENSES = [
        [ExpenseCategory::Travel, 'Déplacement — interclubs à Tubize', 38.4],
        [ExpenseCategory::Event, 'Lots de la tombola du tournoi', 64.9],
        [ExpenseCategory::Bar, 'Courses du bar — soirée de fin de saison', 112.35],
        [ExpenseCategory::Training, 'Formation moniteur ADEPS — inscription', 95.0],
    ];

    /**
     * How each year differs: affiliations, the licence prices (recreational,
     * competitive), affiliation refunds, expense reports, tournament entries
     * and their fee.
     *
     * @var array{previous: array{affiliations: int, licences: array{0: int, 1: int}, refunds: int, expense_reports: int, entries: int, entry_fee: int}, current: array{affiliations: int, licences: array{0: int, 1: int}, refunds: int, expense_reports: int, entries: int, entry_fee: int}}
     */
    private const array YEARS = [
        'previous' => ['affiliations' => 48, 'licences' => [60, 110], 'refunds' => 2, 'expense_reports' => 4, 'entries' => 18, 'entry_fee' => 10],
        'current' => ['affiliations' => 56, 'licences' => [65, 115], 'refunds' => 1, 'expense_reports' => 3, 'entries' => 24, 'entry_fee' => 12],
    ];

    private int $lines = 0;

    private int $references = 0;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('FinancialHistorySeeder ne tourne jamais en production.');

            return;
        }

        $this->wipe();

        $account = $this->currentAccount();
        $treasurer = User::where('committee_role', CommitteeRolesEnum::TREASURER->value)->first() ?? User::query()->orderBy('id')->firstOrFail();
        $today = CarbonImmutable::today();

        // The allocation records who did it, as the reconciliation screen does.
        Auth::setUser($treasurer);

        foreach (['previous' => FiscalYear::current()->previous(), 'current' => FiscalYear::current()] as $which => $year) {
            $profile = self::YEARS[$which];
            $seasonStart = $this->seasonStartIn($year);

            $affiliations = $this->seedAffiliations($year, $seasonStart, $profile, $account, $today);
            $this->seedRefunds($affiliations, $profile['refunds'], $seasonStart->addMonths(2), $account, $today);
            $this->seedExpenseReports($year, $profile['expense_reports'], $treasurer, $account, $today);
            $this->seedTournamentEntries($year, $profile, $treasurer, $today);
        }

        Auth::forgetUser();

        SupportingDocumentSeeder::fillBalances($account);
    }

    private function claim(Subscription $subscription, float $amount, CarbonImmutable $issuedOn): Payment
    {
        $payment = new Payment([
            'reference' => $this->reference(),
            'amount_due' => $amount,
            'amount_paid' => 0,
            'status' => 'pending',
            'payment_method' => 'electronic',
        ]);
        $payment->payable()->associate($subscription);
        $payment->save();
        $payment->forceFill(['created_at' => $issuedOn, 'updated_at' => $issuedOn])->saveQuietly();

        return $payment;
    }

    private function currentAccount(): BankAccount
    {
        return BankAccount::query()->current()->orderBy('id')->first()
            ?? BankAccount::firstOrCreate(
                ['iban' => IbanNormalizer::normalize(Club::ourClub()->value('bank_account')) ?? 'BE23732333208791'],
                ['name' => 'Compte courant', 'type' => BankAccountType::Current],
            );
    }

    private function line(BankAccount $account, CarbonImmutable $date, float $amount, string $description, ?string $counterparty, ?string $reference = null): Transaction
    {
        return Transaction::create([
            'bank_account_id' => $account->id,
            'date' => $date->toDateString(),
            'description' => $description,
            'amount' => $amount,
            'counterparty_name' => $counterparty,
            'structured_reference' => $reference,
            'import_fingerprint' => sprintf('demo-fh-%04d', ++$this->lines),
        ]);
    }

    /**
     * A structured communication, check digits included, that this seeder
     * alone writes.
     */
    private function reference(): string
    {
        $base = self::REFERENCE_PREFIX . str_pad((string) ++$this->references, 9, '0', STR_PAD_LEFT);
        $check = (int) $base % 97;
        $digits = $base . str_pad((string) ($check === 0 ? 97 : $check), 2, '0', STR_PAD_LEFT);

        return substr($digits, 0, 3) . '/' . substr($digits, 3, 4) . '/' . substr($digits, 7);
    }

    /**
     * The season the affiliations of that year pay for: the one starting in
     * the September inside the financial year, created when the demo seasons
     * happen to skip it.
     */
    private function seasonFor(CarbonImmutable $seasonStart): Season
    {
        return Season::query()->whereDate('start_at', $seasonStart->toDateString())->first()
            ?? Season::query()->whereYear('start_at', $seasonStart->year)->orderBy('id')->first()
            ?? Season::create([
                'name' => $seasonStart->year . '-' . ($seasonStart->year + 1),
                'start_at' => $seasonStart,
                'end_at' => $seasonStart->addYear()->month(6)->endOfMonth()->startOfDay(),
                'is_active' => false,
            ]);
    }

    /**
     * The first September inside the financial year: when affiliations are
     * paid.
     */
    private function seasonStartIn(FiscalYear $year): CarbonImmutable
    {
        $september = CarbonImmutable::create($year->startYear(), 9, 1);

        return $september->lessThan($year->start()) ? $september->addYear() : $september;
    }

    /**
     * Affiliations paid by transfer over the first weeks of the season, the
     * composition varying with the member: recreational or competitive
     * licence, no trainings, one pack or two, now and then a family credit.
     * This year's transfers still to come leave their affiliation pending.
     *
     * @param  array{affiliations: int, licences: array{0: int, 1: int}, refunds: int, expense_reports: int, entries: int, entry_fee: int}  $profile
     * @return list<Subscription> the paid ones
     */
    private function seedAffiliations(FiscalYear $year, CarbonImmutable $seasonStart, array $profile, BankAccount $account, CarbonImmutable $today): array
    {
        $season = $this->seasonFor($seasonStart);
        $members = User::query()
            ->whereDoesntHave('subscriptions', fn (Builder $q): Builder => $q->where('season_id', $season->id))
            ->orderBy('id')
            ->limit($profile['affiliations'])
            ->get();

        $paid = [];

        foreach ($members as $index => $member) {
            $competitive = $index % 3 !== 0;
            $licence = $profile['licences'][$competitive ? 1 : 0];
            $trainings = [0, 120, 220][$index % 3];
            $familyCredit = $index % 7 === 0 ? 20 : 0;
            $due = $licence + $trainings - $familyCredit;
            $paidOn = $seasonStart->addDays(($index * 3) % 55);

            $subscription = Subscription::create([
                'user_id' => $member->id,
                'season_id' => $season->id,
                'status' => 'confirmed',
                'is_competitive' => $competitive,
                'has_other_family_members' => $familyCredit > 0,
                'trainings_count' => $trainings === 0 ? 0 : ($trainings === 120 ? 1 : 2),
                'subscription_price' => $licence,
                'training_unit_price' => 0,
                'family_credit' => $familyCredit,
                'amount_due' => $due,
                'amount_paid' => 0,
            ]);

            $claim = $this->claim($subscription, (float) $due, $paidOn->subWeeks(3));

            if ($paidOn->greaterThan($today) || $paidOn->greaterThan($year->end())) {
                continue;
            }

            (new AllocateTransactionAction)(
                $this->line($account, $paidOn, (float) $due, 'VIREMENT DE ' . mb_strtoupper((string) $member->full_name) . ' — COTISATION', $member->full_name, $claim->reference),
                [$claim->id => (float) $due],
            );

            $paid[] = $subscription;
        }

        return $paid;
    }

    /**
     * Expense reports accepted by the treasurer and refunded by transfer, a
     * few weeks apart over the year.
     */
    private function seedExpenseReports(FiscalYear $year, int $count, User $treasurer, BankAccount $account, CarbonImmutable $today): void
    {
        $declarers = User::query()->orderByDesc('id')->limit($count)->get()->values();

        foreach (array_slice(self::EXPENSES, 0, $count) as $index => [$category, $description, $amount]) {
            $spentOn = $year->start()->addMonths(1 + $index * 3)->addDays(10);
            $refundedOn = $spentOn->addDays(12);

            if ($refundedOn->greaterThan($today)) {
                continue;
            }

            $declarer = $declarers[$index] ?? $treasurer;

            $report = ExpenseReport::factory()->create([
                'user_id' => $declarer->id,
                'category' => $category,
                'description' => $description,
                'amount' => $amount,
                'accepted_amount' => $amount,
                'spent_on' => $spentOn,
                'status' => ExpenseReportStatus::Accepted,
                'decided_by' => $treasurer->id,
                'decided_at' => $spentOn->addDays(3),
            ]);

            $refund = (new OpenRefundAction)->forPayable($report, $amount);
            $refund->forceFill(['reference' => $this->reference()])->save();

            (new AllocateTransactionAction)(
                $this->line($account, $refundedOn, -$amount, 'VIREMENT EUROPEEN — NOTE DE FRAIS', $declarer->full_name),
                [$refund->id => $amount],
            );
        }
    }

    /**
     * A few members who left early, refunded part of what they paid.
     *
     * @param  list<Subscription>  $affiliations
     */
    private function seedRefunds(array $affiliations, int $count, CarbonImmutable $on, BankAccount $account, CarbonImmutable $today): void
    {
        foreach (array_slice(array_reverse($affiliations), 0, $count) as $position => $subscription) {
            $refundedOn = $on->addDays($position * 9);

            if ($refundedOn->greaterThan($today)) {
                return;
            }

            $amount = round((float) $subscription->amount_due / 2, 2);
            $refund = (new OpenRefundAction)->forPayable($subscription, $amount, 'BE68539007547034');
            $refund->forceFill(['reference' => $this->reference()])->save();

            (new AllocateTransactionAction)(
                $this->line($account, $refundedOn, -$amount, 'REMBOURSEMENT PARTIEL DE COTISATION', $subscription->user?->full_name),
                [$refund->id => $amount],
            );
        }
    }

    /**
     * The club tournament's entry fees, taken at the till on the day.
     *
     * @param  array{affiliations: int, licences: array{0: int, 1: int}, refunds: int, expense_reports: int, entries: int, entry_fee: int}  $profile
     */
    private function seedTournamentEntries(FiscalYear $year, array $profile, User $treasurer, CarbonImmutable $today): void
    {
        $tournament = Tournament::query()->orderBy('id')->first();
        $till = CashRegister::query()->orderBy('id')->first();
        $day = $year->start()->addMonths(4)->addDays(14)->setTime(14, 0);

        if (! $tournament instanceof Tournament || ! $till instanceof CashRegister || $day->greaterThan($today)) {
            return;
        }

        for ($entry = 0; $entry < $profile['entries']; $entry++) {
            $movement = CashRegisterEntry::create([
                'cash_register_id' => $till->id,
                'amount' => $profile['entry_fee'] * 100,
                'reason' => 'Inscription au tournoi du club',
                'payable_type' => $tournament->getMorphClass(),
                'payable_id' => $tournament->id,
                'recorded_by_id' => $treasurer->id,
                'notes' => self::MARKER,
            ]);
            $movement->forceFill(['created_at' => $day->addMinutes($entry * 4), 'updated_at' => $day->addMinutes($entry * 4)])->saveQuietly();
        }
    }

    /**
     * Everything this seeder owns, and nothing else.
     */
    private function wipe(): void
    {
        $payments = Payment::query()->where('reference', 'like', self::REFERENCE_PREFIX . '%')->get(['id', 'payable_type', 'payable_id']);

        foreach ($payments as $payment) {
            if ($payment->payable_type === Subscription::class) {
                Subscription::withTrashed()->whereKey($payment->payable_id)->forceDelete();
            }

            if ($payment->payable_type === ExpenseReport::class) {
                ExpenseReport::query()->whereKey($payment->payable_id)->delete();
            }
        }

        Payment::query()->whereKey($payments->pluck('id'))->delete();
        CashRegisterEntry::query()->where('notes', self::MARKER)->delete();
        Transaction::withTrashed()->where('import_fingerprint', 'like', 'demo-fh-%')->forceDelete();
    }
}
