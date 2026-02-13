<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Subscription;

use App\Actions\ClubAdmin\Subscriptions\SyncTrainingPackAction;
use App\Http\Controllers\Controller;
use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubEvents\Training\TrainingPack;
use App\Support\Breadcrumb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
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
    public function destroy(Subscription $subscription): RedirectResponse
    {
        $this->authorize('delete', $subscription);

        $season = $subscription->season;
        $subscription->forceDelete();

        return redirect(route('clubEvents.interclubs.seasons.show', $season))
            ->withInput(
                ['success' => __('The subscription has been deleted')]
            );
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
        $subscriptions = Subscription::all();

        return view('clubAdmin.subscriptions.index', compact([
            'subscriptions',
        ]));
    }

    /**
     * Display the specified resource.
     */
    public function show(Subscription $subscription): View
    {
        // Autorisation
        $this->authorize('view', $subscription);

        // Chargement des relations
        $subscription->load(['user', 'season', 'trainingPacks', 'payments']);

        // Récupération des packs d'entraînement disponibles
        $trainingPacks = TrainingPack::where('season_id', $subscription->season_id)
            ->orderBy('name')
            ->get();

        // Fil d'Ariane
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->seasons()
            ->add($subscription->season->name, route('clubEvents.interclubs.seasons.show', $subscription->season->id))
            ->current($subscription->user->full_name)
            ->toArray();

        return view('clubAdmin.subscriptions.show', compact('subscription', 'trainingPacks', 'breadcrumbs'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // TODO
    }

    public function syncTrainingPacks(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'training_packs' => 'array|nullable',
            'training_packs.*' => 'integer|required|exists:training_packs,id',
        ]);

        if (! array_key_exists('training_packs', $validated)) {
            $validated['training_packs'] = [];
        }

        new SyncTrainingPackAction($validated['training_packs'], $subscription);

        return back()
            ->with([
                'success' => __('The training has been added to the subscription'),
            ]);
    }

    public function unsubscribe(Subscription $subscription): RedirectResponse
    {
        $subscription->delete();

        return back()->with([
            'success' => __('The user has been unsuscribed successfully'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // TODO
    }
}
