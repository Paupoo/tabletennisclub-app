<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrainingPackRequest;
use App\Http\Requests\UpdateTrainingPackRequest;
use App\Models\TrainingPack;
use App\Support\Breadcrumb;
use Illuminate\View\View;

class TrainingPackController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->trainingPacks()
            ->toArray();

        $trainingPacks = TrainingPack::paginate();

        return view('admin.training_packs.index', compact([
            'breadcrumbs',
            'trainingPacks',
        ]));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTrainingPackRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(TrainingPack $trainingPack)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TrainingPack $trainingPack)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTrainingPackRequest $request, TrainingPack $trainingPack)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TrainingPack $trainingPack)
    {
        //
    }
}
