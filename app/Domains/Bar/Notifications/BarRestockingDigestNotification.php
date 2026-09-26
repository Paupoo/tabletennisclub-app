<?php

declare(strict_types=1);

namespace App\Domains\Bar\Notifications;

use App\Domains\Bar\Models\BarRestocking;
use App\Domains\Bar\Services\RestockingList;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Le digest « stock bas » du samedi : ce qui doit être acheté pour remplir le bar.
 *
 * Seulement la section « À acheter » : « Si vous avez la place » n'est pas une
 * raison d'aller au magasin, l'écran la montre à qui y va de toute façon. Quand
 * une tournée est en cours, le mail le dit en tête — qu'on ne parte pas en double,
 * ou qu'on la reprenne en connaissance de cause.
 *
 * Pas de désabonnement : qui ne veut plus le recevoir rend la délégation.
 *
 * @phpstan-import-type RestockingLine from RestockingList
 */
class BarRestockingDigestNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<RestockingLine>  $toBuy
     */
    public function __construct(
        public readonly array $toBuy,
        public readonly ?BarRestocking $tripInProgress,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Bar shopping'),
            'body' => trans_choice(':count product must be bought for the bar.|:count products must be bought for the bar.', count($this->toBuy), ['count' => count($this->toBuy)]),
            'url' => route('bar.restocking.index'),
            'category' => 'bar',
            'icon' => 'o-shopping-cart',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(trans_choice('Bar — :count product to buy|Bar — :count products to buy', count($this->toBuy), ['count' => count($this->toBuy)]))
            ->markdown('mail.bar.restocking-digest', [
                'notifiable' => $notifiable,
                'lines' => array_map(fn (array $line): array => [
                    'name' => $line['name'],
                    'category' => $line['category'],
                    'packs' => RestockingList::packsLabel($line['packs'], $line['pack_size'], $line['pack_label']),
                    'units' => $line['units'],
                ], $this->toBuy),
                'trip' => $this->tripInProgress === null ? null : __(':name is already doing the shopping, since :date.', [
                    'name' => $this->tripInProgress->shopper->full_name,
                    'date' => $this->tripInProgress->started_at->translatedFormat('l j F'),
                ]),
                'url' => route('bar.restocking.index'),
            ]);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
