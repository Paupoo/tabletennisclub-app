<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Payment\Models\BankAccount;
use App\Domains\ClubAdmin\Payment\Models\BankImport;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\BankAccountType;
use App\Domains\Shared\Support\IbanNormalizer;
use App\Exceptions\UnknownBankAccount;
use App\Support\Treasury\BankStatement;
use App\Support\Treasury\BankStatementReader;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Fait entrer un relevé bancaire dans la trésorerie.
 *
 * Chaque ligne porte une empreinte calculée sur ses valeurs **brutes** : c'est
 * elle qui écarte une ligne déjà importée quand deux relevés se chevauchent.
 * Son calcul ne doit jamais changer — toutes les lignes en base en portent une.
 */
final class ImportBankStatementAction
{
    /**
     * @throws UnknownBankAccount Quand le relevé est celui d'un compte que le club n'a pas enregistré.
     * @throws \DomainException Quand le fichier n'est pas un relevé lisible.
     */
    public function __invoke(string $path): BankImport
    {
        $statement = (new BankStatementReader)->read($path);

        $this->ensureKnownAccounts($statement);

        return DB::transaction(function () use ($statement): BankImport {
            $bankImport = BankImport::create([
                'user_id' => Auth::id(),
                'new_count' => 0,
                'duplicate_count' => 0,
                'error_count' => 0,
            ]);

            $newCount = 0;
            $duplicateCount = 0;
            $failedRows = [];
            $suspected = [];

            foreach ($statement->rows as $row) {
                $fingerprint = $this->fingerprint($row);

                $alreadyImported = Transaction::where('import_fingerprint', $fingerprint)->first();

                if ($alreadyImported instanceof Transaction) {
                    $this->backfill($alreadyImported, $row);
                    $duplicateCount++;

                    continue;
                }

                $date = $this->parseDate($row['date']);
                $amount = $this->parseAmount($row['amount']);
                $lookAlike = $this->lookAlike($date, $amount, $row['counterparty_account'], $bankImport);

                if ($lookAlike instanceof Transaction) {
                    $suspected[] = [
                        'kind' => BankImport::SUSPECTED_DUPLICATE,
                        'line' => $row['line'],
                        'data' => $row,
                        'transaction_id' => $lookAlike->id,
                    ];

                    continue;
                }

                try {
                    $this->transactionFrom($row, $bankImport);
                    $newCount++;
                } catch (Exception $e) {
                    $failedRows[] = [
                        'line' => $row['line'],
                        'data' => $row,
                        'reason' => $e->getMessage(),
                    ];
                }
            }

            $bankImport->update([
                'new_count' => $newCount,
                'duplicate_count' => $duplicateCount,
                'error_count' => count($failedRows),
                'failed_rows' => [...$failedRows, ...$suspected] ?: null,
            ]);

            return $bankImport;
        });
    }

    /**
     * La transaction d'une ligne de relevé.
     *
     * Publique pour qu'une ligne mise de côté, puis gardée par le trésorier,
     * entre exactement comme elle serait entrée à l'import — même empreinte.
     *
     * @param  array{account?: ?string, date: ?string, amount: ?string, description: ?string, counterparty_account: ?string, counterparty_name: ?string, structured_reference: ?string, free_reference: ?string, balance?: ?string, statement_number?: ?string}  $row
     */
    public function transactionFrom(array $row, BankImport $bankImport): Transaction
    {
        return Transaction::create([
            'bank_account_id' => $this->accountOf($row)?->id,
            'date' => $this->parseDate($row['date']),
            'description' => $row['description'],
            'amount' => $this->parseAmount($row['amount']),
            'balance_after' => $this->parseBalance($row['balance'] ?? null),
            'statement_number' => $this->parseStatementNumber($row['statement_number'] ?? null),
            'is_internal' => $this->isClubAccount($row['counterparty_account']),
            'counterparty_name' => $row['counterparty_name'],
            'counterparty_bank_account' => $row['counterparty_account'],
            'structured_reference' => $row['structured_reference'],
            'free_reference' => $row['free_reference'],
            'import_fingerprint' => $this->fingerprint($row),
            'bank_import_id' => $bankImport->id,
        ]);
    }

    /**
     * The account a line belongs to.
     *
     * A statement without an account column is the club's current account's:
     * that is all the club had before it could hold several.
     *
     * @param  array{account?: ?string}  $row
     */
    private function accountOf(array $row): ?BankAccount
    {
        return BankAccount::findByIban($row['account'] ?? null) ?? $this->defaultAccount();
    }

    /**
     * Fills in what a line imported before the bank's balance and statement
     * number were read is missing. Nothing already known is overwritten.
     *
     * @param  array{account?: ?string, balance?: ?string, statement_number?: ?string}  $row
     */
    private function backfill(Transaction $transaction, array $row): void
    {
        $transaction->bank_account_id ??= $this->accountOf($row)?->id;
        $transaction->balance_after ??= $this->parseBalance($row['balance'] ?? null);
        $transaction->statement_number ??= $this->parseStatementNumber($row['statement_number'] ?? null);

        if ($transaction->isDirty()) {
            $transaction->save();
        }
    }

