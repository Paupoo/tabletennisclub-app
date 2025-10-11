<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\User;
use App\Support\Breadcrumb;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeasonController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.seasons.create');
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
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->toArray();

        $seasons = Season::orderBy('start_at')
            ->paginate();

        return view('admin.seasons.index', compact([
            'seasons',
            'breadcrumbs',
        ]));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): View
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->toArray();
        $season = Season::with('users')->findOrFail($id);
        $subscriptions = $season->subscriptions->load('payments');
        $notSubscribedUsers = User::whereDoesntHave('subscriptions', function ($query) use ($season): void {
            $query->where('season_id', $season->id);
        })->get();

        return view('admin.seasons.show', compact([
            'season',
            'subscriptions',
            'notSubscribedUsers',
            'breadcrumbs',
        ]));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): View
    {
        $validated = $request->validate([
            'name' => 'required|string|max:10',
            'start_at' => 'required|date|before:end_at',
            'end_at' => 'required|date|after:start_at',
        ]);

        Season::create($validated);

        return view('admin.seasons.create');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }
}
