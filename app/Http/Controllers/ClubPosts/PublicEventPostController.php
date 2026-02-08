<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubPosts;

use App\Http\Controllers\Controller;
use App\Models\ClubPosts\EventPost;

class PublicEventPostController extends Controller
{
    public function index()
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
                    'price' => $event->price ?: 'Gratuit',
                    'icon' => $event->icon,
                ];
            })
            ->toArray();

        return view('public.events', compact('events'));
    }
}
