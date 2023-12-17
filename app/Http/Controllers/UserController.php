<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Laravel\Jetstream\Http\Controllers\Inertia\TeamMemberController;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return View('admin/members.index', [
            'members' => User::orderby('last_name')->orderby('first_name')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        return View('admin/members.create');
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
            'force_index' => ['nullable', 'integer'],
            'team' => ['nullable', Rule::in([
                'A',
                'B',
                'C',
                'D',
                'E',
                'F',
                'G',
                'H',
                'I',
                'J',
                'K',
                'L',

            ])],
            'role_id' => ['nullable'],
        ]);

        $request = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => $request->password,
            'licence' => $request->licence,
            'ranking' => $request->ranking,
            'team' => $request->team,
            'role_id' => 1,
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
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $request->validate([
            'last_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'lowercase', 'max:255', 'unique:users,email,'.$id,],
            'password' => ['nullable', 'confirmed', 'min:8', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
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
            'force_index' => ['nullable', 'integer'],
            'team' => ['nullable', Rule::in([
                'A',
                'B',
                'C',
                'D',
                'E',
                'F',
                'G',
                'H',
                'I',
                'J',
                'K',
                'L',

            ])]
        ]);

        $user = User::find($id);

        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->email = $request->email;
        if($user->password != null) {
            $user->password = $request->password;
        }
        $user->licence = $request->licence;
        $user->ranking = $request->ranking;
        $user->force_index = $request->force_index;
        $user->team = $request->team;

        $user->save();

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
        // Get aggregated counts by ranking [B6]=>1, [NC]=>10...)
        $members = DB::table('users')
            ->select('ranking', DB::raw('count(1) as total'))
            ->whereNot('ranking','=', ['NA',null])
            ->groupby('ranking')
            ->get();

        // read the whole table, calculate force index for each ranking and update members in the db.
        $i = 0;
        foreach ($members as $member) {
            User::where('ranking', '=', $member->ranking)->update(['force_index' => ($member->total + $i)]);
            $i = $member->total + $i;
        }

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
