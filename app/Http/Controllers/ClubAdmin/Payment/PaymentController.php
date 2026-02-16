<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Payment;

use App\Actions\ClubAdmin\Payments\SendPayementInvite;
use App\Http\Controllers\Controller;
use App\Models\ClubAdmin\Payment\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // TODO
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // TODO
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        // TODO
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $payments = Payment::all();

        return view('clubAdmin.payments.index', compact([
            'payments',
        ]));
    }

    public function sendInvite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_id' => 'required|exists:payments,id',
        ]);

        $payment = Payment::find($validated['payment_id']);
        new SendPayementInvite()($payment);

        return back()
            ->with([
                'success' => __('The payment invite has been sent'),
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // TODO
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // TODO
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // TODO
    }
}
