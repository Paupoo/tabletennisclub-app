<?php

namespace App\Http\Controllers;

use App\Actions\Payments\SendPayementInvite;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $payments = Payment::all();

        return view('admin.payments.index', compact([
            'payments',
        ]));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function sendInvite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_id' => 'required|exists:payments,id',
        ]);

        $payment = Payment::find($validated['payment_id']);

        new SendPayementInvite()($payment);

        return back()
            ->withInput([
                'success' => __('The payment invite has been sent'),
            ]);
    }
}
