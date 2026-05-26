<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarOrder;
use App\Models\Bar\BarStockMovement;
use Illuminate\Http\Request;

class BarOrderController extends Controller
{
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
     * Mark an order as paid and store the payment metadata.
     */
    public function pay(Request $request, BarOrder $order)
    {
        $order->update([
            'is_paid' => 1,
            'paid_at' => now(),
            'payment_method' => $request->input('method'),
        ]);

        return redirect()->route('bar.orders.index')
            ->with('success', 'Commande payée avec succès.');
    }

    /**
     * Re-open an unpaid order for modification:
     * - load order items into the session cart
     * - remember which order is being edited
     * - redirect back to the main menu page
     */
    public function modify(BarOrder $order)
    {
        if ($order->is_paid) {
            return back()->with('error', 'Commande déjà payée.');
        }

        $order->load('items');

        $cart = [];
        foreach ($order->items as $item) {
            $cart[$item->product_id] = (int) $item->quantity;
        }

        session()->put('cart', $cart);
        session()->put('editing_order_id', $order->id);

        return redirect()->route('bar.index')
            ->with('success', 'Commande chargée dans le panier pour modification.');
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
        if ($order->is_paid) {
            return back()->with('error', 'Impossible de supprimer une commande payée.');
        }

        $order->load('items');

        foreach ($order->items as $item) {
            BarStockMovement::create([
                'product_id'    => $item->product_id,
                'quantity'      => $item->quantity,
                'movement_type' => 'IN', // ✅ RESTORE stock
                'reason'        => "Order #{$order->id} supprimée",
                'created_by'    => null,
                'modified_by'   => auth()->id(),
            ]);
        }

        $order->items()->delete();
        $order->delete();

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

        $query = BarOrder::with(['items.product']);

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

        $totalRevenue = $orders->where('is_paid', 1)->sum('total_price');
        $totalRevenueUnpaid = $orders->where('is_paid', 0)->sum('total_price');
        $orderCount = $orders->count();

        return view('bar.orders.history', [
            'orders' => $orders,
            'period' => $period,
            'status' => $status,
            'totalRevenue' => $totalRevenue,
            'totalRevenueUnpaid' => $totalRevenueUnpaid,
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
}
