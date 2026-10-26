<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarOrderItem;
use App\Domains\Shared\Models\AppSetting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Mail\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use LogicException;
use RuntimeException;
use stdClass;

class CashSheetService
{
    /**
     * @return array{
     *     days: list<array{date: string, orders_total: int, items_total: int, sold_total_cents: int, payments_total: int, received_total_cents: int}>,
     *     previous: string|null,
     *     next: string|null
     * }
     */
    public function activity(string $selectedDate): array
    {
        $dateExpression = $this->businessDateExpression('created_at');
        $orderDays = BarOrder::query()
            ->selectRaw("{$dateExpression} as business_date, COUNT(*) as orders_total, SUM(total_price) as sold_total_cents")
            ->whereRaw("{$dateExpression} <= ?", [now()->toDateString()])
            ->groupByRaw($dateExpression)
            ->toBase()
            ->get()
            ->keyBy('business_date');

        $itemDateExpression = $this->businessDateExpression('bar_orders.created_at');
        $itemDays = BarOrderItem::query()
            ->join('bar_orders', 'bar_orders.id', '=', 'bar_order_items.order_id')
            ->selectRaw("{$itemDateExpression} as business_date, SUM(bar_order_items.quantity) as items_total")
            ->whereRaw("{$itemDateExpression} <= ?", [now()->toDateString()])
            ->groupByRaw($itemDateExpression)
            ->pluck('items_total', 'business_date');

        $paymentDateExpression = $this->businessDateExpression('COALESCE(paid_at, created_at)');
        $paymentDays = BarOrder::query()
            ->where('is_paid', 1)
            ->selectRaw("{$paymentDateExpression} as business_date, COUNT(*) as payments_total, SUM(CASE WHEN payment_method IS NULL OR payment_method NOT IN ('offered', 'free') THEN total_price ELSE 0 END) as received_total_cents")
            ->whereRaw("{$paymentDateExpression} <= ?", [now()->toDateString()])
            ->groupByRaw($paymentDateExpression)
            ->toBase()
            ->get()
            ->keyBy('business_date');

        $dates = $orderDays->keys()
            ->merge($paymentDays->keys())
            ->unique()
            ->sortDesc()
            ->values();

        $days = $dates->map(fn (string $date): array => [
            'date' => $date,
            'orders_total' => (int) ($orderDays->get($date)?->orders_total ?? 0),
            'items_total' => (int) ($itemDays[$date] ?? 0),
            'sold_total_cents' => (int) ($orderDays->get($date)?->sold_total_cents ?? 0),
            'payments_total' => (int) ($paymentDays->get($date)?->payments_total ?? 0),
            'received_total_cents' => (int) ($paymentDays->get($date)?->received_total_cents ?? 0),
        ])->all();

        $previous = $dates->first(fn (string $date): bool => $date < $selectedDate);
        $next = $dates
            ->filter(fn (string $date): bool => $date > $selectedDate)
            ->sort()
            ->first();

        $visibleDays = collect($days)->take(8);
        $selectedDay = collect($days)->firstWhere('date', $selectedDate);

        if ($selectedDay !== null && $visibleDays->doesntContain('date', $selectedDate)) {
            $visibleDays->push($selectedDay);
        }

        return [
            'days' => $visibleDays->sortByDesc('date')->values()->all(),
            'previous' => $previous,
            'next' => $next,
        ];
    }

