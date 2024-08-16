<?php

namespace App\Http\Controllers;

use App\Enums\Ranking;
use App\Enums\Rankings;
use App\Enums\Sex;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\Role;
use App\Models\Team;
use App\Services\ForceList;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class UserController extends Controller
{
    protected $forceList;

    public function __construct(ForceList $forceList)
    {
        $this->forceList = $forceList;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $this->authorize('index', User::class);
        return View('admin.members.index', [
            'members' => User::orderby('is_competitor', 'desc')->with('teams')->orderby('force_list')->orderBy('ranking')->orderby('last_name')->orderby('first_name')->paginate(20),
            'member_model' => User::class,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        $this->authorize('create', User::class);

        return View('admin.members.create', [
            'member' => new User(),
            'teams' => Team::with('league')->get(),
            'rankings' => collect(Ranking::cases())->pluck('name')->toArray(),
            'sexes' => collect(Sex::cases())->pluck('name')->toArray(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request, ForceList $forceList)
    {
        $validated = $request->validated();
        
        $user = User::create($validated);

        // Attach a team (TO CHECK, need to be able to attach many teams)
        if (isset($validated['team_id'])) {
            $user->teams()->attach(Team::find($request['team_id']));
        }

        $forceList->setOrUpdateAll();

        return redirect()->route('members.create')
            ->with('success', __('New member ' . $user->first_name . ' ' . $user->last_name . ' created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $member = User::find($id);
        $member->age = Carbon::parse($member->birthdate)->age;
        return view('admin.members.info', [
            'member' => $member,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $this->authorize('update', User::class);
        
        return view('admin.members.edit', [
            'member' => User::find($id),
            'teams' => Team::all(),
            'rankings' => array_column(Ranking::cases(), 'name'),
            'sexes' => array_column(Sex::cases(), 'name'),

        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        $validated = $request->validated();
        $user = User::find($id);
        $user->update($validated);
        
        // Attach a team (TO CHECK, need to be able to attach many teams)
        if ($request['team_id'] !== null) {
            $user->teams()->attach(Team::find($request['team_id']));
        } else {
            $user->teams()->detach();
        }


        $this->forceList->setOrUpdateAll();

        return redirect()
            ->route('members.index')
            ->with('success', __('Member ' . $user->first_name . ' ' . $user->last_name . ' has been updated.'));
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $user = User::find($id);

        $user->delete();

        $this->forceList->setOrUpdateAll();

        return redirect()->route('members.index')->with('success', 'User ' . $user->first_name . ' ' . $user->last_name . ' has been deleted');
    }

    public function setForceIndex(): RedirectResponse
    {
        $this->authorize('setOrUpdateForceIndex', User::class);
        $this->forceList->setOrUpdateAll();
        return redirect()->route('members.index');
    }

    public function deleteForceIndex(): RedirectResponse
    {
        $this->authorize('deleteForceIndex', User::class);
        $this->forceList->delete();
        return redirect()->route('members.index');
    }
}
