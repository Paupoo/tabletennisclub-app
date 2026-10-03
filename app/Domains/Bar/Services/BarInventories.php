<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarInventory;
use App\Domains\Bar\Models\BarInventoryLine;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Notifications\BarInventoryValidatedNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\BarInventoryCause;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Les gestes d'un inventaire : ouvrir, compter, dire ce qui s'est passé, valider.
 *
 * Rien ne touche au stock avant la validation : un comptage s'enregistre sur sa
 * ligne, avec le stock attendu à cet instant, et c'est la validation qui écrit
 * l'écart en entrée ou en sortie de stock.
 */
class BarInventories
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * Annuler l'inventaire en cours : rien n'a touché au stock, rien n'y touchera.
     *
     * Ouvert à tous ceux qui gèrent le stock, sans délai — l'écran conseille
     * seulement de prévenir d'abord celui qui l'a ouvert.
     */
    public function cancel(BarInventory $inventory, User $by): void
    {
        if (! $inventory->isInProgress()) {
            return;
        }

        $inventory->update([
            'status' => BarInventory::STATUS_CANCELLED,
            'closed_by' => $by->id,
            'closed_at' => now(),
        ]);
    }

    /**
     * Enregistrer ce qui a été compté sur l'étagère, contre le stock du moment.
     *
     * Le premier comptage d'un produit qui n'a jamais eu de stock n'est pas un
     * écart : c'est le produit qui arrive au bar, et personne n'a à dire pourquoi.
     */
    public function count(BarInventory $inventory, int $productId, int $counted, User $by): BarInventoryLine
    {
        $product = BarProduct::query()->withStock()->findOrFail($productId);
        $gap = $counted - $product->stock;

        // Un recomptage garde la cause déjà dite tant qu'elle explique encore l'écart.
        $cause = $inventory->lines()->where('product_id', $product->id)->first()?->cause;

        if (! $product->stockMovements()->exists()) {
            $cause = BarInventoryCause::AddedToBar;
        }

        return $inventory->lines()->updateOrCreate(
            ['product_id' => $product->id],
            [
                'expected' => $product->stock,
                'counted' => $counted,
                'counted_by' => $by->id,
                'counted_at' => now(),
                'cause' => $cause?->fits($gap) ? $cause : null,
            ],
        );
    }

    /**
     * Dire ce qui s'est passé pour une ligne dont le nombre ne tombe pas juste.
     *
     * @throws \DomainException quand la cause ne peut pas expliquer un écart de ce sens
     */
    public function explain(BarInventory $inventory, int $productId, BarInventoryCause $cause): void
    {
        $line = $inventory->lines()->where('product_id', $productId)->firstOrFail();

        if ($cause === BarInventoryCause::AddedToBar || ! $cause->fits($line->gap)) {
            throw new \DomainException(__('This does not explain a gap in that direction.'));
        }

        $line->update(['cause' => $cause]);
    }

    /**
     * Revenir sur un comptage : le produit redevient « non compté » et garde son stock.
     */
    public function forget(BarInventory $inventory, int $productId): void
    {
        $inventory->lines()->where('product_id', $productId)->delete();
    }

    /**
     * L'inventaire en cours, ou un nouveau s'il n'y en a pas.
     *
     * Deux magasiniers qui ouvrent à la même seconde comptent dans le même : la
     * vérification se prend sous un verrou, faute de ligne à verrouiller quand
     * aucun inventaire n'existe encore.
     */
    public function open(User $by): BarInventory
    {
        return Cache::lock('bar-inventory-open', 10)->block(5, fn (): BarInventory => BarInventory::inProgress() ?? BarInventory::query()->create([
            'status' => BarInventory::STATUS_IN_PROGRESS,
            'opened_by' => $by->id,
            'opened_at' => now(),
        ]));
    }

    /**
     * Aligner le stock sur le comptage : l'écart de chaque ligne entre ou sort.
     *
     * Chaque écart doit avoir reçu sa cause : sans elle, une perte ne se lit plus
     * dans les ventes, et le trésorier reçoit un nombre qu'il ne peut pas expliquer.
     *
     * @throws \DomainException quand un écart n'a pas de cause
     */
    public function validate(BarInventory $inventory, User $by, ?string $comment = null): void
    {
        $unexplained = $inventory->lines->filter(fn (BarInventoryLine $line): bool => $line->gap !== 0 && $line->cause === null);

        if ($unexplained->isNotEmpty()) {
            throw new \DomainException(trans_choice('Say what happened for :count product before validating.|Say what happened for :count products before validating.', $unexplained->count()));
        }

        $inventory = DB::transaction(function () use ($inventory, $by, $comment): BarInventory {
            $inventory = BarInventory::query()->lockForUpdate()->findOrFail($inventory->id);

            if (! $inventory->isInProgress()) {
                throw new \DomainException(__('This inventory is already closed.'));
            }

            foreach ($inventory->lines()->with('product')->get() as $line) {
                $gap = $line->gap;
                $line->update(['unit_price' => $line->product->sale_price]);

                if ($gap > 0) {
                    $this->stockService->addIncomingStock($line->product_id, $gap, 'Inventory', $by->id, $by->id, inventoryId: $inventory->id);
                } elseif ($gap < 0) {
                    // Le comptoir a pu vendre depuis le comptage plus que ce qui restait
                    // compté : on ne sort que ce qui reste, le stock ne passe pas sous zéro.
                    $left = BarProduct::query()->withStock()->findOrFail($line->product_id)->stock;
                    $out = min(-$gap, max(0, $left));

                    if ($out > 0) {
                        $this->stockService->consumeFIFO($line->product_id, $out, 'Inventory', $by->id, $by->id, inventoryId: $inventory->id);
                    }
                }
            }

            $inventory->update([
                'status' => BarInventory::STATUS_VALIDATED,
                'closed_by' => $by->id,
                'closed_at' => now(),
                'comment' => filled($comment) ? trim($comment) : null,
            ]);

            return $inventory;
        });

        $this->tellTheTreasurer($inventory);
    }

    /**
     * Le récapitulatif : au trésorier, aux magasiniers et à celui qui a validé.
     *
     * Le trésorier est la fonction au comité, pas la délégation Trésorerie : on vise
     * qui en répond, pas qui en a le droit technique. Sans trésorier désigné, le
     * récapitulatif part aussi à l'adresse du club, pour qu'il ne reste pas entre
     * magasiniers.
     */
    private function tellTheTreasurer(BarInventory $inventory): void
    {
        $treasurers = User::role(Role::COMMITTEE->value)
            ->where('committee_role', CommitteeRolesEnum::TREASURER->value)
            ->get();

        $recipients = $treasurers
            ->merge(User::role(Role::STORE_KEEPER->value)->get())
            ->push($inventory->closer)
            ->filter()
            ->unique('id');

        $notification = new BarInventoryValidatedNotification($inventory);

        Notification::send($recipients, $notification);

        $clubAddress = Club::own()?->email_contact;

        if ($treasurers->isEmpty() && filled($clubAddress)) {
            Notification::route('mail', $clubAddress)->notify($notification);
        }
    }
}
