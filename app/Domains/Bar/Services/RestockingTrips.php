<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarRestocking;
use App\Domains\Bar\Models\BarRestockingLine;
use App\Domains\ClubAdmin\ExpenseReports\Actions\SubmitExpenseReport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Les gestes d'une tournée de courses : partir, reprendre, abandonner, rentrer.
 *
 * Une seule tournée à la fois. Deux magasiniers qui tapent « Je fais les courses »
 * à la même seconde ne doivent pas partir tous les deux : le départ se prend sous
 * un verrou, faute de ligne à verrouiller quand aucune tournée n'existe encore.
 */
class RestockingTrips
{
    public function __construct(
        private readonly RestockingList $restockingList,
        private readonly StockService $stockService,
    ) {}

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
     * Rentrer du magasin : ce qui a vraiment été acheté entre en stock, et la tournée se ferme.
     *
     * On ajoute ce qui a été acheté, on ne recale pas sur un comptage : les ventes
     * faites pendant la tournée restent justes. Chaque entrée porte la tournée, pour
     * qu'on sache d'où vient le stock et que le ticket se rapproche de ce qui est
     * arrivé. Une fois close, la tournée ne bouge plus — une erreur se corrige par
     * l'inventaire.
     *
     * Qui a payé se dit à la clôture : `me` soumet dans la même transaction la note
     * de frais de celui qui rentre, pré-remplie par ce qui vient d'entrer en stock —
     * si elle échoue, rien n'est écrit, et la tournée reste ouverte.
     *
     * @param  array<int|string, int|string|null>  $boughtByLine  conditionnements achetés, par ligne de la liste
     * @param  array<int|string, int|string|null>  $extrasByProduct  conditionnements achetés hors liste, par produit
     * @param  array{amount: float, iban: string, files: array<int, UploadedFile>}|null  $claim  le ticket, quand `$paidBy` vaut `me`
     *
     * @throws \DomainException quand la tournée n'est plus en cours, ou n'est pas à celui qui rentre
     */
    public function close(BarRestocking $trip, User $shopper, array $boughtByLine, array $extrasByProduct, string $paidBy, ?array $claim = null): void
    {
        if ($paidBy === BarRestocking::PAID_BY_ME && $claim === null) {
            throw new \DomainException('A refund needs the receipt.');
        }

        DB::transaction(function () use ($trip, $shopper, $boughtByLine, $extrasByProduct, $paidBy, $claim): void {
            $trip = BarRestocking::query()->lockForUpdate()->findOrFail($trip->id);

            if (! $trip->isInProgress() || $trip->shopper_id !== $shopper->id) {
                throw new \DomainException(__('Only the one doing the shopping can close the trip.'));
            }

            $reason = "Shopping trip #{$trip->id}";

            foreach ($trip->lines as $line) {
                $packs = max(0, (int) ($boughtByLine[$line->id] ?? 0));
                $line->update(['bought_packs' => $packs]);

                if ($packs > 0) {
                    $this->stockService->addIncomingStock($line->product_id, $packs * $line->pack_size, $reason, $shopper->id, $shopper->id, $trip->id);
                }
            }

            $onTheList = $trip->lines->pluck('product_id')->all();

            foreach ($extrasByProduct as $productId => $packs) {
                $packs = max(0, (int) $packs);

                if ($packs === 0 || in_array((int) $productId, $onTheList, true)) {
                    continue;
                }

                $product = BarProduct::query()->withStock()->findOrFail((int) $productId);

                $trip->lines()->create([
                    'product_id' => $product->id,
                    'section' => BarRestockingLine::SECTION_EXTRA,
                    'stock_at_start' => $product->stock,
                    'pack_size' => $product->pack_size,
                    'pack_label' => $product->pack_label,
                    'proposed_packs' => 0,
                    'bought_packs' => $packs,
                ]);

                $this->stockService->addIncomingStock($product->id, $packs * $product->pack_size, $reason, $shopper->id, $shopper->id, $trip->id);
            }

            $trip->update(['status' => BarRestocking::STATUS_CLOSED, 'closed_at' => now(), 'paid_by' => $paidBy]);

            if ($paidBy === BarRestocking::PAID_BY_ME) {
                $report = (new SubmitExpenseReport)(
                    author: $shopper,
                    category: ExpenseCategory::Bar,
                    description: $this->describe($trip),
                    amount: $claim['amount'],
                    spentOn: today(),
                    refundIban: $claim['iban'],
                    files: $claim['files'],
                );

                $trip->update(['expense_report_id' => $report->id]);
            }
        });
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

    /**
     * Ce que le valideur lit dans la note : la date, puis ce qui est entré en stock.
     */
    private function describe(BarRestocking $trip): string
    {
        $items = $trip->lines()->with('product')->where('bought_packs', '>', 0)->get()
            ->map(fn (BarRestockingLine $line): string => RestockingList::packsLabel((int) $line->bought_packs, $line->pack_size, $line->pack_label) . ' ' . $line->product->name)
            ->join(', ');

        return Str::limit(__('Bar shopping of :date: :items', ['date' => today()->format('d/m/Y'), 'items' => $items]), 255);
    }
}
