<?php

namespace App\Http\Controllers;

use App\Enums\Ranking;
use App\Enums\Sex;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\Team;
use App\Services\ForceList;
use Illuminate\Http\RedirectResponse;

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

        return View('admin.users.index', [
            'user_model' => User::class,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        $this->authorize('create', User::class);

        return View('admin.users.create', [
            'user' => new User(),
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

        return redirect()->route('users.create')
            ->with('success', __('New member ' . $user->first_name . ' ' . $user->last_name . ' created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
        $user->setAge();
        
        return view('admin.users.show', [
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        //
        $this->authorize('update', User::class);
        
        return view('admin.users.edit', [
            'user' => $user,
            'teams' => Team::all(),
            'rankings' => array_column(Ranking::cases(), 'name'),
            'sexes' => array_column(Sex::cases(), 'name'),

        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();
        $user->update($validated);
        
        // Attach a team (TO CHECK, need to be able to attach many teams)
        if ($request['team_id'] !== null) {
            $user->teams()->attach(Team::find($request['team_id']));
        } else {
            $user->teams()->detach();
        }


        $this->forceList->setOrUpdateAll();

        return redirect()
            ->route('users.index')
            ->with('success', __('Member ' . $user->first_name . ' ' . $user->last_name . ' has been updated.'));
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', User::class);

        $user->delete();

        $this->forceList->setOrUpdateAll();

        return redirect()
            ->route('users.index')
            ->with('deleted', 'User ' . $user->first_name . ' ' . $user->last_name . ' has been deleted');
    }

    public function setForceList(): RedirectResponse
    {
        $this->authorize('setOrUpdateForceList', User::class);
        $this->forceList->setOrUpdateAll();
        return redirect()->route('users.index');
    }

    public function deleteForceList(): RedirectResponse
    {
        $this->authorize('deleteForceList', User::class);
        $this->forceList->delete();
        return redirect()->route('users.index');
    }
}
