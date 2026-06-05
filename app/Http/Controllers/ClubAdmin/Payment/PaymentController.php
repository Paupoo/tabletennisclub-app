<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Payment;

use App\Actions\ClubAdmin\Payments\SendPayementInvite;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create(): void
    {
        // TODO
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): void
    {
        // TODO
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): void
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
        (new SendPayementInvite)($payment);

        return back()
            ->with([
                'success' => __('The payment invite has been sent'),
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): void
    {
        // TODO
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): void
    {
        // TODO
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): void
    {
        // TODO
    }
}