    public function build(string $date): array
    {
        $businessDayStart = Carbon::parse($date)->startOfDay()->setTime(6, 0);
        $businessDayEnd = $businessDayStart->copy()->addDay();

        $ordersQuery = BarOrder::query()
            ->where('created_at', '>=', $businessDayStart)
            ->where('created_at', '<', $businessDayEnd);

        $paymentsQuery = BarOrder::query()
            ->where('is_paid', 1)
            ->where(function (Builder $query) use ($businessDayStart, $businessDayEnd): void {
                $query->where(function (Builder $query) use ($businessDayStart, $businessDayEnd): void {
                    $query->where('paid_at', '>=', $businessDayStart)
                        ->where('paid_at', '<', $businessDayEnd);
                })->orWhere(function (Builder $query) use ($businessDayStart, $businessDayEnd): void {
                    $query->whereNull('paid_at')
                        ->where('created_at', '>=', $businessDayStart)
                        ->where('created_at', '<', $businessDayEnd);
                });
            });

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

        $receivedTotalCents = (int) (clone $paymentsQuery)
            ->where(function (Builder $query): void {
                $query->whereNotIn('payment_method', ['offered', 'free'])
                    ->orWhereNull('payment_method');
            })
            ->sum('total_price');

        $unpaidTotalCents = (int) (clone $ordersQuery)
            ->where('is_paid', 0)
            ->sum('total_price');

        $paymentRows = (clone $paymentsQuery)
            ->selectRaw('payment_method, COUNT(*) as transaction_count, SUM(total_price) as total_cents')
            ->groupBy('payment_method')
            ->orderBy('payment_method')
            ->toBase()
            ->get();

        $paymentsTotal = (int) $paymentRows->sum('transaction_count');

        // --- Total quantity sold ---
        $itemsTotal = (int) BarOrderItem::query()
            ->whereHas('order', function (Builder $q) use ($businessDayStart, $businessDayEnd): void {
                $q->where('created_at', '>=', $businessDayStart)
                    ->where('created_at', '<', $businessDayEnd);
            })
            ->sum('quantity');

        // --- Payment method breakdown (paid orders only) ---
        $paymentTotals = $paymentRows->mapWithKeys(fn (stdClass $payment): array => [
            (string) ($payment->payment_method ?? '') => (int) $payment->total_cents,
        ]);

        $cash = (int) ($paymentTotals['cash'] ?? 0);
        $qr = (int) ($paymentTotals['qr'] ?? 0);

        // Support both legacy "free" and current "offered"
        $offered = (int) (($paymentTotals['offered'] ?? 0) + ($paymentTotals['free'] ?? 0));

        // --- Sold products breakdown (full list of sold articles) ---
        $rows = BarOrderItem::query()
            ->selectRaw('
                product_id,
                unit_price,
                SUM(quantity) as total_quantity,
                SUM(total_price) as total_revenue
            ')
            ->with('product:id,name')
            ->whereHas('order', function (Builder $q) use ($businessDayStart, $businessDayEnd): void {
                $q->where('created_at', '>=', $businessDayStart)
                    ->where('created_at', '<', $businessDayEnd);
            })
            ->groupBy('product_id', 'unit_price')
            ->orderByDesc('total_quantity')
            ->get()
            ->map(fn (BarOrderItem $item): array => [
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product->name ?? 'Produit supprimé',
                'quantity' => (int) $item->total_quantity,
                'unit_price_cents' => (int) $item->unit_price,
                'revenue_cents' => (int) $item->total_revenue,
            ]);

        $transactions = BarOrderItem::query()
            ->with([
                'product:id,name',
                'order:id,name,is_paid,paid_at,payment_method,created_by,created_at',
                'order.createdBy:id,first_name,last_name',
            ])
            ->whereHas('order', function (Builder $query) use ($businessDayStart, $businessDayEnd): void {
                $query->where(function (Builder $query) use ($businessDayStart, $businessDayEnd): void {
                    $query->where(function (Builder $query) use ($businessDayStart, $businessDayEnd): void {
                        $query->where('created_at', '>=', $businessDayStart)
                            ->where('created_at', '<', $businessDayEnd);
                    })->orWhere(function (Builder $query) use ($businessDayStart, $businessDayEnd): void {
                        $query->where('is_paid', 1)
                            ->where('paid_at', '>=', $businessDayStart)
                            ->where('paid_at', '<', $businessDayEnd);
                    });
                });
            })
            ->orderBy('order_id')
            ->orderBy('id')
            ->get();

        $otherPaymentsCents = (int) $paymentRows
            ->reject(fn (stdClass $payment): bool => in_array($payment->payment_method, ['cash', 'qr', 'offered', 'free'], true))
            ->sum('total_cents');
        $other = $otherPaymentsCents;

        $summary = [
            'orders_total' => $ordersTotal,
            'orders_paid' => $ordersPaid,
            'orders_unpaid' => $ordersUnpaid,
            'payments_total' => $paymentsTotal,
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
            'other_payments_cents' => $otherPaymentsCents,
            'offered_value_cents' => $offered,
        ];

        $csv = $this->buildCsv($date, $businessDayStart, $businessDayEnd, $summary, $rows, $paymentRows, $transactions);

        return [$summary, $rows, $csv];
    }

    /**
     * Get the default treasurer email, from DB or config fallback.
     */
    public function getDefaultEmail(): ?string
    {
        $value = AppSetting::get('treasurer_email');

        return is_string($value) && $value !== ''
            ? $value
            : config('bar.treasurer_email_default');
    }

    /**
     * Save the default treasurer email.
     */
    public function saveDefaultEmail(string $email): void
    {
        AppSetting::set('treasurer_email', $email);
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
     * Build a detailed CSV report using only persisted bar order data.
     */
    private function buildCsv(
        string $date,
        Carbon $businessDayStart,
        Carbon $businessDayEnd,
        array $summary,
        Collection $productRows,
        Collection $paymentRows,
        Collection $transactions
    ): string {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open a temporary stream for the cashsheet CSV.');
        }

        $writeRow = static function (array $row) use ($handle): void {
            if (fputcsv($handle, $row, ';', '"', '') === false) {
                throw new RuntimeException('Unable to write a row to the cashsheet CSV.');
            }
        };

        try {
            $writeRow(['FEUILLE DE CAISSE']);
            $writeRow(['Date d’activité', $date]);
            $writeRow(['Rapport généré le', now()->format('Y-m-d H:i:s')]);

            $writeRow([]);
            $writeRow(['RÉSUMÉ FINANCIER']);
            $writeRow(['Ventes totales (date de commande)', $this->formatCents((int) $summary['sold_total_cents'])]);
            $writeRow(['Montant impayé (commandes créées ce jour)', $this->formatCents((int) $summary['unpaid_total_cents'])]);
            $writeRow(['Encaissé en espèces (date d’encaissement)', $this->formatCents((int) $summary['by_method_cents']['cash'])]);
            $writeRow(['Encaissé par QR (date d’encaissement)', $this->formatCents((int) $summary['by_method_cents']['qr'])]);
            $writeRow(['Autres modes encaissés (date d’encaissement)', $this->formatCents((int) $summary['other_payments_cents'])]);
            $writeRow(['Total encaissé hors offerts (date d’encaissement)', $this->formatCents((int) $summary['received_total_cents'])]);
            $writeRow(['Valeur des commandes offertes (non encaissée)', $this->formatCents((int) $summary['offered_value_cents'])]);

            if ($productRows->isNotEmpty()) {
                $writeRow([]);
                $writeRow(['VENTES PAR PRODUIT']);
                $writeRow(['Produit', 'Quantité vendue', 'Prix unitaire', 'Total']);

                foreach ($productRows as $row) {
                    $writeRow([
                        $this->spreadsheetSafeText($row['product_name']),
                        $row['quantity'],
                        $this->formatCents($row['unit_price_cents']),
                        $this->formatCents($row['revenue_cents']),
                    ]);
                }
            }

            if ($paymentRows->isNotEmpty()) {
                $writeRow([]);
                $writeRow(['MODES DE PAIEMENT']);
                $writeRow(['Mode de paiement', 'Nombre de transactions', 'Total']);

                foreach ($paymentRows as $payment) {
                    $method = $payment->payment_method;
                    $methodLabel = is_string($method) && $method !== '' ? $method : 'Non renseigné';

                    $writeRow([
                        $this->spreadsheetSafeText($methodLabel),
                        (int) $payment->transaction_count,
                        $this->formatCents((int) $payment->total_cents),
                    ]);
                }
            }

            if ($transactions->isNotEmpty()) {
                $writeRow([]);
                $writeRow(['DÉTAIL DES TRANSACTIONS']);
                $writeRow(['Horodatage', 'Événement', 'Référence', 'Produit / description', 'Quantité', 'Prix unitaire', 'Mode de paiement', 'Total', 'Créée par']);

                foreach ($transactions as $item) {
                    $order = $item->order;
                    $isSaleDay = $order->created_at !== null
                        && $order->created_at->greaterThanOrEqualTo($businessDayStart)
                        && $order->created_at->lessThan($businessDayEnd);
                    $transactionAt = $isSaleDay
                        ? $order->created_at
                        : ($order->paid_at ? Carbon::parse($order->paid_at) : null);
                    $paymentMethod = $order->is_paid
                        ? ($order->payment_method ?: 'Non renseigné')
                        : 'Non payé';

                    $writeRow([
                        $transactionAt?->format('Y-m-d H:i:s') ?? '',
                        $isSaleDay ? 'Vente' : 'Encaissement',
                        "Commande #{$order->id}",
                        $this->spreadsheetSafeText($item->product->name ?? 'Produit supprimé'),
                        (int) $item->quantity,
                        $this->formatCents((int) $item->unit_price),
                        $this->spreadsheetSafeText($paymentMethod),
                        $this->formatCents((int) $item->total_price),
                        $this->spreadsheetSafeText($order->createdBy?->full_name ?? ''),
                    ]);
                }
            }

            rewind($handle);
            $csv = stream_get_contents($handle);

            if ($csv === false) {
                throw new RuntimeException('Unable to read the generated cashsheet CSV.');
            }

            return $csv;
        } finally {
            fclose($handle);
        }
    }

    private function businessDateExpression(string $column): string
    {
        $connectionName = (new BarOrder)->getConnectionName() ?? DB::getDefaultConnection();
        $driver = config("database.connections.{$connectionName}.driver");

        if (! is_string($driver)) {
            throw new LogicException("Unable to determine database driver for cashsheet activity: {$connectionName}");
        }

        return match ($driver) {
            'mysql', 'mariadb' => "DATE(DATE_SUB({$column}, INTERVAL 6 HOUR))",
            'sqlite' => "DATE({$column}, '-6 hours')",
            'pgsql' => "CAST({$column} - INTERVAL '6 hours' AS DATE)",
            default => throw new LogicException("Unsupported database driver for cashsheet activity: {$driver}"),
        };
    }

    private function formatCents(int $cents): string
    {
        $absoluteCents = abs($cents);
        $amount = intdiv($absoluteCents, 100) . '.' . str_pad((string) ($absoluteCents % 100), 2, '0', STR_PAD_LEFT);

        return $cents < 0 ? "-{$amount}" : $amount;
    }

    private function spreadsheetSafeText(string $value): string
    {
        $firstCharacter = ltrim($value, " \t\n\r\0\x0B")[0] ?? '';

        return str_contains('=+-@', $firstCharacter)
            ? "'{$value}"
            : $value;
    }
}
