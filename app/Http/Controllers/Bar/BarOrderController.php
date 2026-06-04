<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Services\StockService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BarOrderController extends Controller
{
    private StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->middleware('auth');
        $this->stockService = $stockService;
    }

    public function cancelEdit()
    {
        session()->forget('cart');
        session()->forget('editing_order_id');

        return redirect()->route('bar.index')
            ->with('success', 'Modification annulée.');
    }

    public function destroy(BarOrder $order)
    {
        if ((int) $order->created_by !== (int) auth()->id()) {
            return back()->with('error', "Vous n'êtes pas autorisé à modifier cette commande.");
        }

        if ($order->is_paid) {
            return back()->with('error', 'Impossible de supprimer une commande payée.');
        }

        DB::transaction(function () use ($order) {
            $order->load('items');
            foreach ($order->items as $item) {
                $this->stockService->restoreFromOrderItem(
                    (int) $item->id,
                    auth()->id()
                );
            }
            $order->items()->delete();
            $order->delete();
        });

        return redirect()->route('bar.orders.index')
            ->with('success', 'Commande supprimée.');
    }

    /**
     * Order history with filters and KPIs.
     */
    public function history(Request $request)
    {
        $period = $request->input('period', 'today');
        $status = $request->input('status', 'all');

        $query = BarOrder::query()->with(['items.product']);

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case '7':
                $query->where('created_at', '>=', now()->subDays(7));
                break;
            case '30':
                $query->where('created_at', '>=', now()->subDays(30));
                break;
            case 'all':
            default:
                // no filter
                break;
        }

        switch ($status) {
            case 'paid':
                $query->where('is_paid', 1);
                break;
            case 'unpaid':
                $query->where('is_paid', 0);
                break;
            case 'all':
            default:
                // no filter
                break;
        }

        $orders = $query
            ->orderByDesc('id')
            ->get();

        $totalRevenue = $orders
            ->where('payment_method', '!=', 'offered')
            ->where('is_paid', 1)
            ->sum('total_price');
        $totalRevenueUnpaid = $orders->where('is_paid', 0)->sum('total_price');
        $totalRevenueOffered = $orders->where('payment_method', 'offered')->sum('total_price');
        $orderCount = $orders->count();

        return view('bar.orders.history', [
            'orders' => $orders,
            'period' => $period,
            'status' => $status,
            'totalRevenue' => $totalRevenue,
            'totalRevenueUnpaid' => $totalRevenueUnpaid,
            'totalRevenueOffered' => $totalRevenueOffered,
            'orderCount' => $orderCount,
            'periodLabels' => [
                'today' => "Aujourd'hui",
                '7' => '7 jours',
                '30' => '30 jours',
                'all' => 'Tout',
            ],
            'statusLabels' => [
                'all' => 'Tous',
                'paid' => 'Payés',
                'unpaid' => 'Non payés',
            ],
        ]);
    }

    /**
     * List open (unpaid) orders.
     */
    public function index()
    {
        $orders = BarOrder::with('items.product')
            ->where('is_paid', 0)
            ->orderByDesc('id')
            ->get();

        return view('bar.orders.index', compact('orders'));
    }

    /**
     * Re-open an unpaid order for modification:
     * - load order items into the session cart
     * - remember which order is being edited
     * - redirect back to the main menu page
     */
    public function modify(BarOrder $order)
    {
        if ((int) $order->created_by !== (int) auth()->id()) {
            return back()->with('error', "Vous n'êtes pas autorisé à modifier cette commande.");
        }

        if ($order->is_paid) {
            return back()->with('error', 'Commande déjà payée.');
        }

        $order->load('items');

        $cart = $order->items
            ->mapWithKeys(fn ($item) => [$item->product_id => (int) $item->quantity])
            ->toArray();

        session()->put('cart', $cart);
        session()->put('editing_order_id', $order->id);

        return redirect()->route('bar.index')
            ->with('success', 'Commande chargée dans le panier pour modification.');
    }
}
