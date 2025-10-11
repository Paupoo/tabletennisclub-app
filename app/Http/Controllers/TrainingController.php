<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Recurrence;
use App\Enums\TrainingLevel;
use App\Enums\TrainingType;
use App\Http\Requests\StoreTrainingRequest;
use App\Http\Requests\UpdateTrainingRequest;
use App\Models\Room;
use App\Models\Season;
use App\Models\Training;
use App\Models\TrainingPack;
use App\Models\User;
use App\Services\TrainingBuilder;
use App\Services\TrainingDateGenerator;
use App\Support\Breadcrumb;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TrainingController extends Controller
{
    protected TrainingBuilder $builder;

    protected TrainingDateGenerator $dateGenerator;

    public function __construct(TrainingDateGenerator $training_date_generator, TrainingBuilder $training_builder)
    {
        $this->dateGenerator = $training_date_generator;
        $this->builder = $training_builder;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Training::class);

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->trainings()
            ->add('Create')
            ->toArray();

        $training = new Training;
        $trainingPacks = TrainingPack::all();

        return view('admin.trainings.create', [
            'levels' => TrainingLevel::cases(),
            'rooms' => Room::all(),
            'seasons' => $this->getAdjacentSeasons(),
            'training' => $training,
            'types' => TrainingType::cases(),
            'users' => User::select('id', 'last_name', 'first_name')->orderBy('last_name', 'asc')->orderBy('first_name', 'asc')->get(),
            'breadcrumbs' => $breadcrumbs,
            'trainingPacks' => $trainingPacks,
        ]);
    }

    /**
     * Get all dates for a specific weekday between 2 dates
     */
    public function daysBetweenTwoDate(string $start_date, string $end_date, int $week_day): array
    {

        $dates = [];
        // Make sure end time is always after start time.
        if (strtotime($start_date) > strtotime($end_date)) {
            throw new Exception('The end date cannot be before the start date.');
        } else {
            while (strtotime($start_date) <= strtotime($end_date)) {

                // If the day number matches the date's date number, add it into the array, otherwise do nothing.
                if (date('N', strtotime($start_date)) === $week_day) {
                    $dates[] = $start_date;
                } else {
                }

                // Then add 24h.
                $start_date = date('d-m-Y', strtotime('+1 day', strtotime($start_date)));
            }
        }

        return $dates;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Training $training)
    {
        //

        $training->delete();

        return redirect()->route('trainings.index')->with('deleted', 'The training has been deleted.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Training $training)
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->trainings()
            ->current(__('Edit a training'))
            ->toArray();

        $levels = TrainingLevel::cases();
        $rooms = Room::all();
        $seasons = $this->getAdjacentSeasons();
        $types = TrainingType::cases();
        $users = User::all();
        $notSubscribedUsers = User::whereDoesntHave('trainings', function ($query) use ($training) {
            $query->where('training_id', $training->id);
        })->get();
        $trainingPacks = TrainingPack::all();

        return view('admin.trainings.edit', compact([
            'breadcrumbs',
            'levels',
            'training',
            'rooms',
            'seasons',
            'types',
            'users',
            'notSubscribedUsers',
            'trainingPacks',
        ]));
    }

    public function getAdjacentSeasons(): Collection
    {
        return Season::where('start_at', '>=', now()->format('Y') - 1)->orderBy('start_at')->get();
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Training::class);

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->trainings()
            ->toArray();

        return view('admin.trainings.index', [
            'trainings' => Training::orderBy('start')->paginate(10),
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function register(Training $training): RedirectResponse
    {
        $training->trainees()->attach(Auth()->user()->id);

        return redirect()
            ->route('trainings.index')
            ->with('success', __('You are registered to the training'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Training $training)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTrainingRequest $request): RedirectResponse
    {
        // Validate the request
        $validated = $request->validated();
        $trainingPackId = null;

        /**
         * See form request, I might have an ID if there is no recurrence,
         * to be used to link the solo training to that pack.
         */
        if($validated['training_pack_id']) {
            $trainingPackId = $validated['training_pack_id'];
        }

        /**
         * See form request, I expect to have a name if there is a recurrence
         * so that I can create a new trainingpack.
         */
        if($validated['training_pack_name']) {
            $newCreatedPack = TrainingPack::create([
                'season_id' => $validated['season_id'],
                'name' => $validated['training_pack_name'],
                'price' => $validated['training_pack_price']
            ]);

            $trainingPack = $newCreatedPack->id;
        }
        

        $training_dates = $this->dateGenerator->generateDates($validated['start_date'], $validated['end_date'], $validated['recurrence']);

        // create training(s)
        foreach ($training_dates as $training_date) {
            // merge date & time
            $training = $this->builder
                ->mergeDateAndTime($training_date, $validated['start_time'], $validated['end_time'])
                ->setAttributes($validated)
                ->setRoom($validated['room_id'])
                ->setSeason($validated['season_id'])
                ->setTrainer($validated['trainer_id'])
                ->setTrainingPack($trainingPack)
                ->buildAndSave();
        }

        return redirect()->route('trainings.index')->with('success', 'The training has been created.');
    }

    public function unregister(Training $training): RedirectResponse
    {
        $training->trainees()->detach(Auth()->user()->id);

        return redirect()
            ->route('trainings.index')
            ->with('success', __('You are unregistered to the training'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTrainingRequest $request, Training $training): RedirectResponse
    {

        $updated = $training->update($request->validated());

        if (! $updated) {
            return redirect()->route('trainings.index')
                ->with('error', __('The training could not be updated'));
        }

        return redirect()->route('trainings.index')
            ->with('success', __('The training has been updated'));
    }
}
