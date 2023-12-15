<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
<<<<<<< HEAD
            'last_name' => ['required','string','unique:users']
        ]);

        return redirect()->route('members.create')
            ->with('success', '__(Member added)');
=======
            'last_name' => ['required','string'],
            'first_name' => ['required','string'],
            'licence' => ['nullage', 'integer', 'min:1', 'max:999999'],
            'ranking' => ['string'],
            'team' => ['string']
        ]);

        User::create($request);

        event(New Registered($user));
        
        return redirect('members.index');

>>>>>>> 4efe05f453431e0b032a3b3c4f2cfd63270f9545
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
