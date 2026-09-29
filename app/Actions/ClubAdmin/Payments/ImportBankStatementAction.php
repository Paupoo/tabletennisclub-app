<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Payment\Models\BankImport;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Support\IbanNormalizer;
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
     * @throws \DomainException Quand le fichier n'est pas un relevé lisible.
     */
    public function __invoke(string $path): BankImport
    {
        $statement = (new BankStatementReader)->read($path);

        $this->ensureClubAccount($statement);

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

                if (Transaction::where('import_fingerprint', $fingerprint)->exists()) {
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
     * @param  array{date: ?string, amount: ?string, description: ?string, counterparty_account: ?string, counterparty_name: ?string, structured_reference: ?string, free_reference: ?string}  $row
     */
    public function transactionFrom(array $row, BankImport $bankImport): Transaction
    {
        return Transaction::create([
            'date' => $this->parseDate($row['date']),
            'description' => $row['description'],
            'amount' => $this->parseAmount($row['amount']),
            'counterparty_name' => $row['counterparty_name'],
            'counterparty_bank_account' => $row['counterparty_account'],
            'structured_reference' => $row['structured_reference'],
            'free_reference' => $row['free_reference'],
            'import_fingerprint' => $this->fingerprint($row),
            'bank_import_id' => $bankImport->id,
        ]);
    }

    /**
     * Refuse le relevé d'un autre compte que celui du club.
     *
     * Les exports d'un compte personnel et ceux du club se côtoient dans le
     * même dossier de téléchargements, et portent le même nom au numéro près.
     * Une seule ligne étrangère suffit : un relevé ne mélange jamais deux
     * comptes, donc c'est le fichier qui est le mauvais.
     *
     * Sans IBAN renseigné pour le club, il n'y a rien à comparer ; l'écran le
     * signale.
     *
     * @throws \DomainException
     */
    private function ensureClubAccount(BankStatement $statement): void
    {
        $clubAccount = IbanNormalizer::normalize(Club::ourClub()->value('bank_account'));

        if ($clubAccount === null || $clubAccount === '') {
            return;
        }

        foreach ($statement->rows as $row) {
            $account = IbanNormalizer::normalize($row['account']);

            if (! in_array($account, [null, '', $clubAccount], true)) {
                throw new \DomainException(__('This statement is for account :account, not the club account (:club).', [
                    'account' => IbanNormalizer::format($account),
                    'club' => IbanNormalizer::format($clubAccount),
                ]));
            }
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
}
