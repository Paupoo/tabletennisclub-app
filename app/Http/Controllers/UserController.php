<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Http\Request;
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
        return View('admin.members.index', [
            'members' => User::orderby('last_name')->orderby('first_name')->paginate(20),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        return View('admin.members.create', [
            'roles' => Role::orderby('name')->get(),
            'teams' => Team::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'last_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'lowercase', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'is_competitor' => ['nullable'],
            'licence' => ['nullable', 'unique:users,licence', 'size:6'],
            'ranking' => ['nullable', Rule::in([
                'B0',
                'B2',
                'B4',
                'B6',
                'C0',
                'C2',
                'C4',
                'C6',
                'D0',
                'D2',
                'D4',
                'D6',
                'E0',
                'E2',
                'E4',
                'E6',
                'NC',
            ])],
            'team_id' => ['nullable', 'exists:teams,id'],
            'role' => ['nullable'],
        ]);

        $request = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => $request->password,
            'is_competitor' => ($request->is_competitor != null) ? true : false,
            'licence' => $request->licence,
            'ranking' => $request->ranking,
            'team_id' => $request->team_id,
            'role_id' => $request->role,
        ]);

        $this->setForceIndex();

        return redirect()->route('members.create')
            ->with('success', __('New member '. $request->first_name . ' ' . $request->last_name . ' created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
    public function update(Request $request, string $id)
    {
        //validation
        $request->validate([
            'last_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'lowercase', 'max:255', 'unique:users,email,'.$id,],
            'password' => ['nullable', 'confirmed', 'min:8', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'is_competitor' => ['nullable'],
            'licence' => ['nullable', 'unique:users,licence,'.$id, 'size:6'],
            'ranking' => ['nullable', Rule::in([
                'B0',
                'B2',
                'B4',
                'B6',
                'C0',
                'C2',
                'C4',
                'C6',
                'D0',
                'D2',
                'D4',
                'D6',
                'E0',
                'E2',
                'E4',
                'E6',
                'NC',
            ])],
            'team_id' => ['nullable', 'exists:teams,id'],
            'role' => ['integer'],
        ]);

        $user = User::find($id);

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
        $user->role_id = $request->role;

        $user->save();

        $this->setForceIndex();

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

        $this->setForceIndex();

        return redirect()->route('members.index')->with('success', 'User ' . $user->first_name . ' ' . $user->last_name . ' has been deleted');
    }

    /**
     * Calculate force index for every registered players and store into the DB.
     */
    public function setForceIndex() 
    {      
        // Get aggregated counts by ranking (i.e. [B6]=>1, [D4]=>5, ...) but exclude E6 and NC players
        $members = DB::table('users')
            ->select('ranking', DB::raw('count(1) as total'))
            ->whereNot('ranking', '=', 'NA')
            ->whereNot('ranking', '=', null)
            ->groupby('ranking')
            ->orderBy('ranking', 'asc')
            ->get();

        // Get count of total E6 & NC players
        $totalE6_and_NC_users = User::whereIn('ranking', ['E6','NC'])->count();

        // read the whole table, calculate force index for each ranking and update members in the db except for E6/NC.
        $i = 0;
        foreach ($members as $member) {
            if ($member->ranking == 'E6' || $member->ranking == 'NC') {
                null;
            } elseif ($member->ranking != 'E6' || $member->ranking != 'NC') {
                User::where('ranking', '=', $member->ranking)->update(['force_index' => ($member->total + $i)]);
                $i = $member->total + $i;
            }
        }

        // For E6 and NC players, simply mass update their count + last value of $i
        User::whereIn('ranking', ['E6','NC'])->update(['force_index' => $totalE6_and_NC_users + $i]);
        
        unset($i);

        return redirect()->route('members.index');
    }

    /**
     * Delete force index for all members in the db.
     */
    public function deleteForceIndex()
    {
        User::where('force_index', '!=', null)->update(['force_index' => null]);
        return redirect()->route('members.index');
    }
}
