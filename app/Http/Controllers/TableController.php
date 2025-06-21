<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrUpdateTableRequest;
use App\Models\Room;
use App\Models\Table;
use App\Models\Tournament;
use App\Services\TournamentTableService;
use App\Support\Breadcrumb;

class TableController extends Controller
{
    public function __construct(private TournamentTableService $tableService) {}

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Table::class);

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->tables()
            ->add('Create')
            ->toArray();

        $table = new Table;
        $rooms = Room::orderBy('name')->get();

        return view('admin.tables.create', [
            'table' => $table,
            'rooms' => $rooms,
            'breadcrumbs' => $breadcrumbs,
        ]);
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
     * Show the form for editing the specified resource.
     */
    public function edit(Table $table)
    {
        $this->authorize('create', Table::class);

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->tables()
            ->add('Edit')
            ->toArray();
        $rooms = Room::orderBy('name')->get();

        return view('admin.tables.edit', [
            'table' => $table,
            'rooms' => $rooms,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Table::class);

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->tables()
            ->toArray();

        return view('admin.tables.index', [
            'tables' => Table::orderByRaw('name * 1 ASC')->paginate(10),
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Table $table)
    {
        //
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
     *  Show tables and their current status for a given tournament
     */
    public function tableOverview(Tournament $tournament)
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->tables()
            ->add(title: 'Overview')
            ->toArray();

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
                ->orderByRaw('name')
                ->get(),
            'tournament' => $tournament,
            'breadcrumbs' => $breadcrumbs,
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
}
