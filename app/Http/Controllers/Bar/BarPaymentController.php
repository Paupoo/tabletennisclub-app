<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bar\BarPayment;
use App\Models\Bar\BarOrder;
use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Models\ClubAdmin\Payment\Payment;

class BarPaymentController extends Controller
{
    public function show(Request $request, BarOrder $order, GeneratePaymentQR $generatePaymentQR)
    {
        // load items + product for display
        $order->load('items.product');

        //Get selected payment method
        $method = $request->input('method');
        $qrCode = null;
        if ($method === 'qr') {
            $payment = new Payment([
                'amount_due' => $order->total_price,
                'reference' => "Bar order #{$order->id}",
            ]);
            $qrCode = $generatePaymentQR($payment);
        }

        return view('bar.payments.index', compact('order', 'method', 'qrCode'));
    }


    public function pay(Request $request, BarOrder $order)
    {
        $validated = $request->validate([
            'method' => 'required|in:cash,offered,qr',
            'reason' => 'nullable|string|max:255',
        ]);

        if($validated['method'] === 'offered' && empty($validated['reason'])) {
            return back()
            ->with('error', 'Veuillez fournir une raison pour un paiement offert.')
            ->withinput();
        }
        
        if ($order->is_paid) {
            return back()->with('error', 'Commande déjà payée.');
        }

        $order->update([
            'is_paid' => 1,
            'paid_at' => now(),
            'payment_method' => $validated['method'],
            'reason' => $validated['method'] === 'offered' ? $validated['reason'] : null,
        ]);

        return redirect()
            ->route('bar.orders.index')
            ->with('success', 'Paiement enregistré.');
    }

}