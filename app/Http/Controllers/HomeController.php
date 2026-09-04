<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Services\PublicAgendaService;
use App\Support\Captcha;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly PublicAgendaService $agenda) {}

    public function index(): View
    {
        $sponsors = [
            ['name' => 'La maison de Malou', 'logo' => asset('images/sponsors/sponsor_1_v2.jpg'), 'url' => 'https://www.lamaisondemalou.be/'],
            ['name' => 'Chatisfait', 'logo' => asset('images/sponsors/sponsor_2_v2.png'), 'url' => 'https://www.chatisfait.be/'],
        ];

        $articles = NewsPost::published()
            ->latest()
            ->with('user')
            ->take(3)
            ->get();

        /** @var array{type: string, season_name: string, season_start: Carbon|null}|null */
        $scheduleContext = null;

        $season = $this->resolveScheduleSeason();

        if ($season !== null) {
            $scheduleContext = $this->buildScheduleContext($season);
        }

        $agenda = $this->agenda->forHomepage($season);

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

        return view('public.home', compact('sponsors', 'articles', 'agenda', 'scheduleContext', 'club', 'featuredEvents'));
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
