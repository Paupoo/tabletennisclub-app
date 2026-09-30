<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\BankAccount;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\BankAccountType;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\ValueObjects\FiscalYear;
use Database\Seeders\SupportingDocumentSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Carbon::setTestNow('2026-09-30 10:00:00');
    Club::factory()->ownClub()->create(['bank_account' => 'BE23732333208791']);
    Club::forgetOwnClub();
    User::factory()->create(['committee_role' => CommitteeRolesEnum::TREASURER]);
    CashRegister::create(['name' => 'Caisse du club']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('seeds two financial years of documents, with open debts and a receivable', function (): void {
    $this->seed(SupportingDocumentSeeder::class);

    $previous = FiscalYear::current()->previous();

    expect(SupportingDocument::count())->toBeGreaterThanOrEqual(30)
        ->and(SupportingDocument::datedIn($previous)->count())->toBeGreaterThan(10)
        ->and(SupportingDocument::toSettle()->expenses()->count())->toBe(2)
        ->and(SupportingDocument::toSettle()->incomes()->count())->toBe(1)
        ->and(SupportingDocument::has('files')->count())->toBe(SupportingDocument::count())
        ->and(CashRegisterEntry::has('supportingDocuments')->count())->toBe(1)
        ->and(CashRegisterEntry::internal()->count())->toBe(1)
        ->and(BankAccount::where('type', BankAccountType::Savings)->count())->toBe(1)
        ->and(Transaction::internal()->count())->toBe(3)
        ->and(Transaction::unallocated()->count())->toBe(7);
});

it('fills every line with the balance the bank would print', function (): void {
    $this->seed(SupportingDocumentSeeder::class);

    expect(Transaction::whereNull('balance_after')->count())->toBe(0);
});

it('starts over when run again, instead of piling up', function (): void {
    $this->seed(SupportingDocumentSeeder::class);
    $counts = [SupportingDocument::count(), Transaction::count(), CashRegisterEntry::count()];

    $this->seed(SupportingDocumentSeeder::class);

    expect([SupportingDocument::withTrashed()->count(), Transaction::withTrashed()->count(), CashRegisterEntry::count()])->toBe($counts);
});
