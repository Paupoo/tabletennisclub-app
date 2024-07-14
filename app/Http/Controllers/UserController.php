<?php

namespace App\Http\Controllers;

use App\Classes\ForceIndex;
use App\Enums\Rankings;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $this->authorize('index', User::class);
        return View('admin.members.index', [
            'members' => User::orderby('force_index')->orderby('last_name')->orderby('first_name')->paginate(20),
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

        ForceIndex::setForceIndex();

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
        $request->validate([
            'last_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'lowercase', 'max:255', 'unique:users,email,'.$id,],
            'password' => ['nullable', 'confirmed', 'min:8', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'is_competitor' => ['nullable'],
            'licence' => ['nullable', 'unique:users,licence,'.$id, 'size:6'],
            'ranking' => ['nullable', Rule::in(array_column(Rankings::cases(),'value'))],
            'team_id' => ['nullable', 'exists:teams,id'],
            'role_id' => ['integer'],
        ]);

        $user = User::find($id);
        $role = Role::find($request->role_id);

        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->email = $request->email;
        if($request->password != null) {
            $user->password = $request->password;
        }
        if($request->is_competitor != null) {
            $user->is_competitor = true;
        } else {
            $user->is_competitor = false;
        };
        $user->licence = $request->licence;
        $user->ranking = $request->ranking;
        $user->team_id = $request->team_id;

        $user->save();

        $user->role()->associate($role);
        $user->save();

        ForceIndex::setForceIndex();

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

        ForceIndex::setForceIndex();

        return redirect()->route('members.index')->with('success', 'User ' . $user->first_name . ' ' . $user->last_name . ' has been deleted');
    }
}
