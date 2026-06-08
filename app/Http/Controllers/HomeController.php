<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Models\AppSetting;
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

        /** @var array{type: string, season_name: string, season_start: Carbon|null}|null */
        $scheduleContext = null;

        $season = $this->resolveScheduleSeason();

        if ($season !== null) {
            $scheduleContext = $this->buildScheduleContext($season);

            TrainingPack::with(['trainer', 'room'])
                ->where('season_id', $season->id)
                ->where('is_active', true)
                ->whereNotNull('day_of_week')
                ->where(fn ($q) => $q->whereNull('pack_end_date')->orWhere('pack_end_date', '>=', today()))
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get()
                ->each(function (TrainingPack $pack) use (&$schedules, $dayNames, $levelLabels): void {
                    $start = Carbon::parse($pack->start_time);
                    $end = $start->copy()->addMinutes($pack->duration_minutes);

                    // Strip day prefix ("Lundi — ") from pack name for display
                    $activity = preg_replace('/^(Lundi|Mardi|Mercredi|Jeudi|Vendredi|Samedi|Dimanche)\s+—\s+/', '', $pack->name);

                    $schedules[] = [
                        'day' => $dayNames[$pack->day_of_week],
                        'time' => $start->format('G\hi') . ' – ' . $end->format('G\hi'),
                        'activity' => $activity,
                        'location' => $pack->room->name,
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

        if ($season !== null && AppSetting::get('interclub_schedule_enabled', '1') === '1') {
            $day = AppSetting::get('interclub_schedule_day', 'Vendredi');
            $timeStart = AppSetting::get('interclub_schedule_time_start', '19:00');
            $timeEnd = AppSetting::get('interclub_schedule_time_end', '23:30');

            $schedules[] = [
                'day' => $day,
                'time' => $timeStart . ' – ' . $timeEnd,
                'activity' => 'Interclubs',
                'location' => AppSetting::get('interclub_schedule_location', 'Demeester (0 et -1)'),
                'level' => null,
                'coach' => null,
                'capacity' => null,
                'description' => AppSetting::get('interclub_schedule_description', 'Matches de compétition à domicile. Venez nous supporter !'),
                'price' => null,
                'is_open_enrollment' => true,
                'type' => 'match',
            ];
        }

        usort($schedules, fn (array $a, array $b): int => $dayOrder[$a['day']] <=> $dayOrder[$b['day']]);

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

        return view('public.home', compact('sponsors', 'articles', 'schedules', 'scheduleContext', 'club', 'featuredEvents'));
    }

    /**
     * Build the schedule context array for the resolved season.
     *
     * @return array{type: string, season_name: string, season_start: Carbon|null}
     */
    private function buildScheduleContext(Season $season): array
    {
        if (! $season->is_active && $season->start_at->gt(now())) {
            // Priority 1: future season (not active, starts in the future)
            return [
                'type' => 'future',
                'season_name' => $season->name,
                'season_start' => $season->start_at,
            ];
        }

        if ($season->is_active && $season->start_at->gt(now())) {
            // Priority 2a: active but not yet started
            return [
                'type' => 'upcoming',
                'season_name' => $season->name,
                'season_start' => $season->start_at,
            ];
        }

        if ($season->is_active) {
            // Priority 2b: active and already started — normal display, no banner
            return [
                'type' => 'active',
                'season_name' => $season->name,
                'season_start' => $season->start_at,
            ];
        }

        // Priority 3: past season fallback
        return [
            'type' => 'past',
            'season_name' => $season->name,
            'season_start' => null,
        ];
    }

    /**
     * Resolve which season should supply the schedule, following the priority chain:
     *
     * 1. Future season (is_active=false, start_at > now) with active packs
     * 2. Active season (is_active=true), with or without packs
     * 3. Most recent past season (end_at < now) with active packs
     * 4. null — no season with packs exists
     */
    private function resolveScheduleSeason(): ?Season
    {
        // Priority 1: upcoming future season (not yet active) with schedule packs
        $futureSeason = Season::where('is_active', false)
            ->where('start_at', '>', now())
            ->whereHas('trainingPacks', fn ($q) => $q
                ->where('is_active', true)
                ->whereNotNull('day_of_week')
                ->where(fn ($q2) => $q2->whereNull('pack_end_date')->orWhere('pack_end_date', '>=', today()))
            )
            ->orderBy('start_at')
            ->first();

        if ($futureSeason !== null) {
            return $futureSeason;
        }

        // Priority 2: currently active season
        $activeSeason = Season::where('is_active', true)->first();

        if ($activeSeason !== null) {
            return $activeSeason;
        }

        // Priority 3: most recent past season that still has schedule packs
        return Season::where('is_active', false)
            ->where('end_at', '<', now())
            ->whereHas('trainingPacks', fn ($q) => $q
                ->where('is_active', true)
                ->whereNotNull('day_of_week')
                ->where(fn ($q2) => $q2->whereNull('pack_end_date')->orWhere('pack_end_date', '>=', today()))
            )
            ->orderByDesc('end_at')
            ->first();
    }
}
