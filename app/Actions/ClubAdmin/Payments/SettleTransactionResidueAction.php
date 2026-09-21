<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Payment\Models\Transaction;
use Illuminate\Support\Facades\Auth;

/**
 * Abandonne ce qui reste à affecter sur une ligne de relevé.
 *
 * La troisième sortie d'un reliquat, après « l'affecter ailleurs » et « le
 * rembourser » : le membre a arrondi, le club garde. Rien ne bouge côté
 * paiements — c'est précisément ce qui la distingue d'un trop-perçu, lequel est
 * reconnu sur une ligne et ne peut plus en sortir que par un remboursement.
 */
final class SettleTransactionResidueAction
{
    /**
     * @throws \DomainException
     */
    public function __invoke(Transaction $transaction, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \DomainException(__('A reason is required to write off a residue.'));
        }

        if ($transaction->isSettled()) {
            throw new \DomainException(__('This transaction leaves nothing to write off.'));
        }

        // `forceFill` : ces colonnes décrivent une décision, pas une donnée
        // importée, et rien de ce qui vient du relevé n'a à les atteindre.
        $transaction->forceFill([
            'settled_at' => now(),
            'settled_reason' => $reason,
            'settled_by_id' => Auth::id(),
        ])->save();
    }
}
