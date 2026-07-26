<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Notifications;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingPackProrata;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Subscription $subscription,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Affiliation registered'),
            'body' => __('Your affiliation request has been received'),
            'url' => '#',
            'category' => 'subscription',
            'icon' => 'o-identification',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $season = $this->subscription->season;
        $formula = $this->subscription->is_competitive
            ? __('Competitive')
            : __('Recreational');

        $trainingPacks = $this->subscription->trainingPacks;

        $message = (new MailMessage)
            ->subject(__('Affiliation :season — request registered', ['season' => $season->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('Your affiliation request for season **:season** has been registered.', ['season' => $season->name]))
            ->line(__('The club secretary will process it shortly. You will receive an email as soon as your file is confirmed, with the payment details.'))
            ->line('---')
            ->line(__('**Summary of your request:**'))
            ->line(__('Formule : :formula', ['formula' => $formula]));

        if ($trainingPacks->isNotEmpty()) {
            $packNames = $trainingPacks->pluck('name')->join(', ');
            $message->line(__('Trainings: :packs', ['packs' => $packNames]));
        }

        $estimatedTotal = $this->computeEstimate($trainingPacks);

        return $message
            ->line(__('Estimated amount: :amount € *(before any discount, computed on validation)*', ['amount' => number_format($estimatedTotal, 2, ',', ' ')]))
            ->line(__('If you have any question, feel free to contact the club secretariat.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Estimation volontairement grossière — la remise est annoncée « à la
     * validation » — mais le pro rata, lui, est déjà connu : annoncer le plein
     * tarif à quelqu'un qui rejoint un pack à mi-parcours serait faux.
     */
    private function computeEstimate(Collection $trainingPacks): float
    {
        $basePrice = $this->subscription->is_competitive ? 125.0 : 60.0;
        $prorata = new TrainingPackProrata;

        $packTotal = $trainingPacks->sum(fn (TrainingPack $pack) => $prorata->billableAmount(
            $pack,
            (float) $pack->price,
            $pack->pivot->starts_on,
            $pack->pivot->ends_on,
        ));

        return round($basePrice + $packTotal, 2);
    }
}
