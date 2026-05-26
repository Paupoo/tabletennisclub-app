<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;
use App\Http\Controllers\Controller;
use App\Models\Bar\BarOrder;
use Illuminate\Http\Request;

class BarOrderController extends Controller
{
    public function index()
    {
        $orders = BarOrder::with('items.product')
        ->where('is_paid', 0)
        ->orderByDesc('id')
        ->get();

        return view('bar.orders.index', compact('orders'));
    }
    public function pay(Request $request, BarOrder $order)
    {
        // Mark the order as paid
        $order->is_paid = true;
        $order->paid_at = now();
        $order->payment_method = $request->input('method');
        $order->save();

        return redirect()->route('bar.orders.index')->with('success', 'Commande payée avec succès.');
    }
}

