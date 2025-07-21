<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrUpdateRoomRequest;
use App\Models\Room;
use App\Support\Breadcrumb;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Room::class);

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->rooms()
            ->add('Create')
            ->toArray();

        $room = new Room;

        return view('admin.rooms.create', [
            'room' => $room,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  string  $id
     * @return void
     */
    public function destroy(Room $room): RedirectResponse
    {
        $this->authorize('delete', $room);
        $room->delete();

        return redirect()->route('rooms.index')->with('deleted', 'The room ' . $room->name . ' has been deleted.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Room $room)
    {

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->rooms()
            ->add('Edit ' . $room->name)
            ->toArray();

        $this->authorize('create', Room::class);

        return view('admin.rooms.edit', [
            'room' => $room,
            'breadcrumbs'=> $breadcrumbs,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->rooms()
            ->toArray();

        $this->authorize('viewAny', Room::class);

        return view('admin.rooms.index', [
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Room $room)
    {
        $this->authorize('view', Room::class);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrUpdateRoomRequest $request)
    {
        //
        $validated = $request->validated();

        $room = new Room;

        $room = Room::create($validated);

        return redirect()->route('rooms.index')->with('success', 'The room ' . $room->name . ' has been added.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreOrUpdateRoomRequest $request, Room $room)
    {
        //
        $validated = $request->validated();

        $room->fill($validated);

        $room->save();

        return redirect()->route('rooms.index')->with('success', 'The room ' . $room->name . ' has been updated.');
    }

    /**
     * Check if a room has enough capacity for a specific activity (training or match)
     */
    protected function checkCapacity(Request $request): bool
    {
        // $room = Room::find($request->room_id);
        // $requested_capacity = $request->people;
        // $activity = $request->activity;

        // if ($activity == 'training') {
        //     $response = $requested_capacity <= $room->capacity_trainings ? true : false;
        //     return $response;
        // } elseif ($activity == 'match') {
        //     $response = $requested_capacity <= $room->capacity_matches ? true : false;
        //     return $response;
        // } else {
        //     throw new Exception(__('This activity is unknown. Expected values \'training\' or \'match\'.'));
        // }
    }
}
