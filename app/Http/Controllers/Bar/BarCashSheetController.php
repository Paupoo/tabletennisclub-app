<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarOrder;
use Illuminate\Http\Request;

class BarCashSheetController extends Controller
{
    public function index(Request $request)
    {
        // 1. Get selected date (default = today)
        $date = $request->input('date', now()->toDateString());

        // 2. Load orders for that day
        $orders = BarOrder::with('items')
            ->whereDate('created_at', $date)
            ->get();

        // 3. Counts
        $paidOrders = $orders->where('is_paid', 1);
        $unpaidOrders = $orders->where('is_paid', 0);

        $paidCount = $paidOrders->count();
        $unpaidCount = $unpaidOrders->count();

        // 4. Quantities sold
        $itemsSold = $orders->sum(function ($order) {
            return $order->items->sum('quantity');
        });

        // 5. Totals
        $totalSold = $orders->sum('total_price');
        $totalPaid = $paidOrders->sum('total_price');
        $totalUnpaid = $unpaidOrders->sum('total_price');

        // 6. Payment breakdown
        $totalCash = $paidOrders
            ->where('payment_method', 'cash')
            ->sum('total_price');

        $totalQr = $paidOrders
            ->where('payment_method', 'qr')
            ->sum('total_price');

        $totalOther = $paidOrders
            ->where('payment_method', 'other')
            ->sum('total_price');

        $totalFree = $paidOrders
            ->where('payment_method', 'free')
            ->sum('total_price');

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