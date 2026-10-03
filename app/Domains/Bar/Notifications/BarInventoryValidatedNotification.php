<?php

declare(strict_types=1);

namespace App\Domains\Bar\Notifications;

use App\Domains\Bar\Models\BarInventory;
use App\Domains\Bar\Models\BarInventoryLine;
use App\Domains\Shared\Enums\BarInventoryCause;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Le récapitulatif d'un inventaire validé, pour le trésorier et les magasiniers.
 *
 * Une correction de stock fait perdre ou gagner de l'argent au club sans passer
 * par la caisse : le trésorier doit le voir, même quand tout tombait juste — il
 * sait alors que l'inventaire a eu lieu. Ce qui vient d'être ajouté au bar tient
 * sur une ligne à part : ce n'est pas un écart.
 *
 * En file d'attente : un relais lent ne doit pas faire attendre celui qui valide,
 * debout dans la réserve.
 *
 * Pas de désabonnement : qui ne veut plus le recevoir rend la fonction.
 */
class BarInventoryValidatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly BarInventory $inventory) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Bar inventory'),
            'body' => $this->subject(),
            'url' => route('bar.inventories.show', $this->inventory),
            'category' => 'bar',
            'icon' => 'o-clipboard-document-check',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lines = $this->inventory->lines()->with('product')->get();
        $added = $lines->filter(fn (BarInventoryLine $line): bool => $line->cause === BarInventoryCause::AddedToBar);
        $gaps = $lines->filter(fn (BarInventoryLine $line): bool => $line->gap !== 0 && $line->cause !== BarInventoryCause::AddedToBar)
            ->sortBy('gap');

        return (new MailMessage)
            ->subject($this->subject())
            ->markdown('mail.bar.inventory-validated', [
                'name' => $notifiable->first_name ?? null,
                'inventory' => $this->inventory,
                'counted' => $lines->count(),
                'totals' => $this->inventory->totals(),
                'gaps' => $gaps->map(fn (BarInventoryLine $line): array => [
                    'name' => $line->product->name,
                    'gap' => $line->gap > 0 ? '+' . $line->gap : (string) $line->gap,
                    'value' => euros($line->gap * (int) $line->unit_price),
                    'cause' => $line->cause?->label(),
                    'note' => $line->note,
                ])->values()->all(),
                'added' => $added->map(fn (BarInventoryLine $line): string => $line->product->name . ' (' . $line->gap . ')')->values()->all(),
                'url' => route('bar.inventories.show', $this->inventory),
            ]);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['mail', 'database'];
    }

    private function subject(): string
    {
        $totals = $this->inventory->totals();
        $date = $this->inventory->opened_at->translatedFormat('j F');

        if ($totals['missing'] === 0 && $totals['surplus'] === 0) {
            return __('Bar inventory of :date: no gap', ['date' => $date]);
        }

        return __('Bar inventory of :date: :missing missing, :surplus surplus (:value)', [
            'date' => $date,
            'missing' => $totals['missing'],
            'surplus' => $totals['surplus'],
            'value' => euros($totals['value']),
        ]);
    }
}