    /**
     * The account a statement without an account column belongs to: the one on
     * the club record, else the first current account registered.
     */
    private function defaultAccount(): ?BankAccount
    {
        return $this->registeredClubAccount()
            ?? BankAccount::current()->orderBy('id')->first();
    }

    /**
     * Refuses a statement of an account the club has not registered.
     *
     * A personal account's exports lie in the same downloads folder as the
     * club's and bear the same name but for the number. So an unknown account
     * is never imported silently: the screen asks the treasurer whether it is
     * a new club account first. The account written on the club record needs
     * no asking — it is registered here as the current account.
     *
     * @throws UnknownBankAccount
     */
    private function ensureKnownAccounts(BankStatement $statement): void
    {
        $this->registeredClubAccount();

        $unknown = collect($statement->rows)
            ->map(fn (array $row): ?string => IbanNormalizer::normalize($row['account']))
            ->filter()
            ->unique()
            ->reject(fn (string $iban): bool => BankAccount::findByIban($iban) instanceof BankAccount)
            ->values()
            ->all();

        if ($unknown !== []) {
            throw new UnknownBankAccount($unknown);
        }
    }

    /**
     * @param  array{date: ?string, amount: ?string, counterparty_account: ?string, structured_reference: ?string, free_reference: ?string, description: ?string}  $row
     */
    private function fingerprint(array $row): string
    {
        return hash('sha256', implode('|', [
            $row['date'] ?? '',
            $row['amount'] ?? '',
            $row['counterparty_account'] ?? '',
            $row['structured_reference'] ?? '',
            $row['free_reference'] ?? '',
            $row['description'] ?? '',
        ]));
    }

    /**
     * Is this counterparty one of the club's own accounts?
     *
     * Such a line is a transfer between two pockets of the same club: neither
     * an income nor an expense, and nothing a member paid.
     */
    private function isClubAccount(?string $counterpartyAccount): bool
    {
        $iban = IbanNormalizer::normalize($counterpartyAccount);

        return $iban !== null && BankAccount::where('iban', $iban)->exists();
    }

    /**
     * Une transaction déjà en base qui pourrait être cette même ligne, venue
     * par l'autre export.
     *
     * Les deux exports de CBC n'écrivent pas la description de la même façon
     * (remplissage, date glissée dans le texte) : un même virement y prend deux
     * empreintes. Date, montant et compte de la contrepartie, eux, ne bougent
     * pas. Deux vrais virements peuvent pourtant les partager — deux commandes
     * du bar le même jour —, d'où une mise de côté que le trésorier tranche,
     * plutôt qu'un rejet.
     *
     * Les lignes du relevé en cours sont exclues : un export ne se répète
     * jamais, deux lignes semblables y sont deux mouvements.
     */
    private function lookAlike(?string $date, float $amount, ?string $counterpartyAccount, BankImport $bankImport): ?Transaction
    {
        if ($date === null) {
            return null;
        }

        $account = IbanNormalizer::normalize($counterpartyAccount);

        return Transaction::query()
            ->whereDate('date', $date)
            ->where('amount', (int) round($amount * 100))
            ->where(fn (Builder $q): Builder => $q->whereNull('bank_import_id')->orWhere('bank_import_id', '!=', $bankImport->id))
            ->when(
                $account === null || $account === '',
                fn (Builder $q): Builder => $q->whereNull('counterparty_bank_account'),
                fn (Builder $q): Builder => $q->whereRaw("UPPER(REPLACE(REPLACE(counterparty_bank_account, ' ', ''), '-', '')) = ?", [$account]),
            )
            ->orderBy('transactions.id')
            ->first();
    }

    private function parseAmount(?string $value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return (float) str_replace([' ', ','], ['', '.'], $value);
    }

    private function parseBalance(?string $value): ?float
    {
        return $value === null || $value === '' ? null : $this->parseAmount($value);
    }

    private function parseDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Exception) {
                return null;
            }
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date) {
                    return $date->format('Y-m-d');
                }
            } catch (Exception) {
                continue;
            }
        }

        return null;
    }

    /**
     * CBC pads the number with zeros in one export and not in the other: the
     * same statement must carry the same number whichever export brought it.
     */
    private function parseStatementNumber(?string $value): ?string
    {
        $number = ltrim(trim((string) $value), '0');

        return $number === '' ? null : $number;
    }

    /**
     * The account written on the club record, registered as the current
     * account the first time it is needed. Null when the club has none.
     */
    private function registeredClubAccount(): ?BankAccount
    {
        $iban = IbanNormalizer::normalize(Club::ourClub()->value('bank_account'));

        if ($iban === null) {
            return null;
        }

        return BankAccount::firstOrCreate(
            ['iban' => $iban],
            ['name' => __('Current account'), 'type' => BankAccountType::Current],
        );
    }
}
