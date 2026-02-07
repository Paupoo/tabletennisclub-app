<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Contact;

use App\Http\Controllers\Controller;
use App\Models\ClubAdmin\Contact\Spam;
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
        // TODO
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Spam $spam)
    {
        // TODO
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Spam $spam)
    {
        // TODO
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

        return view('clubAdmin.contacts.spams.index', compact('breadcrumbs', 'stats'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Spam $spam)
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
    public function update(Request $request, Spam $spam)
    {
        // TODO
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
