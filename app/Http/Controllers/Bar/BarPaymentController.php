<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Shared\Enums\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarPaymentController extends Controller
{
    public function pay(Request $request, BarOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'method' => 'required|in:cash,offered,qr',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validated['method'] === 'offered' && empty($validated['reason'])) {
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
            // L'ardoise est réglée : son nom se libère pour la prochaine tournée, sans
            // que l'historique le perde — `name` reste, seule la clé d'unicité part.
            'open_name_key' => null,
        ]);

        return redirect()
            ->route('bar.orders.index')
            ->with('success', 'Paiement enregistré.');
    }

    public function show(Request $request, BarOrder $order, GeneratePaymentQR $generatePaymentQR): View
    {
        // `bar.orders.takeover` : un bar tourne en équipe, et celui qui encaisse n'est
        // presque jamais celui qui a servi. La permission existait, elle est accordée
        // au rôle BARMAN, et personne ne la vérifiait.
        if ((int) $order->created_by !== (int) auth()->id()
            && $request->user()?->can(Permission::BarOrdersTakeover->value) !== true) {
            abort(403);
        }
        // load items + product for display
        $order->load('items.product');

        // Get selected payment method
        $method = $request->input('method');
        $qrCode = null;
        if ($method === 'qr') {
            $payment = new Payment([
                'amount_due' => $order->total_price / 100,
                'reference' => "Bar order #{$order->id}",
            ]);
            $qrCode = $generatePaymentQR($payment);
        }

        return view('bar.payments.index', compact('order', 'method', 'qrCode'));
    }
}
