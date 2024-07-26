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
use App\Services\ForceIndex;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class UserController extends Controller
{
    protected $forceIndex;

    public function __construct(ForceIndex $forceIndex)
    {
        $this->forceIndex = $forceIndex;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $this->authorize('index', User::class);
        return View('admin.members.index', [
            'members' => User::orderby('is_competitor', 'desc')->with('teams')->orderby('force_index')->orderBy('ranking')->orderby('last_name')->orderby('first_name')->paginate(20),
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
            'teams' => Team::with('league')->get(),
            'rankings' => array_column(Ranking::cases(), 'name'),
            'sexes' => array_column(Sex::cases(), 'name'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $request = $request->validated();
        $user = User::create([
            'first_name' => $request['first_name'],
            'last_name' => $request['last_name'],
            'email' => $request['email'],
            'password' => $request['password'],
            'is_competitor' => isset($request['is_competitor']) ? true : false,
            'licence' => $request['licence'],
            'ranking' => $request['ranking'],
            'is_admin' => isset($request['is_admin']) ? true : false,
            'is_comittee_member' => isset($request['is_comittee_member']) ? true : false,
            'is_active' => true,
        ]);

        // Attach a team (TO CHECK, need to be able to attach many teams)
        if (isset($request['team_id'])) {
            $user->teams()->attach(Team::find($request['team_id']));
        }

        $this->forceIndex->setOrUpdateAll();

        return redirect()->route('members.create')
            ->with('success', __('New member ' . $user->first_name . ' ' . $user->last_name . ' created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        return view('admin.members.info', [
            'member' => User::find($id),
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
        // $request = $request->validated();
        $user = User::find($id);

        $user->fill($request->validated());
        if ($request['password'] != null) {
            $user->password = $request['password'];
        }
        $user->is_competitor = isset($request['is_competitor']) ? true : false;
        $user->is_active = isset($request['is_active']) ? true : false;
        $user->is_admin = isset($request['is_admin']) ? true : false;
        $user->is_comittee_member = isset($request['is_comittee_member']) ? true : false;
        $user->save();
        
        // Attach a team (TO CHECK, need to be able to attach many teams)
        if ($request['team_id'] !== null) {
            $user->teams()->attach(Team::find($request['team_id']));
        } else {
            $user->teams()->detach();
        }


        $this->forceIndex->setOrUpdateAll();

        return redirect()->route('members.index');
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $user = User::find($id);

        $user->delete();

        $this->forceIndex->setOrUpdateAll();

        return redirect()->route('members.index')->with('success', 'User ' . $user->first_name . ' ' . $user->last_name . ' has been deleted');
    }

    public function setForceIndex(): RedirectResponse
    {
        $this->forceIndex->setOrUpdateAll();
        return redirect()->route('members.index');
    }

    public function deleteForceIndex(): RedirectResponse
    {
        $this->forceIndex->delete();
        return redirect()->route('members.index');
    }
}
