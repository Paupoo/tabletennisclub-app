<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Training;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class TrainingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return view ('admin.trainings.index', [
            'trainings' => Training::all(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //

        return view ('admin.trainings.create', [
            'rooms' => Room::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        //
        // dd($request);
        $request->validate([
            'start' => 'date',
            'end' => 'date','gt:start',
            'room_id' => 'integer',
            'type' => 'string',
            'level' => 'string',
            'trainer_name' => 'string',
        ]);

        $training = Training::create([
            'start' => $request->start,
            'end' => $request->end,
            'room_id' => $request->room_id,
            'type' => $request->type,
            'level' => $request->level,
            'trainer_name' => $request->trainer_name,
            'price' => 0,
        ]);

        return redirect()->route('trainings.index')->with('success', 'The training has been created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Training $training)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Training $training)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Training $training)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Training $training)
    {
        //
    }
}
