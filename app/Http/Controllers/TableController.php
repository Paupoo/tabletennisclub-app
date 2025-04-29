<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrUpdateTableRequest;
use App\Models\Room;
use App\Models\Table;
use Illuminate\Http\Request;

class TableController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Table::class);

        return view('admin.tables.index', [
            'tables' => Table::orderBy('name')->paginate(10),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Table::class);
        
        $table = new Table();
        $rooms = Room::orderBy('name')->get();

        return view('admin.tables.create', [
            'table' => $table,
            'rooms' => $rooms,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrUpdateTableRequest $request)
    {
        $this->authorize('create', Table::class);

        $validated = $request->validated();
        
        $table = Table::create($validated);

        return redirect()->route('tables.index')->with('success', 'The table ' . $table->name . ' has been added.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Table $table)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Table $table)
    {
        $this->authorize('create', Table::class);

        $rooms = Room::orderBy('name')->get();

        return view('admin.tables.edit', [
            'table' => $table,
            'rooms' => $rooms,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreOrUpdateTableRequest $request, Table $table)
    {
        $validated = $request->validated();

        $table->fill($validated);

        $table->save();

        return redirect()->route('tables.index')->with('success', 'The table ' . $table->name . ' has been updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Table $table)
    {
        $this->authorize('delete', $table);

        $table->delete();

        return redirect()->route('tables.index')->with('success', 'The table ' . $table->name . ' has been deleted.');

    }
}
