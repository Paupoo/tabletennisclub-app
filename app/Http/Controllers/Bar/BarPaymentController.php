<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domains\Bar\Models\BarPayment;
use App\Domains\Bar\Models\BarOrder;

class BarPaymentController extends Controller
{
    public function show(BarOrder $order)
    {
        // load items + product for display
        $order->load('items.product');

        return view('bar.payments.index', compact('order'));
    }
}