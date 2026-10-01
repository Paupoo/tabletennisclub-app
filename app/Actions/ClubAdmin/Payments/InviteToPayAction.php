<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Mail\PaymentInvitationEmail;
use Illuminate\Support\Facades\Mail;

/**
 * Réclame une ligne de paiement d'affiliation à ceux qui la règlent.
 *
 * Un mailable ne passe pas par `routeNotificationForMail()` : sans
 * `contactEmails()`, l'invitation partirait vers `email`, null pour un compte
 * géré. Un mineur est joint avec chacun de ses tuteurs, un message par adresse
 * pour qu'aucun parent ne voie celle de l'autre.
 *
 * Une ligne qui n'est plus en attente ne réclame rien : une remise qui couvre
 * tout le complément l'annule sans ramener son montant à zéro.
 */
class InviteToPayAction
{
    /**
     * @return list<string> les adresses réellement écrites, vide si rien n'est parti
     */
    public function __invoke(Payment $payment): array
    {
        if ($payment->status !== 'pending' || $payment->balance() <= 0.0) {
            return [];
        }

        $payment->loadMissing('payable.user.guardians', 'payable.season');

        $subscription = $payment->payable;

        if (! $subscription instanceof Subscription) {
            return [];
        }

        $recipients = $subscription->user->contactEmails();

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(new PaymentInvitationEmail($payment));
        }

        if ($recipients !== []) {
            $payment->increment('invitation_counter');
        }

        return $recipients;
    }
}
