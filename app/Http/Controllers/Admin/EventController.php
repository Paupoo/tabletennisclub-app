<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\Breadcrumb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function archive(Event $event): RedirectResponse
    {
        $this->authorize('archive', $event);
        $event->update(['status' => 'archived']);

        return back()->with('success', 'Événement archivé !');
    }

    public function create(): View
    {
        $this->authorize('create', Event::class);
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->events()
            ->current(__('New event'))
            ->toArray();

        return view('admin.events.create', compact('breadcrumbs'));
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);
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

    public function index(Request $request): View
    {
        // Statistiques rapides
        $stats = collect([
            'totalDrafts' => Event::where('status', 'draft')->count(),
            'totalPublished' => Event::where('status', 'published')->count(),
            'totalArchived' => Event::where('status', 'archived')->count(),
            'totalUpcoming' => Event::published()->upcoming()->count(),
        ]);

        // Requête de base avec filtres
        $query = Event::query();

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

        return view('admin.events.index', compact('events', 'stats', 'breadcrumbs'));
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
                    'location' => $event->location,
                    'price' => $event->price ?: 'Gratuit',
                    'icon' => $event->icon,
                ];
            })
            ->toArray();

        return view('events', compact('events'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Event::class);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|in:' . implode(',', array_keys(Event::CATEGORIES)),
            'status' => 'required|in:' . implode(',', array_keys(Event::STATUSES)),
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
            $validated['icon'] = Event::ICONS[$validated['category']] ?? '📅';
        }

        $event = Event::create($validated);

        return redirect()
            ->route('admin.events.show', $event)
            ->with('success', 'Événement créé avec succès !');
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|in:' . implode(',', array_keys(Event::CATEGORIES)),
            'status' => 'required|in:' . implode(',', array_keys(Event::STATUSES)),
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
            ->route('admin.events.show', $event)
            ->with('success', 'Événement mis à jour avec succès !');
    }
}
