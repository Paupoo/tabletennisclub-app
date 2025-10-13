<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Subscriptions\AddTrainingPack;
use App\Actions\Subscriptions\SynchTrainingPack;
use App\Models\Subscription;
use App\Models\TrainingPack;
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
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
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $subscriptions = Subscription::all();

        return view('admin.subscriptions.index', compact([
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
            ->add($subscription->season->name, route('admin.seasons.show', $subscription->season->id))
            ->current($subscription->user->full_name)
            ->toArray();
        
        return view('admin.subscriptions.show', compact('subscription', 'trainingPacks', 'breadcrumbs'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
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

    public function syncTrainingPacks(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'training_packs' => 'array|required',
            'training_packs.*' => 'integer|required|exists:training_packs,id',
        ]);

        new SynchTrainingPack()($validated['training_packs'], $subscription);

        return back()
            ->with([
                'success' => __('The training has been added to the subscription'),
            ]);
    }
}
