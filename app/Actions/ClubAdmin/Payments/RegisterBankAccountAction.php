<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Payment\Models\BankAccount;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\Shared\Enums\BankAccountType;
use App\Domains\Shared\Support\IbanNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Registers a new club bank account, as the treasurer confirmed it at import.
 *
 * The lines already imported that went to (or came from) that account were
 * taken for ordinary movements, since nobody knew the account was the club's:
 * they become internal transfers now. A savings account is typically
 * registered after the current account's statements have been imported for
 * months.
 */
final class RegisterBankAccountAction
{
    public function __invoke(string $iban, string $name, BankAccountType $type): BankAccount
    {
        return DB::transaction(function () use ($iban, $name, $type): BankAccount {
            $account = BankAccount::create([
                'iban' => $iban,
                'name' => trim($name),
                'type' => $type,
            ]);

            Transaction::query()
                ->where('is_internal', false)
                ->whereNotNull('counterparty_bank_account')
                ->whereRaw("UPPER(REPLACE(REPLACE(counterparty_bank_account, ' ', ''), '-', '')) = ?", [IbanNormalizer::normalize($iban)])
                ->update(['is_internal' => true]);

            return $account;
        });
    }
}
