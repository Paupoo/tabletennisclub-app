<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\TrainingPack;
use App\Support\Captcha;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $sponsors = [
            ['name' => 'La maison de Malou', 'logo' => asset('images/sponsors/sponsor_1_v2.jpg'), 'url' => 'https://www.lamaisondemalou.be/'],
            ['name' => 'Chatisfait', 'logo' => asset('images/sponsors/sponsor_2_v2.png'), 'url' => 'https://www.chatisfait.be/'],
        ];

        $articles = NewsPost::latest()
            ->with('user')
            ->take(3)
            ->get();

        $dayNames = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        $dayOrder = ['Lundi' => 1, 'Mardi' => 2, 'Mercredi' => 3, 'Jeudi' => 4, 'Vendredi' => 5, 'Samedi' => 6, 'Dimanche' => 7];
        $levelLabels = [
            'Beginners' => 'Débutant',
            'Elite' => 'Compétition',
            'Intermediate' => 'Confirmé',
            'Kids' => 'Jeunes',
            'Open' => 'Tous niveaux',
            'Young potential' => 'Jeunes espoirs',
        ];

        $schedules = [];

        $season = Season::where('is_active', true)->first();

        if ($season) {
            TrainingPack::with(['trainer', 'room'])
                ->where('season_id', $season->id)
                ->where('is_active', true)
                ->whereNotNull('day_of_week')
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get()
                ->each(function (TrainingPack $pack) use (&$schedules, $dayNames, $levelLabels): void {
                    $start = Carbon::createFromFormat('H:i:s', $pack->start_time);
                    $end = $start->copy()->addMinutes($pack->duration_minutes);

                    // Strip day prefix ("Lundi — ") from pack name for display
                    $activity = preg_replace('/^(Lundi|Mardi|Mercredi|Jeudi|Vendredi|Samedi|Dimanche)\s+—\s+/', '', $pack->name);

                    $schedules[] = [
                        'day' => $dayNames[$pack->day_of_week],
                        'time' => $start->format('G\hi') . ' – ' . $end->format('G\hi'),
                        'activity' => $activity,
                        'location' => $pack->room?->name,
                        'level' => $levelLabels[$pack->level->value] ?? $pack->level->value,
                        'coach' => $pack->trainer ? $pack->trainer->first_name . ' ' . $pack->trainer->last_name : null,
                        'capacity' => $pack->max_participants,
                        'description' => $pack->description,
                        'price' => (float) $pack->price,
                        'is_open_enrollment' => $pack->is_open_enrollment,
                        'type' => $pack->type->value,
                    ];
                });
        }

        // Interclubs (vendredi soir) — événement récurrent, pas un training pack
        $schedules[] = [
            'day' => 'Vendredi',
            'time' => '19h00 – 23h30',
            'activity' => 'Interclubs',
            'location' => 'Demeester (0 et -1)',
            'level' => null,
            'coach' => null,
            'capacity' => null,
            'description' => 'Matches de compétition à domicile. Venez nous supporter !',
            'price' => null,
            'is_open_enrollment' => true,
            'type' => 'match',
        ];

        usort($schedules, fn (array $a, array $b): int => $dayOrder[$a['day']] <=> $dayOrder[$b['day']]);

        // Clean d'un éventuel ancien captcha en session pour éviter un brute force sur le captcha
        session()->forget(['captcha', 'captcha_created_at']);

        $captcha = Captcha::generate();

        session([
            'captcha' => $captcha,
            'captcha_created_at' => time(),
        ]);

        // Clean d'un éventuel ancien captcha en session pour éviter un brute force sur le captcha
        session()->forget(['captcha', 'captcha_created_at']);

        $captcha = Captcha::generate();

        session([
            'captcha' => $captcha,
            'captcha_created_at' => time(),
        ]);

        $club = Club::ourClub()->first();

        $featuredEvents = EventPost::published()
            ->featured()
            ->orderBy('event_date')
            ->get();

        return view('public.home', compact('sponsors', 'articles', 'schedules', 'club', 'featuredEvents'));
    }
}
