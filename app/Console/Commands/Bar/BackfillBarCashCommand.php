<?php

declare(strict_types=1);

namespace App\Console\Commands\Bar;

use App\Actions\Bar\RecordBarOrderPayment;
use App\Domains\Bar\Models\BarOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fait entrer dans la caisse du club le cash que le bar a encaissé avant que
 * chaque vente en espèces n'y écrive son mouvement.
 *
 * Ces billets sont dans le tiroir, mais ni la caisse ni la rubrique « Bar » du
 * rapport financier ne les connaissent. Le jour de départ est obligatoire : les
 * premières commandes de la production sont des essais, et c'est à celui qui
 * lance la reprise de dire où commence le vrai argent.
 *
 * Rejouable sans dommage : une commande qui a déjà son mouvement est laissée
 * telle quelle.
 */
class BackfillBarCashCommand extends Command
{
    protected $description = 'Bring the cash the bar took since a given day into the club till.';

    protected $signature = 'bar:backfill-cash
        {since : The first day whose cash orders are brought in (Y-m-d)}
        {--dry-run : Count what would be written, write nothing}';

    public function handle(RecordBarOrderPayment $recordPayment): int
    {
        $orders = BarOrder::query()
            ->where('is_paid', true)
            ->where('payment_method', RecordBarOrderPayment::CASH_METHOD)
            ->where('paid_at', '>=', Carbon::parse((string) $this->argument('since'))->startOfDay())
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('cash_register_entries')
                ->where('payable_type', BarOrder::class)
                ->whereColumn('payable_id', 'bar_orders.id'))
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Nothing to backfill — every cash order since that day is already in the till.');

            return self::SUCCESS;
        }

        $euros = number_format($orders->sum('total_price') / 100, 2, '.', '');

        if ($this->option('dry-run')) {
            $this->info("{$orders->count()} cash orders ({$euros} €) would be brought into the till.");

            return self::SUCCESS;
        }

        $created = 0;

        foreach ($orders as $order) {
            $entry = $recordPayment->putInTill($order);

            if ($entry === null) {
                continue;
            }

            // Daté du soir où le bar a encaissé : c'est le jour où l'argent est
            // entré, celui que le rapport financier compte.
            $entry->forceFill([
                'created_at' => $order->paid_at,
                'updated_at' => $order->paid_at,
            ])->save();

            $created++;
        }

        $this->info("{$created} cash orders ({$euros} €) brought into the till.");

        return self::SUCCESS;
    }
}
