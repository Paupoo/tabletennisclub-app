<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarOrder;
use App\Models\Bar\BarOrderItem;
use Illuminate\Http\Request;

class BarCashSheetController extends Controller
{
    public function index(Request $request)
    {
        // 1. Get selected date (default = today)
        $validated = $request->validate([
            'date' => 'nullable|date'
        ]);
        $date = $validated['date'] ?? now()->toDateString();

        // 2. Load orders for that day
        $ordersQuery = BarOrder::query()
            ->whereDate('created_at', $date);

        // 3. Counts
        $paidCount = (clone $ordersQuery)
            -> where('is_paid', 1)
            ->count();
        $unpaidCount = (clone $ordersQuery)
            -> where('is_paid', 0)
            ->count();

        // 4. Quantities sold
        $itemsSold = BarOrderItem::query()
        ->whereHas('order', function ($q) use ($date) {
            $q->whereDate('created_at', $date);
            })
            ->sum('quantity');

        // 5. Totals
        $totalSold = (clone $ordersQuery)->sum('total_price');

        $totalPaid = (clone $ordersQuery)
            ->where('is_paid', 1)
            ->sum('total_price');

        $totalUnpaid = (clone $ordersQuery)
            ->where('is_paid', 0)
            ->sum('total_price');


        // 6. Payment breakdown
        $paymentTotals = (clone $ordersQuery)
        ->where('is_paid', 1)
        ->selectRaw('payment_method, SUM(total_price) as total')
        ->groupBy('payment_method')
        ->pluck('total', 'payment_method');

        $totalCash = $paymentTotals['cash'] ?? 0;
        $totalQr = $paymentTotals['qr'] ?? 0;
        $totalOther = $paymentTotals['other'] ?? 0;
        $totalFree = $paymentTotals['free'] ?? 0;


        // 7. Return view
        
return view('bar.cashSheet.index', [
            'date' => $date,
            'paidCount' => $paidCount,
            'unpaidCount' => $unpaidCount,
            'itemsSold' => $itemsSold,
            'totalSold' => $totalSold,
            'totalPaid' => $totalPaid,
            'totalCash' => $totalCash,
            'totalQr' => $totalQr,
            'totalOther' => $totalOther,
            'totalFree' => $totalFree,
            'totalUnpaid' => $totalUnpaid,
        ]);

    }
}