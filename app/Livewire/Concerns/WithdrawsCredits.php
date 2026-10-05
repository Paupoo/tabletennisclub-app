<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\Payment\Models\PaymentCredit;
use App\Domains\Shared\Enums\Permission;
use DomainException;
use Illuminate\Support\Facades\Gate;

/**
 * Retirer un virement placé sur la mauvaise créance.
 *
 * Deux portes pour le même geste : le paiement (« Déjà reçu ») et le virement
 * (« Déjà placé »). On découvre l'erreur depuis l'un ou l'autre.
 *
 * La confirmation se déplie sous la ligne plutôt que dans une modale : les deux
 * listes vivent déjà dans une modale, et la suivante s'empilerait dessus.
 */
trait WithdrawsCredits
{
    /** La ligne de crédit dont le retrait attend confirmation. */
    public ?int $withdrawCreditId = null;

    /**
     * Ce que l'écran doit oublier une fois la ligne retirée : ses listes
     * mémoïsées décrivent encore l'état d'avant.
     */
    abstract protected function creditWithdrawn(): void;

    public function askWithdrawCredit(int $creditId): void
    {
        Gate::authorize(Permission::PaymentsReconcile->value);

        $this->withdrawCreditId = $creditId;
    }

    public function cancelWithdrawCredit(): void
    {
        $this->withdrawCreditId = null;
    }

    public function confirmWithdrawCredit(): void
    {
        Gate::authorize(Permission::PaymentsReconcile->value);

        $credit = PaymentCredit::find($this->withdrawCreditId);
        $this->withdrawCreditId = null;

        if (! $credit instanceof PaymentCredit) {
            return;
        }

        try {
            (new AllocateTransactionAction)->withdraw($credit);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->creditWithdrawn();
        $this->success(__('Bank transfer removed from the payment.'));
    }

    /**
     * Ce que le retrait en attente changerait, pour la confirmation.
     *
     * @return array{reopens_payment: bool, written_off: float}|null
     */
    public function withdrawalPreview(PaymentCredit $credit): ?array
    {
        if ($this->withdrawCreditId !== $credit->id) {
            return null;
        }

        return (new AllocateTransactionAction)->previewWithdrawal($credit);
    }
}
