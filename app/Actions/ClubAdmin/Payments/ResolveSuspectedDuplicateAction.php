<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Payment\Models\BankImport;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Tranche une ligne que l'import a mise de côté comme doublon probable.
 *
 * Gardée, elle entre comme elle serait entrée à l'import et compte parmi les
 * nouvelles ; écartée, elle rejoint les doublons ignorés. Dans les deux cas
 * elle quitte la liste d'attente : une ligne tranchée ne se tranche plus.
 */
final class ResolveSuspectedDuplicateAction
{
    /**
     * @throws \DomainException Quand la ligne n'attend plus de décision.
     */
    public function __invoke(BankImport $bankImport, int $line, bool $keep): ?Transaction
    {
        return DB::transaction(function () use ($bankImport, $line, $keep): ?Transaction {
            $bankImport = BankImport::lockForUpdate()->findOrFail($bankImport->id);

            $rows = $bankImport->failed_rows ?? [];
            $index = array_find_key(
                $rows,
                fn (array $row): bool => ($row['kind'] ?? null) === BankImport::SUSPECTED_DUPLICATE && $row['line'] === $line,
            );

            if ($index === null) {
                throw new \DomainException(__('This line is no longer waiting for a decision.'));
            }

            $transaction = $keep
                ? (new ImportBankStatementAction)->transactionFrom($rows[$index]['data'], $bankImport)
                : null;

            unset($rows[$index]);

            $bankImport->update([
                'failed_rows' => array_values($rows) ?: null,
                'new_count' => $bankImport->new_count + ($keep ? 1 : 0),
                'duplicate_count' => $bankImport->duplicate_count + ($keep ? 0 : 1),
            ]);

            return $transaction;
        });
    }
}
