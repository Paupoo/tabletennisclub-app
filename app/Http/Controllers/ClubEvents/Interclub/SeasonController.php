<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Interclub;

use App\Http\Controllers\Controller;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Support\Breadcrumb;
use Illuminate\Http\Request;
use Illuminate\View\View;

use const App\Http\Controllers\seasons;

class SeasonController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('clubEvents.interclubs.seasons.create');
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
            ->seasons()
            ->toArray();

        $seasons = Season::orderBy('start_at')
            ->paginate();

        $users = User::all();

        return view('clubEvents.interclubs.seasons.index', compact([
            'breadcrumbs',
            'seasons',
            'users',
        ]));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): View
    {
        $season = Season::with('users')->findOrFail($id);

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->seasons()
            ->current($season->name)
            ->toArray();
        $subscriptions = $season->subscriptions->load('payments');
        $trainingPacks = $season->trainingPacks()->get();
        $notSubscribedUsers = User::whereDoesntHave('subscriptions', function ($query) use ($season): void {
            $query->where('season_id', $season->id);
        })->get();

        return view('clubEvents.interclubs.seasons.show', compact([
            'season',
            'subscriptions',
            'notSubscribedUsers',
            'breadcrumbs',
            'trainingPacks',
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

        return view('clubEvents.interclubs.seasons.create');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }
}
