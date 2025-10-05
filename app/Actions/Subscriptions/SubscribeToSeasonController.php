<?php

namespace App\Actions\Subscriptions;

use App\Actions\Payments\GeneratePaymentReference;
use App\Actions\Payments\GeneratePaymentQR;
use App\Http\Controllers\Controller;
use App\Mail\PaymentInvitation;
use App\Models\Payment;
use App\Models\Season;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

use const App\Http\Controllers\Actions\Subscriptions\competitive;
use const App\Http\Controllers\Actions\Subscriptions\required;
use const App\Http\Controllers\Actions\Subscriptions\users;

class SubscribeToSeasonController extends Controller
{
    private int $casualLicencePrice = 60;
    private int $competitiveLicencePrice = 125;

    private User $user;

    private Season $season;

    private bool $is_competitor = false;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Season $season, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'string|required|exists:users,id',
            'type' => 'required|in:competitive,casual',
        ]);
        
        // Set up parameters
        $this->season = $season;
        $this->user = User::find($validated['user_id']);
        $this->is_competitor = $validated['type'] === 'competitive' ? true : false;

        // Create the subscription
        $subscription = $this->subscribe();
        
        // Generate the pengind payment
        $payment = $this->generatePayment($subscription);

        // Generate the QRCode
        $QRGenerator = new GeneratePaymentQR();
        $QRGenerator($payment); 

        $payment->load( 'payable.user', 'payable.season');
        // Send an email with payment instructions
        Mail::to($this->user->email)
            ->send(new PaymentInvitation($payment));

        return back()->withInput([
            'success' => __('The user has been suscribed successfully'),
        ]);
    }

    public function calculatePrice(): int
    {
        return $this->is_competitor ? $this->competitiveLicencePrice : $this->casualLicencePrice;
    }

    public function subscribe(): Subscription
    {
        return Subscription::create([
            'user_id' => $this->user->id,
            'season_id' => $this->season->id,
            'is_competitive' => $this->is_competitor,
            'amount_due' => $this->calculatePrice(),
            'status' => 'pending',
        ]);
    }

    public function generatePayment(Subscription $subscription): Payment
    {
        $referenceGenerator = new GeneratePaymentReference();

        return $subscription->payments()->create([
            'reference' => $referenceGenerator  (),
            'amount_due' => $subscription->amount_due,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

    }
}
