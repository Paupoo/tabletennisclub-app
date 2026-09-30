<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Finance\Services\FinancialPosition;
use App\Domains\ClubAdmin\Finance\Services\FinancialReport;
use App\Domains\ClubAdmin\Payment\Models\BankAccount;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\ValueObjects\FiscalYear;
use Database\Seeders\FinancialHistorySeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Twelve members, a treasurer among them, a till and a tournament: small
 * enough to work the two years out by hand.
 *
 * Previous year (licences 60 € / 110 €), all twelve paid in September:
 * membership fees 1 090,43 € and trainings 1 349,57 € — the affiliation with
 * a family credit (110 € licence, 120 € of trainings, less 20 €) splits its
 * 210 € as 100,43 / 109,57 — then two half refunds (165 € and 115 €) take
 * back 110 € of fees and 170 € of trainings. The tournament brings 18 × 10 €;
 * four expense reports cost 38,40 + 64,90 + 112,35 + 95,00 €.
 */
beforeEach(function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-09-30 10:00:00');
    Club::factory()->ownClub()->create(['fiscal_year_start_month' => 1]);
    Club::forgetOwnClub();
    Season::create(['name' => '2026-2027', 'start_at' => '2026-09-01', 'end_at' => '2027-06-30', 'is_active' => true]);
    Cache::forget('season.current');
    Tournament::factory()->create();
    CashRegister::create(['name' => 'Caisse du club']);
    User::factory()->create(['committee_role' => CommitteeRolesEnum::TREASURER]);
    User::factory()->count(12 - User::count())->create();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('seeds a complete previous year, split into its postes', function (): void {
    $this->seed(FinancialHistorySeeder::class);

    $previous = FinancialReport::for(FiscalYear::current()->previous());

    expect($previous->incomeByCategory())->toBe(['membership_fees' => 980.43, 'trainings' => 1179.57, 'event_income' => 180.0])
        ->and($previous->membershipFeesByLicence())->toBe(['recreational' => 220.0, 'competitive' => 760.43])
        ->and($previous->expensesByCategory())->toBe(['training' => 95.0, 'event' => 64.9, 'bar' => 112.35, 'travel' => 38.4]);
});

it('seeds the current year up to today, and leaves the transfers still to come unpaid', function (): void {
    $this->seed(FinancialHistorySeeder::class);

    $current = FinancialReport::for(FiscalYear::current());

    // Twelve affiliations; the two due after today (3 October, 4 October) still open.
    expect(Subscription::where('status', 'paid')->whereHas('season', fn ($q) => $q->where('is_active', true))->count())->toBe(10)
        ->and((new FinancialPosition)->membersWithOpenDebt())->toBe(['count' => 2, 'active' => 12])
        ->and($current->incomeByCategory()['event_income'])->toBe(288.0)
        ->and($current->expensesByCategory())->toBe(['event' => 64.9, 'bar' => 112.35, 'travel' => 38.4]);
});

it('fills the balances of the current account, its lines included', function (): void {
    $this->seed(FinancialHistorySeeder::class);

    expect(Transaction::whereNull('balance_after')->count())->toBe(0)
        ->and(Transaction::query()->pluck('bank_account_id')->unique()->all())->toBe([BankAccount::query()->current()->value('id')]);
});

it('starts over when run again, instead of piling up', function (): void {
    $this->seed(FinancialHistorySeeder::class);
    $counts = [Transaction::withTrashed()->count(), Payment::count(), Subscription::withTrashed()->count()];

    $this->seed(FinancialHistorySeeder::class);

    expect([Transaction::withTrashed()->count(), Payment::count(), Subscription::withTrashed()->count()])->toBe($counts);
});
