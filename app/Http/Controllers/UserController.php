<?php

namespace App\Http\Controllers;

use App\Enums\Rankings;
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
            'members' => User::orderby('is_competitor', 'desc')->orderby('force_index')->orderBy('ranking')->orderby('last_name')->orderby('first_name')->paginate(20),
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
            'roles' => Role::orderby('name')->get(),
            'teams' => Team::all(),
            'rankings' => array_column(Rankings::cases(), 'value'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {

        $request = $request->validated();
        $role = Role::findOrFail($request['role_id']);
        $user = User::create ([
            'first_name' => $request['first_name'],
            'last_name' => $request['last_name'],
            'email' => $request['email'],
            'password' => $request['password'],
            'is_competitor' => isset($request['is_competitor']) ? true : false,
            'licence' => $request['licence'],
            'ranking' => $request['ranking'],
            'team_id' => $request['team_id'],
            'role_id' => $role['id'],
        ]);

        $this->forceIndex->setOrUpdate(); 

        return redirect()->route('members.create')
            ->with('success', __('New member '. $user->first_name . ' ' . $user->last_name . ' created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        return view ('admin.members.info', [
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

        return view ('admin.members.edit', [
            'member' => User::find($id),
            'roles' => Role::orderby('name')->get(),
            'teams' => Team::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        //validation
        $request->validated();

        $user = User::find($id);
        $role = Role::find($request->role_id);

        $user->first_name = $request['first_name'];
        $user->last_name = $request['last_name'];
        $user->email = $request['email'];
        if($request['password'] != null) {
            $user->password = $request['password'];
        }
        if($request['is_competitor'] != null) {
            $user->is_competitor = true;
        } else {
            $user->is_competitor = false;
        };
        $user->licence = $request['licence'];
        $user->ranking = $request['ranking'];
        $user->team_id = $request['team_id'];

        $user->save();

        $user->role()->associate($role);
        $user->save();

        $this->forceIndex->setOrUpdate();

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

        $this->forceIndex->setOrUpdate();

        return redirect()->route('members.index')->with('success', 'User ' . $user->first_name . ' ' . $user->last_name . ' has been deleted');
    }

    public function setForceIndex(): RedirectResponse
    {
        $this->forceIndex->setOrUpdate();
        return redirect()->route('members.index');
    }

    public function deleteForceIndex(): RedirectResponse
    {
        $this->forceIndex->delete();
        return redirect()->route('members.index');

    }
}