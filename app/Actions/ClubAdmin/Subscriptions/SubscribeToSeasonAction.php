<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Actions\ClubAdmin\Payments\GeneratePayment;
use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscribeToSeasonAction
{
    private float $casualLicencePrice = 60;

    private float $competitiveLicencePrice = 125;

    private bool $is_competitor = false;

    private Season $season;

    private float $trainingPrice = 90;

    private User $user;


    /**
     * Handle the incoming request.
     * @param Season $season
     * @param Request $request
     * @return RedirectResponse
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

        // Make sure we don't subscribe twice for the same season
        if ($this->user->subscriptions()->where('season_id', $this->season->id)->exists()) {
            return back()->withErrors(__('The user has already subscribed to this season'));
        }

        $this->is_competitor = $validated['type'] === 'competitive' ? true : false;

        // Create the subscription
        $subscription = $this->subscribe();

        // Removed... we need to let the user or the admin choose the options first
        // Generate the pending payment
        // $payment = new GeneratePayment()($subscription);

        return back()->with([
            'success' => __('The user has been subscribed successfully'),
        ]);
    }

    /**
     * @return float
     */
    public function calculatePrice(): float
    {
        return $this->is_competitor ? $this->competitiveLicencePrice : $this->casualLicencePrice;
    }

    /**
     * @return Subscription
     */
    public function subscribe(): Subscription
    {
        return Subscription::create([
            'user_id' => $this->user->id,
            'season_id' => $this->season->id,
            'is_competitive' => $this->is_competitor,
            'amount_due' => $this->calculatePrice(),
            'status' => 'pending',
            'training_unit_price' => $this->trainingPrice,
        ]);
    }
}
