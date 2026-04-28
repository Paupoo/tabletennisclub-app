<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubPosts;

use App\Http\Controllers\Controller;
use App\Models\ClubPosts\EventPost;
use Illuminate\Contracts\View\View;


class PublicEventPostController extends Controller
{
   public function index(): View
    {
        $today = now()->startOfDay();

        $events = EventPost::published()
            ->orderBy('event_date', 'asc')
            ->get()
            ->sortBy(fn (EventPost $event) => [
                $event->event_date >= $today ? 0 : 1,
                $event->event_date,
            ])
            ->map(fn (EventPost $event) => [
                'id' => $event->id,
                'category' => $event->category,
                'title' => $event->title,
                'description' => $event->description,
                'date' => $event->formatted_date,
                'time' => $event->formatted_time,
                'location' => $event->location,
                'price' => $event->price ?: __('Gratuit'),
                'icon' => $event->icon,
            ])
            ->values()
            ->toArray();

        return view('public.events', compact('events'));
    }
}
