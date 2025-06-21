<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LeagueCategory;
use App\Http\Requests\StoreInterclubRequest;
use App\Models\Club;
use App\Models\Interclub;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use App\Services\InterclubService;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InterclubController extends Controller
{
    protected $interclubService;

    public function __construct(InterclubService $interclubService)
    {
        $this->interclubService = $interclubService;
    }

    public function addToSelection(Interclub $interclub, User $user): RedirectResponse
    {
        /**
         * to do : check if allowed, make a function for this
         *  - not selected in other team already
         *  - match not in the past
         *  - player is competitor
         *  - player is allow to play (list force check)
         */
        $userSelected = $user->interclubs()->sync([
            $interclub->id => ['is_selected' => true],
        ]);

        return redirect()->route('interclubs.show', $interclub)->with('success', __($user->last_name . ' ' . $user->first_name . ' have been selected for the interclub.'));

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        $this->authorize('create', Interclub::class);

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->matches()
            ->add('Create')
            ->toArray();

        $club = Club::OurClub()->first();
        $otherClubs = Club::OtherClubs()->orderBy('name')->get();
        $user = Auth::user();
        $teams = ($user->is_admin || $user->is_committee_member)
            ? $teams = Team::where('club_id', $club->id)->get()
            : $teams = Team::where('captain_id', $user->id)->get();
        $rooms = Room::select('id', 'name')
            ->where('capacity_for_interclubs', '>', 0)
            ->get();

        return view('admin.interclubs.create', [
            'otherClubs' => $otherClubs,
            'rooms' => $rooms,
            'teams' => $teams,
            'interclubTypes' => collect(LeagueCategory::cases()),
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Interclub $interclub)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Interclub $interclub)
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
            ->matches()
            ->toArray();

        $interclubs = Interclub::orderBy('start_date_time', 'asc')->paginate(10);

        return view('admin.interclubs.index', [
            'interclubs' => $interclubs,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Interclub $interclub): View
    {
        $this->authorize('view', Auth::user(), Interclub::class);

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->matches()
            ->add('Edit')
            ->toArray();

        $selectedUsers = $interclub
            ->users()
            ->wherePivot('is_selected', true)
            ->orderBy('last_name', 'asc')
            ->orderby('first_name', 'asc')
            ->get();

        $subscribedUsers = $interclub
            ->users()
            ->wherePivot('is_subscribed', true)
            ->wherePivot('is_selected', false)
            ->orWherePivot('is_selected', null)
            ->orderBy('last_name', 'asc')
            ->orderby('first_name', 'asc')
            ->get();

        $users = User::where('is_competitor', true)
            ->whereDoesntHave('interclubs')
            ->orWhereHas('interclubs', function (Builder $query) use ($interclub): void {
                $query->where('interclub_id', $interclub->id)
                    ->whereNot('is_subscribed', true)
                    ->whereNot('is_selected', true);
            })
            ->get();

        return View('admin.interclubs.show', [
            'interclub' => $interclub,
            'selectedUsers' => $selectedUsers,
            'subscribedUsers' => $subscribedUsers,
            'users' => $users,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function showSelections(): View
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->matches()
            ->add('Selections')
            ->toArray();

        $interclubs = Interclub::all();

        return View('admin.interclubs.selections', [
            'interclubs' => $interclubs,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInterclubRequest $request)
    {
        $validated = $request->validated();

        $this->interclubService->createInterclub($validated);

        return redirect()->route('interclubs.index')->with('success', 'The match has been added.');

    }

    public function subscribe(Request $request): RedirectResponse
    {

        $subscriptions = array_keys($request->all()['subscriptions']);

        $user = Auth::user();

        $user->interclubs()->syncWithPivotValues(array_values($subscriptions), ['is_subscribed' => true]);

        return redirect()->route('interclubs.index')->with('success', __('You have correctly subscribed.'));
    }

    public function toggleSelection(Interclub $interclub, User $user): RedirectResponse
    {
        $userWithPivot = $user->interclubs()->where('interclub_id', $interclub->id)->first();

        // if (!isset($userWithPivot->registration->is_selected)) {
        //     $userWithPivot->registration->is_selected = false;
        // }

        // Toggle pivot value
        $user->interclubs()->updateExistingPivot($interclub->id, [
            'is_selected' => ! $userWithPivot->registration->is_selected,
        ]);

        return ! $userWithPivot->registration->is_selected
            ? redirect()->route('interclubs.show', $interclub)->with('success', __($user->last_name . ' ' . $user->first_name . ' has been selected for the interclub.'))
            : redirect()->route('interclubs.show', $interclub)->with('deleted', __($user->last_name . ' ' . $user->first_name . ' has been unselected for the interclub.'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Interclub $interclub)
    {
        //
    }
}
