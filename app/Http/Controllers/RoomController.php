<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Exception;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return view('admin/rooms/index', [
            'rooms' => Room::all(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        return view('admin/rooms/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'name' => ['string', 'unique:rooms,name',],
            'street' => ['string'],
            'city_code' => ['integer', 'max:9999'],
            'city_name' => ['string'],
            'building_name' => ['string'],
            'access_description' => ['string', 'nullable',],
            'capacity_trainings' => ['integer', 'max:999'],
            'capacity_matches' => ['integer', 'max:999'],
        ]);

        Room::create([
            'name' => $request->name,
            'street' => $request->street,
            'city_code' => $request->city_code,
            'city_name' => $request->city_name,
            'building_name' => $request->building_name,
            'access_description' => $request->access_description,
            'capacity_trainings' => $request->capacity_trainings,
            'capacity_matches' => $request->capacity_matches,
        ]);

        return redirect()->route('rooms.index')->with('success', 'The room ' . $request->name . ' has been added.');
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
        return view('admin.rooms.edit', [
            'room' => Room::find($id),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $request->validate([
            'name' => ['string', 'unique:rooms,name,' . $id,],
            'street' => ['string'],
            'city_code' => ['integer', 'max:9999'],
            'city_name' => ['string'],
            'building_name' => ['string'],
            'access_description' => ['string', 'nullable',],
            'capacity_trainings' => ['integer', 'max:999'],
            'capacity_matches' => ['integer', 'max:999'],
        ]);

        $room = Room::find($id);


        $room->name = $request->name;
        $room->street = $request->street;
        $room->city_code = $request->city_code;
        $room->city_name = $request->city_name;
        $room->building_name = $request->building_name;
        $room->access_description = $request->access_description;
        $room->capacity_trainings = $request->capacity_trainings;
        $room->capacity_matches = $request->capacity_matches;

        $room->save();

        return redirect()->route('rooms.index')->with('success', 'The room ' . $request->name . ' has been updated.');
    }

    /**
     * Remove the specified resource from storage.
     * 
     * @param string $id
     * @return void
     */
    public function destroy(string $id)
    {
        //

        $room = Room::find($id);

        $room->delete();

        return redirect()->route('rooms.index')->with('deleted', 'The room ' . $room->name . ' has been deleted.');
    }

    /**
     * Check if a room has enough capacity for a specific activity (training or match)
     *
     * @param Request $request
     * @return boolean
     */
    protected function checkCapacity(Request $request): bool
    {
        $room = Room::find($request->room_id);
        $requested_capacity = $request->people;
        $activity = $request->activity;

        if ($activity == 'training') {
            $response = $requested_capacity <= $room->capacity_trainings ? true : false;
            return $response;
        } elseif ($activity == 'match') {
            $response = $requested_capacity <= $room->capacity_matches ? true : false;
            return $response;
        } else {
            throw new Exception(__('This activity is unknown. Expected values \'training\' or \'match\'.'));
        }
    }
}
