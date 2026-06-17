<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Training;

use App\Domains\Trainings\Models\TrainingPack;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainingPackRequest;
use App\Http\Requests\UpdateTrainingPackRequest;
use App\Support\Breadcrumb;
use Illuminate\View\View;

class TrainingPackController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create(): void
    {
        // TODO
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TrainingPack $trainingPack): void
    {
        // TODO
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TrainingPack $trainingPack): void
    {
        // TODO
    }

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
     * Display the specified resource.
     */
    public function show(TrainingPack $trainingPack): void
    {
        // TODO
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTrainingPackRequest $request): void
    {
        // TODO
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTrainingPackRequest $request, TrainingPack $trainingPack): void
    {
        // TODO
    }
}
