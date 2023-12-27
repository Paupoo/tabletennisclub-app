<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Training;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Redirect;

class TrainingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return view('admin.trainings.index', [
            'trainings' => Training::orderBy('start')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //

        return view('admin.trainings.create', [
            'rooms' => Room::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        //
        $request->validate([
            'start' => 'required|date|before:end',
            'end' => 'required|date|after:start',
            'room_id' => 'integer',
            'type' => 'string',
            'level' => 'string',
            'trainer_name' => 'string|nullable',
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

    public function test2 (Request $request)
    {
        $result = $this->daysBetweenTwoDate($request->start, $request->end, $request->day);

        return $result;
    }

    /**
     * Get all dates for a specific weekday between 2 dates
     *
     * @return array
     */
    public function daysBetweenTwoDate(string $start_date, string $end_date, int $week_day): array
    {
        $dates = [];
        $start_date = new Date($start_date);
        $end_date = new Date($end_date);

        for ($i = $start_date; $i <= $end_date; $i++) {
            if ($start_date->dayOfWeek() == $week_day) {
                $dates[] = $start_date;
            } else {
            }
            $i++;
        }

        dd($dates);
        return $dates;
    }
}
