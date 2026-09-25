<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use Illuminate\Database\Seeder;

/**
 * Une cotisation à moitié payée, et le virement qui l'a mise là.
 *
 * Le cas le plus fréquent d'un vrai club : le membre verse une partie en
 * septembre, le reste en novembre, et les deux relevés arrivent à deux mois
 * d'écart. Sur une base fraîche il n'existait qu'après avoir importé deux fois
 * à la main — ni le versement partiel du relevé, qui n'apporte jamais la
 * suite, ni les deux virements de même référence, qui arrivent ensemble sur
 * une créance intacte, ne le mettent en scène.
 *
 * Le versement passe par l'action plutôt que par une écriture directe : la
 * fiche du paiement doit pouvoir montrer d'où viennent ces euros, sinon le
 * trésorier lit un solde qu'il ne peut pas vérifier.
 */
class PartiallySettledClaimSeeder extends Seeder
{
    private const float SHARE = 0.6;

    public function run(): void
    {
        if (Payment::where('status', 'pending')->where('amount_paid', '>', 0)->exists()) {
            $this->command?->info('A partly settled claim already exists — nothing to do.');

            return;
        }

        $claim = Payment::where('status', 'pending')
            ->where('payable_type', Subscription::class)
            ->where('amount_paid', 0)
            ->orderBy('id')
            ->first();

        if (! $claim instanceof Payment) {
            $this->command?->warn('No open affiliation claim to settle in part.');

            return;
        }

        $amount = round((float) $claim->amount_due * self::SHARE, 2);
        $payer = $claim->payable?->user;

        $transaction = Transaction::create([
            'date' => now()->subMonths(2)->toDateString(),
            'description' => 'VIREMENT EN VOTRE FAVEUR',
            'amount' => $amount,
            'counterparty_name' => $payer?->full_name ?? 'Membre',
            'counterparty_bank_account' => $payer?->iban,
            'structured_reference' => $claim->reference,
        ]);

        (new AllocateTransactionAction)($transaction, [$claim->id => $amount]);

        $this->command?->info(sprintf(
            'Claim %s partly settled: %s of %s.',
            $claim->reference,
            number_format($amount, 2),
            number_format((float) $claim->amount_due, 2),
        ));
    }
}
