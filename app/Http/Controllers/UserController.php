<?php

namespace App\Http\Controllers;

use App\Models\User;
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
        return View('admin/members.index', [
            'members' => User::orderby('ranking')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        return View('admin/members.create', [
            'members' => User::all(),
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
                'NA',
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

        $request = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => $request->password,
            'licence' => $request->licence,
            'ranking' => $request->ranking,
            'team' => $request->team,
        ]);

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
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Calculate force index for every registered players and store into the DB.
     */
    public function setForceIndex()
    {
        // Get aggregated counts by ranking [B6]=>1, [NC]=>10...)
        $members = DB::table('users')
            ->select('ranking', DB::raw('count(1) as total'))
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
