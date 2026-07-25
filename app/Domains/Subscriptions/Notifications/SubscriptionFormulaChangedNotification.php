<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Notifications;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an admin changes the formula of an affiliation that has already been
 * invoiced. Not sent while the affiliation is still awaiting validation: nothing
 * has been announced to the member yet, so there is nothing to correct.
 */
class SubscriptionFormulaChangedNotification extends Notification
{
    use Queueable;

    /**
     * @param  float  $delta  positive when the member owes a complement, negative when the club owes a refund
     */
    public function __construct(
        public readonly Subscription $subscription,
        public readonly float $delta = 0.0,
        public readonly ?string $paymentReference = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Formule d\'affiliation modifiée'),
            'body' => $this->subscription->is_competitive
                ? __('Votre affiliation est passée en compétition')
                : __('Votre affiliation est passée en récréatif'),
            'url' => route('admin.user.registration-management', $this->subscription->user_id),
            'category' => 'subscription',
            'icon' => 'o-identification',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $season = $this->subscription->season;

        $mail = (new MailMessage)
            ->subject(__('Affiliation :season — changement de formule', ['season' => $season->name]))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->first_name]))
            ->line($this->subscription->is_competitive
                ? __('Votre affiliation pour la saison **:season** est désormais une affiliation **compétition**.', ['season' => $season->name])
                : __('Votre affiliation pour la saison **:season** est désormais une affiliation **récréative**.', ['season' => $season->name]));

        if ($this->delta > 0) {
            $mail->line(__('Un complément de **:amount €** reste à verser.', [
                'amount' => number_format($this->delta, 2),
            ]));

            if ($this->paymentReference !== null) {
                $mail->line(__('Merci d\'indiquer la communication structurée :reference lors de votre virement.', [
                    'reference' => $this->paymentReference,
                ]));
            }
        }

        if ($this->delta < 0) {
            $mail->line(__('Un remboursement de **:amount €** va vous être effectué.', [
                'amount' => number_format(abs($this->delta), 2),
            ]));

            if (! $notifiable->iban) {
                $mail->line(__('Nous n\'avons pas de compte bancaire enregistré à votre nom : merci de communiquer votre IBAN au secrétariat pour recevoir le remboursement.'));
            }
        }

        return $mail->line(__('Pour toute question, répondez simplement à cet e-mail.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
