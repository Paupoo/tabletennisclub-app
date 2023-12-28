<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Training;
use Exception;
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

        // Validate the request
        $validated = $request->validate([
            'start_date' => 'required|date_format:Y-m-d|before:end_date',
            'end_date' => 'required|date_format:Y-m-d|after:start_date',
            'start_time' => 'required|date_format:H:i|before:end_time',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'room_id' => 'integer',
            'type' => 'string',
            'level' => 'string',
            'trainer_name' => 'string|nullable',
        ]);

        // add the number of the day from the start date (i. e. 2 for Tuesday)
        $request->merge([
            'start_date_day' => date('N', strtotime($request->start_date)),
        ]);

        // Get the array with all the dates in the interval with the same day.
        $dates = $this->daysBetweenTwoDate($request->start_date, $request->end_date, $request->start_date_day);

        if (count($dates) == 0
        ) {
            // Return to previous page with an error if no date has been found.
            return redirect::back()->with('error', __('You have chosen a too short period of time. No ' . jddayofweek($request->day, 1) . ' found between ' . $request->start_date . ' and ' . $request->end_date . '.'));
        } else {

            foreach ($dates as $date) {

                // Concatenate date & time in dedicated dateTime properties.
                $request->start_dateTime = $date . 'T' . $request->start_time;
                $request->end_dateTime = $date . 'T' . $request->end_time;

                // Created trainings
                $training = Training::create([
                    'start' => $request->start_dateTime,
                    'end' => $request->end_dateTime,
                    'room_id' => $request->room_id,
                    'type' => $request->type,
                    'level' => $request->level,
                    'trainer_name' => $request->trainer_name,
                    'price' => 0,
                ]);
            }
        }


        

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

        $training->delete();

        return redirect()->route('trainings.index')->with('success','The training has been deleted.');
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return void
     */
    public function test2(Request $request)
    {
        $request->validate([
            'start' => 'date|before:end|required',
            'end' => 'date|after:start|required',
            'day' => 'integer|required',
        ]);

        $result = $this->daysBetweenTwoDate($request->start, $request->end, $request->day);

        if(count($result) == 0) {
            // Return to previous page with an error if no date has been found.
            return redirect::back()->with('error', __('You have chosen a too short period of time. No '. jddayofweek($request->day, 1) . ' found between ' . $request->start . ' and ' . $request->end . '.'));
        } else {

            // DO SOMETHING.
        }
        
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
        // Make sure end time is always after start time.
        if (strtotime($start_date) > strtotime($end_date)) {
            throw new Exception('The end date cannot be before the start date.');
        } else {
            while (strtotime($start_date) <= strtotime($end_date)) {

                //If the day number matches the date's date number, add it into the array, otherwise do nothing.
                if (date('N', strtotime($start_date)) == $week_day) {
                    $dates[] = $start_date;
                } else {
                }

                // Then add 24h.
                $start_date = date('d-m-Y', strtotime("+1 day", strtotime($start_date)));
            }
        }

        return $dates;
    }
}
