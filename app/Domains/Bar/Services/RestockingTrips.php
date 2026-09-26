<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarRestocking;
use App\Domains\Bar\Models\BarRestockingLine;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Les gestes d'une tournée de courses : partir, reprendre, abandonner, rentrer.
 *
 * Une seule tournée à la fois. Deux magasiniers qui tapent « Je fais les courses »
 * à la même seconde ne doivent pas partir tous les deux : le départ se prend sous
 * un verrou, faute de ligne à verrouiller quand aucune tournée n'existe encore.
 */
class RestockingTrips
{
    public function __construct(private readonly RestockingList $restockingList) {}

    /**
     * Libérer la liste : la tournée n'aura pas lieu, ou plus.
     *
     * Ouvert à tous et sans délai — ce sont des adultes. L'écran conseille
     * seulement d'appeler d'abord celui qui y était.
     */
    public function abandon(BarRestocking $trip, User $by): void
    {
        if (! $trip->isInProgress()) {
            return;
        }

        $trip->update([
            'status' => BarRestocking::STATUS_ABANDONED,
            'abandoned_by' => $by->id,
            'closed_at' => now(),
        ]);
    }

    /**
     * Figer la liste du moment au nom de celui qui part.
     *
     * @throws \DomainException quand une tournée est déjà en cours, ou que rien n'est à acheter
     */
    public function start(User $shopper): BarRestocking
    {
        return Cache::lock('bar-restocking-start', 10)->block(5, function () use ($shopper): BarRestocking {
            if (BarRestocking::inProgress() !== null) {
                throw new \DomainException(__('Someone is already doing the shopping.'));
            }

            $list = $this->restockingList->current();

            if ($list['to_buy'] === []) {
                throw new \DomainException(__('Nothing needs buying right now.'));
            }

            return DB::transaction(function () use ($shopper, $list): BarRestocking {
                $trip = BarRestocking::query()->create([
                    'status' => BarRestocking::STATUS_IN_PROGRESS,
                    'shopper_id' => $shopper->id,
                    'started_at' => now(),
                ]);

                foreach ([BarRestockingLine::SECTION_TO_BUY => $list['to_buy'], BarRestockingLine::SECTION_IF_ROOM => $list['if_room']] as $section => $lines) {
                    foreach ($lines as $line) {
                        $trip->lines()->create([
                            'product_id' => $line['product_id'],
                            'section' => $section,
                            'stock_at_start' => $line['stock'],
                            'pack_size' => $line['pack_size'],
                            'pack_label' => $line['pack_label'],
                            'proposed_packs' => $line['packs'],
                        ]);
                    }
                }

                return $trip;
            });
        });
    }

    /**
     * Prendre la tournée à son compte : la liste et les cases cochées restent.
     */
    public function takeOver(BarRestocking $trip, User $shopper): void
    {
        if (! $trip->isInProgress()) {
            return;
        }

        $trip->update(['shopper_id' => $shopper->id]);
    }
}
