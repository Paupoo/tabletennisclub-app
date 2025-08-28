<?php

namespace App\Http\Controllers;

use App\Models\Spam;
use App\Support\Breadcrumb;
use Illuminate\Http\Request;

class SpamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->current('Spams')
            ->toArray();

        $stats = collect([
            'totalSpams' => Spam::count(),
            'todaySpams' => Spam::whereDate('created_at', today())->count(),
            'uniqueIPs' => Spam::distinct('ip')->count(),
            'blockedIPs' => Spam::where('is_blocked', true)->count(),
        ]);

        return view('admin.spams.index', compact('breadcrumbs', 'stats'));
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
    public function show(Spam $spam)
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
     * Update the specified resource in storage.
     */
    public function update(Request $request, Spam $spam)
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
}
