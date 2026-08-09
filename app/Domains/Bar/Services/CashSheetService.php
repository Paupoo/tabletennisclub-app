<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarOrderItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Mail\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class CashSheetService
{
    public function build(string $date): array
    {
        $date = Carbon::parse($date)->toDateString();

        $ordersQuery = BarOrder::query()
            ->whereDate('created_at', $date);

        // --- Orders summary ---
        $ordersTotal = (clone $ordersQuery)->count();

        $ordersPaid = (clone $ordersQuery)
            ->where('is_paid', 1)
            ->count();

        $ordersUnpaid = (clone $ordersQuery)
            ->where('is_paid', 0)
            ->count();

        // --- Sales totals ---
        $soldTotalCents = (int) (clone $ordersQuery)->sum('total_price');

        $receivedTotalCents = (int) (clone $ordersQuery)
            ->where('is_paid', 1)
            ->sum('total_price');

        $unpaidTotalCents = (int) (clone $ordersQuery)
            ->where('is_paid', 0)
            ->sum('total_price');

        // --- Total quantity sold ---
        $itemsTotal = (int) BarOrderItem::query()
            ->whereHas('order', function (Builder $q) use ($date): void {
                $q->whereDate('created_at', $date);
            })
            ->sum('quantity');

        // --- Payment method breakdown (paid orders only) ---
        $paymentTotals = (clone $ordersQuery)
            ->where('is_paid', 1)
            ->selectRaw('payment_method, SUM(total_price) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $cash = (int) ($paymentTotals['cash'] ?? 0);
        $qr = (int) ($paymentTotals['qr'] ?? 0);
        $other = (int) ($paymentTotals['other'] ?? 0);

        // Support both legacy "free" and current "offered"
        $offered = (int) (($paymentTotals['offered'] ?? 0) + ($paymentTotals['free'] ?? 0));

        // --- Sold products breakdown (full list of sold articles) ---
        $rows = BarOrderItem::query()
            ->selectRaw('
                product_id,
                SUM(quantity) as total_quantity,
                SUM(total_price) as total_revenue
            ')
            ->with('product:id,name')
            ->whereHas('order', function (Builder $q) use ($date): void {
                $q->whereDate('created_at', $date);
            })
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->get()
            ->map(fn (BarOrderItem $item): array => [
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product->name ?? 'Produit supprimé',
                'quantity' => (int) $item->total_quantity,
                'revenue_cents' => (int) $item->total_revenue,
            ]);

        $summary = [
            'orders_total' => $ordersTotal,
            'orders_paid' => $ordersPaid,
            'orders_unpaid' => $ordersUnpaid,
            'items_total' => $itemsTotal,
            'sold_total_cents' => $soldTotalCents,
            'received_total_cents' => $receivedTotalCents,
            'unpaid_total_cents' => $unpaidTotalCents,
            'by_method_cents' => [
                'cash' => $cash,
                'qr' => $qr,
                'other' => $other,
                'offered' => $offered,
            ],
            'offered_value_cents' => $offered,
        ];

        $csv = $this->buildCsv($rows);

        return [$summary, $rows, $csv];
    }

    /**
     * Get the default treasurer email, from DB or config fallback.
     */
    public function getDefaultEmail(): ?string
    {
        if (Schema::hasTable('settings')) {
            $value = DB::table('settings')
                ->where('key', 'treasurer_email')
                ->value('value');

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return config('bar.treasurer_email_default');
    }

    /**
     * Save a default treasurer email in a simple settings table if it exists.
     * Safe fallback: do nothing if the table does not exist.
     */
    public function saveDefaultEmail(string $email): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->updateOrInsert(
            ['key' => 'treasurer_email'],
            [
                'value' => $email,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * Send the cash sheet CSV by email.
     */
    public function sendCsv(
        string $to,
        string $subject,
        string $body,
        string $date,
        string $csv
    ): bool {
        try {
            Mail::raw($body, function (Message $message) use ($to, $subject, $date, $csv): void {
                $message->to($to)
                    ->subject($subject)
                    ->attachData(
                        $csv,
                        "cash-sheet-{$date}.csv",
                        ['mime' => 'text/csv']
                    );
            });

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Build a CSV export from the sold products rows.
     */
    private function buildCsv(Collection $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'Produit',
            'Quantité vendue',
            'Total (cents)',
        ], ';', escape: '\\');

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['product_name'],
                $row['quantity'],
                $row['revenue_cents'],
            ], ';', escape: '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }
}
