<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use App\Enums\LeagueCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Models\Club;
use App\Models\Event;
use App\Models\Interclub;
use App\Models\League;
use App\Models\Room;
use App\Models\Season;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\Training;
use App\Models\User;
use App\Services\InterclubService;
use App\Support\Breadcrumb;
use Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class EventController extends Controller
{
    public function archive(Event $event): RedirectResponse
    {
        $this->authorize('archive', $event);
        $event->update(['status' => 'archived']);

        return back()->with('success', 'Événement archivé !');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('destroy', $event);
        if (! $event->canBeDeleted()) {
            return back()->with('error', 'Cet événement ne peut pas être supprimé.');
        }

        $event->delete();

        return redirect()
            ->route('admin.events.index')
            ->with('success', 'Événement supprimé avec succès !');
    }

    public function duplicate(Event $event): RedirectResponse
    {
        $this->authorize('duplicated', $event);
        $newEvent = $event->replicate();
        $newEvent->title = $event->title . ' (Copie)';
        $newEvent->status = 'draft';
        $newEvent->save();

        return redirect()
            ->route('admin.events.edit', $newEvent)
            ->with('success', 'Événement dupliqué ! Vous pouvez maintenant le modifier.');
    }

    public function edit(Event $event): View
    {
        $this->authorize('update', $event);
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->events()
            ->add($event->title, route('admin.events.show', $event))
            ->current(__('Edit'))
            ->toArray();

        return view('admin.events.edit', compact('event', 'breadcrumbs'));
    }

    /**
     * Affiche la liste des événements
     */
    public function index(Request $request)
    {
        $query = Event::with('eventable');

        // Recherche
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // Filtre par statut
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtre par type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Tri par défaut : prochains événements en premier
        $query->orderBy('start_at', 'asc');
        // Pagination
        $perPage = $request->get('perPage', 25);
        $events = $query->paginate($perPage);

        // Statistiques
        $stats = [
            'drafts' => Event::where('status', EventStatusEnum::DRAFT)->count(),
            'published' => Event::where('status', EventStatusEnum::PUBLISHED)->count(),
            'archived' => Event::where('status', EventStatusEnum::ARCHIVED)->count(),
            'upcoming' => Event::where('start_at', '>=', today())->count(),
        ];

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->events()
            ->toArray();

        return view('admin.events.index', [
            'events' => $events,
            'stats' => $stats,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    // Actions rapides pour changer le statut
    public function publish(Event $event): RedirectResponse
    {
        $this->authorize('publish', $event);
        $event->update(['status' => 'published']);

        return back()->with('success', 'Événement publié !');
    }

    public function show(Event $event): View
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->events()
            ->current($event->title)
            ->toArray();

        return view('admin.events.show', compact('event', 'breadcrumbs'));
    }

    public function showPublicEvents()
    {
        // Récupérer uniquement les événements publiés, triés par date
        $events = Event::published()
            ->orderByRaw('
                CASE 
                    WHEN event_date >= CURDATE() THEN 0 
                    ELSE 1 
                END, 
                event_date ASC
            ')
            ->get()
            ->map(function ($event) {
                // Transformer pour correspondre au format attendu par la vue publique
                return [
                    'id' => $event->id,
                    'category' => $event->category,
                    'title' => $event->title,
                    'description' => $event->description,
                    'date' => $event->formatted_date,
                    'time' => $event->formatted_time,
                    'address' => $event->address,
                    'price' => $event->price ?: 'Gratuit',
                    'icon' => $event->icon,
                ];
            })
            ->toArray();

        return view('events', compact('events'));
    }

    /**
     * Affiche le formulaire de création
     */
    public function create()
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->events()
            ->current(__('Create'))
            ->toArray();

        return view('admin.events.create', [
            'breadcrumbs' => $breadcrumbs,
            'rooms' => Room::orderBy('name')->get(),
            'seasons' => Season::orderBy('start_at', 'desc')->get(),
            'teams' => Team::orderBy('name')->get(),
            'otherClubs' => Club::where('id', '!=', auth()->user()->club_id ?? null)->orderBy('name')->get(),
            'leagues' => League::orderBy('division')->get(),
            'trainers' => User::orderBy('first_name')->orderBy('last_name')->get(),
        ]);
    }

    /**
     * Enregistre un nouvel événement
     */
    public function store(Request $request)
    {
        // Validation de base
        $validated = $request->validate([
            // Champs communs
            'type' => ['required', Rule::in(EventTypeEnum::values())],
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => ['required', Rule::in(EventStatusEnum::values())],
            'event_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'location' => 'required|string|max:255',
            'price' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:10',
            'max_participants' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'featured' => 'boolean',
            'action' => 'nullable|in:draft,publish',
        ]);

        // Validation spécifique selon le type
        $this->validateSpecificFields($request, $validated['type']);

        DB::beginTransaction();

        try {
            // 1. Créer le modèle spécifique selon le type
            $eventable = $this->createEventableModel($request, $validated['type']);

            // 2. Créer l'événement principal avec la relation polymorphique
            $event = $this->createEvent($request, $validated, $eventable);

            DB::commit();

            return redirect()
                ->route('admin.events.index')
                ->with('success', __('Event created successfully!'));

        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()
                ->withInput()
                ->withErrors(['error' => __('An error occurred while creating the event: ') . $e->getMessage()]);
        }
    }

    /**
     * Valide les champs spécifiques selon le type
     */
    protected function validateSpecificFields(Request $request, string $type): void
    {
        match ($type) {
            EventTypeEnum::TRAINING->value => $request->validate([
                'training_level' => 'required|string',
                'training_type' => 'required|string',
                'room_id' => 'required|exists:rooms,id',
                'trainer_id' => 'nullable|exists:users,id',
                'season_id' => 'required|exists:seasons,id',
            ]),

            EventTypeEnum::INTERCLUB->value => $request->validate([
                'is_home' => 'boolean',
                'interclub_room_id' => 'required_if:is_home,1|nullable|exists:rooms,id',
                'interclub_address' => 'required_if:is_home,0|nullable|string|max:150',
                'visited_team_id' => 'required|exists:teams,id',
                'opposite_club_id' => 'required|exists:clubs,id',
                'visiting_team_id' => 'nullable|exists:teams,id',
                'opposite_team_name' => 'nullable|string|size:1|regex:/^[a-zA-Z]$/',
                'total_players' => 'required|integer|min:1|max:20',
                'week_number' => 'nullable|integer|min:1|max:52',
                'league_id' => 'nullable|exists:leagues,id',
                'interclub_season_id' => 'required|exists:seasons,id',
            ]),

            EventTypeEnum::TOURNAMENT->value => $request->validate([
                'tournament_start_date' => 'nullable|date',
                'tournament_end_date' => 'nullable|date|after_or_equal:tournament_start_date',
                'tournament_max_users' => 'required|integer|min:2',
                'tournament_price' => 'required|numeric|min:0',
                'tournament_status' => 'required|string',
                'has_handicap_points' => 'boolean',
            ]),

            default => null,
        };
    }

    /**
     * Crée le modèle spécifique (Training, Interclub ou Tournament)
     */
    protected function createEventableModel(Request $request, string $type): Training|Interclub|Tournament
    {
        return match ($type) {
            EventTypeEnum::TRAINING->value => $this->createTraining($request),
            EventTypeEnum::INTERCLUB->value => $this->createInterclub($request),
            EventTypeEnum::TOURNAMENT->value => $this->createTournament($request),
            default => throw new InvalidArgumentException("Unknown event type: {$type}"),
        };
    }

    /**
     * Crée un Training
     */
    protected function createTraining(Request $request): Training
    {
        $startDateTime = $request->event_date . ' ' . $request->start_time;
        $endDateTime = $request->end_time 
            ? $request->event_date . ' ' . $request->end_time 
            : null;

        return Training::create([
            'level' => $request->training_level,
            'type' => $request->training_type,
            'start' => $startDateTime,
            'end' => $endDateTime,
            'room_id' => $request->room_id,
            'trainer_id' => $request->trainer_id,
            'season_id' => $request->season_id,
        ]);
    }

    /**
     * Crée un Interclub
     */
    protected function createInterclub(Request $request): Interclub
    {
        $startDateTime = $request->event_date . ' ' . $request->start_time;

        return Interclub::create([
            'address' => $request->is_home ? null : $request->interclub_address,
            'start_date_time' => $startDateTime,
            'week_number' => $request->week_number,
            'total_players' => $request->total_players,
            'visited_team_id' => $request->visited_team_id,
            'visiting_team_id' => $request->visiting_team_id,
            'room_id' => $request->is_home ? $request->interclub_room_id : null,
            'league_id' => $request->league_id,
            'season_id' => $request->interclub_season_id,
        ]);
    }

    /**
     * Crée un Tournament
     */
    protected function createTournament(Request $request): Tournament
    {
        $startDate = $request->tournament_start_date 
            ?? $request->event_date . ' ' . $request->start_time;
        
        $endDate = $request->tournament_end_date 
            ?? ($request->end_time ? $request->event_date . ' ' . $request->end_time : null);

        return Tournament::create([
            'name' => $request->title, // Réutilise le titre de l'événement
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_users' => 0, // Sera incrémenté lors des inscriptions
            'max_users' => $request->tournament_max_users,
            'price' => $request->tournament_price * 100, // Conversion en centimes
            'status' => $request->tournament_status,
            'has_handicap_points' => $request->boolean('has_handicap_points'),
        ]);
    }

    /**
     * Crée l'événement principal
     */
    protected function createEvent(Request $request, array $validated, $eventable): Event
    {
        // Gestion du statut selon l'action
        $status = match ($request->action) {
            'publish' => EventStatusEnum::PUBLISHED->value,
            'draft' => EventStatusEnum::DRAFT->value,
            default => $validated['status'],
        };

        return Event::create([
            'eventable_type' => get_class($eventable),
            'eventable_id' => $eventable->id,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'status' => $status,
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'location' => $validated['location'],
            'price' => $validated['price'],
            'icon' => $validated['icon'] ?? EventTypeEnum::from($validated['type'])->getIcon(),
            'max_participants' => $validated['max_participants'],
            'notes' => $validated['notes'],
            'featured' => $request->boolean('featured'),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $event->update($validated);

        return redirect()
            ->route('admin.events.show', $event)
            ->with('success', 'Événement mis à jour avec succès !');
    }
}
