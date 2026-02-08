<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubPosts;

use App\Enums\EventPostStatusEnum;
use App\Enums\ClubEventTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\ClubPosts\EventPost;
use App\Support\Breadcrumb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminEventPostController extends Controller
{
    public function archive(EventPost $event): RedirectResponse
    {
        $this->authorize('archive', $event);
        $event->update(['status' => 'archived']);

        return back()->with('success', __('Event put in archives'));
    }

    public function create(): View
    {
        $this->authorize('create', EventPost::class);
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->events()
            ->current(__('New event'))
            ->toArray();

        return view('clubPosts.eventPosts.create', compact('breadcrumbs'));
    }

    public function destroy(EventPost $event): RedirectResponse
    {
        $this->authorize('delete', $event);
        if (! $event->canBeDeleted()) {
            return back()->with('error', __('This event cannot be deleted'));
        }

        $event->delete();

        return redirect()
            ->route('clubPosts.eventPosts.index')
            ->with('success', __('Event deleted successfully'));
    }

    public function duplicate(EventPost $event): RedirectResponse
    {
        $this->authorize('duplicated', $event);
        $newEvent = $event->replicate();
        $newEvent->title = $event->title . ' (Copie)';
        $newEvent->status = 'draft';
        $newEvent->save();

        return redirect()
            ->route('clubPosts.eventPosts.edit', $newEvent)
            ->with('success', __('Event duplicated successfully, you may proceed to edit it'));
    }

    public function edit(EventPost $eventPost): View
    {
        $this->authorize('update', $eventPost);
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->events()
            ->add($eventPost->title, route('clubPosts.eventPosts.show', $eventPost))
            ->current(__('Edit'))
            ->toArray();

        return view('clubPosts.eventPosts.edit', compact('eventPost', 'breadcrumbs'));
    }

    public function index(Request $request): View
    {
        // Statistiques rapides
        $stats = collect([
            'drafts' => EventPost::where('status', 'draft')->count(),
            'published' => EventPost::where('status', 'published')->count(),
            'archived' => EventPost::where('status', 'archived')->count(),
            'upcoming' => EventPost::published()->upcoming()->count(),
        ]);

        // Requête de base avec filtres
        $query = EventPost::query();

        // Filtres
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request): void {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('location', 'like', '%' . $request->search . '%');
            });
        }

        // Tri par défaut : événements à venir d'abord, puis par date
        $events = $query->orderByRaw('
            CASE
                WHEN event_date >= CURDATE() THEN 0
                ELSE 1
            END,
            event_date ASC
        ')->paginate($request->get('perPage', 25));

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->events()
            ->toArray();

        return view('clubPosts.eventPosts.index', compact('events', 'stats', 'breadcrumbs'));
    }

    // Actions rapides pour changer le statut
    public function publish(EventPost $event): RedirectResponse
    {
        $this->authorize('publish', $event);
        $event->update(['status' => 'published']);

        return back()->with('success', __('Event published successfully'));
    }

    public function show(EventPost $event): View
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->events()
            ->current($event->title)
            ->toArray();

        return view('clubPosts.eventPosts.show', compact('event', 'breadcrumbs'));
    }

    public function showPublicEvents()
    {
        // Récupérer uniquement les événements publiés, triés par date
        $events = EventPost::published()
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
                    'location' => $event->location,
                    'price' => $event->price ?: __('Free'),
                    'icon' => $event->icon,
                ];
            })
            ->toArray();

        return view('clubPosts.eventPosts.index', compact('events'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', EventPost::class);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => ['required', Rule::enum(ClubEventTypeEnum::class)],
            'status' => ['required', Rule::enum(EventPostStatusEnum::class)],
            'event_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'location' => 'required|string|max:255',
            'price' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:10',
            'max_participants' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'featured' => 'boolean',
        ]);

        // Si pas d'icône fournie, utiliser l'icône par défaut de la catégorie
        if (empty($validated['icon'])) {
            $validated['icon'] = EventPost::ICONS[$validated['category']] ?? '📅';
        }

        $event = EventPost::create($validated);

        return redirect()
            ->route('clubPosts.eventPosts.show', $event)
            ->with('success', __('Event created successfully'));
    }

    public function update(Request $request, EventPost $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|in:' . implode(',', array_keys(EventPost::CATEGORIES)),
            'status' => 'required|in:' . implode(',', array_keys(EventPost::STATUSES)),
            'event_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'location' => 'required|string|max:255',
            'price' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:10',
            'max_participants' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'featured' => 'boolean',
        ]);

        $event->update($validated);

        return redirect()
            ->route('clubPosts.eventPosts.show', $event)
            ->with('success', __('Event updated successfully'));
    }
}
