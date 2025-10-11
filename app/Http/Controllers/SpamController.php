<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Spam;
use App\Support\Breadcrumb;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SpamController extends Controller
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
    public function destroy(Spam $spam)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Spam $spam)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        // Breadcrumbs pour la navigation
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->current('Spams')
            ->toArray();

        // Calcul des statistiques pour l'en-tête
        $stats = $this->getStats();

        return view('admin.spams.index', compact('breadcrumbs', 'stats'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Spam $spam)
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
     * Update the specified resource in storage.
     */
    public function update(Request $request, Spam $spam)
    {
        //
    }

    /**
     * Calcul des statistiques pour l'en-tête de page
     */
    private function getStats(): Collection
    {
        $baseQuery = Spam::query();

        return collect([
            'totalSpams' => $baseQuery->count(),
            'todaySpams' => $baseQuery->whereDate('created_at', today())->count(),
            'uniqueIps' => $baseQuery->distinct('ip')->count('ip'),
            'blockedIps' => 0, // À implémenter selon ton système de blocage
        ]);
    }
}
