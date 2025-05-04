<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrUpdateTableRequest;
use App\Models\Room;
use App\Models\Table;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\TournamentTableService;
use Illuminate\Http\Request;

class TableController extends Controller
{

    public function __construct(private TournamentTableService $tableService)
    {

    }


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Table::class);

        return view('admin.tables.index', [
            'tables' => Table::orderByRaw('name * 1 ASC')->paginate(10),
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
        $room = Room::find($table->room_id);
        $this->tableService->updateTablesCount($room);

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
        
        $room = Room::find($table->room_id);
        $this->tableService->updateTablesCount($room);

        return redirect()->route('tables.index')->with('success', 'The table ' . $table->name . ' has been updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Table $table)
    {
        $this->authorize('delete', $table);

        $table->delete();

        $room = Room::find($table->room_id);
        $this->updateTablesCount($room);

        return redirect()->route('tables.index')->with('success', 'The table ' . $table->name . ' has been deleted.');

    }

    /**
     *  Show tables and their current status for a given tournament
     */
    public function tableOverview(Tournament $tournament)
    {
        return view('tables.overview', [
            'tables' => $tournament
                ->tables()
                ->withPivot([
                    'is_table_free',
                    'match_started_at',
                ])
                ->with('match.player1', 'match.player2')
                ->orderBy('is_table_free')
                ->orderBy('match_started_at')
                ->orderByRaw('name * 1 ASC')
                ->get(),
        ]); 
    }
}
