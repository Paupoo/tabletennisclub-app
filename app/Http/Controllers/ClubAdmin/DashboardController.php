<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin;

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Feature;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Trainings\Models\Training;
use App\Http\Controllers\Controller;
use App\Support\QueueHealth;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $role = $user->committee_role;
        $isAdmin = $user->is_admin;
        $isCaptain = Team::where('captain_id', $user->id)->exists();

        $showSecretary = $isAdmin || in_array($role, [
            CommitteeRolesEnum::SECRETARY,
            CommitteeRolesEnum::PRESIDENT,
            CommitteeRolesEnum::VICE_PRESIDENT,
        ]);
        $showTreasurer = $isAdmin || $role === CommitteeRolesEnum::TREASURER;
        $showCaptain = $isAdmin || $isCaptain;
        $showCommittee = $isAdmin || in_array($role, [
            CommitteeRolesEnum::PRESIDENT,
            CommitteeRolesEnum::VICE_PRESIDENT,
            CommitteeRolesEnum::ADMINISTRATOR,
        ]);

        $alerts = $this->buildAlerts($user, $isAdmin, $showSecretary, $showTreasurer, $showCaptain);
        $recentActivity = $this->buildActivityFeed();
        $memberTiles = $this->buildMemberTiles($user);
        $upcomingTrainings = $this->buildUpcomingTrainings();
        $upcomingMatches = $this->buildUpcomingMatches();
        $upcomingInternalEvents = $this->buildUpcomingInternalEvents();

        return view('clubAdmin.dashboard', compact(
            'showSecretary',
            'showTreasurer',
            'showCaptain',
            'showCommittee',
            'alerts',
            'recentActivity',
            'memberTiles',
            'upcomingTrainings',
            'upcomingMatches',
            'upcomingInternalEvents',
        ));
    }

    /**
     * @return array<int, array{type: string, label: string, time: string}>
     */
    private function buildActivityFeed(): array
    {
        $feed = collect();

        User::latest()->take(3)->get()->each(function (User $u) use ($feed): void {
            $feed->push([
                'type' => 'member',
                'label' => "{$u->first_name} {$u->last_name} a rejoint le club",
                'time' => $u->created_at?->diffForHumans() ?? '',
                'sort_at' => $u->created_at,
            ]);
        });

        Contact::latest()->take(3)->get()->each(function (Contact $c) use ($feed): void {
            $feed->push([
                'type' => 'contact',
                'label' => "Message de {$c->first_name} {$c->last_name}",
                'time' => $c->created_at?->diffForHumans() ?? '',
                'sort_at' => $c->created_at,
            ]);
        });

        Interclub::whereNotNull('result')
            ->latest('updated_at')
            ->take(2)
            ->get()
            ->each(function (Interclub $i) use ($feed): void {
                $score = $i->score ? " : {$i->score}" : '';
                $feed->push([
                    'type' => 'match',
                    'label' => 'Match du ' . $i->start_date_time->translatedFormat('d M') . $score,
                    'time' => $i->updated_at?->diffForHumans() ?? '',
                    'sort_at' => $i->updated_at,
                ]);
            });

        return $feed
            ->sortByDesc('sort_at')
            ->take(8)
            ->map(fn (array $item): array => [
                'type' => $item['type'],
                'label' => $item['label'],
                'time' => $item['time'],
            ])
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array{type: string, icon: string, label: string, route: string}>
     */
    private function buildAlerts(User $user, bool $isAdmin, bool $showSecretary, bool $showTreasurer, bool $showCaptain): array
    {
        $alerts = [];
        $currentSeason = Season::current();

        // Personal alert: own pending payments (all users)
        $myPendingPayments = Payment::where('status', 'pending')
            ->whereHasMorph('payable', [Subscription::class], fn ($q) => $q->where('user_id', $user->id))
            ->count();
        if ($myPendingPayments > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'o-banknotes',
                'label' => $myPendingPayments === 1 ? '1 paiement ouvert à votre nom' : "{$myPendingPayments} paiements ouverts à votre nom",
                'route' => route('admin.user.registration-management', $user),
            ];
        }

        // No personal "incomplete profile" alert here: the profile.complete
        // middleware sends those members to the onboarding wizard before they
        // can ever reach the dashboard.

        // Personal alert: not affiliated for current season (all users)
        if ($currentSeason) {
            $isAffiliated = $user->subscriptions()
                ->where('season_id', $currentSeason->id)
                ->whereIn('status', ['pending', 'confirmed', 'paid'])
                ->exists();

            if (! $isAffiliated) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'o-exclamation-circle',
                    'label' => "Vous n'êtes pas affilié pour la saison {$currentSeason->name}",
                    'route' => route('admin.user.registration-management', $user),
                ];
            }
        }

        if ($showSecretary || $isAdmin) {
            $unpaidCount = User::active()->unpaid()->count();
            if ($unpaidCount > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'o-exclamation-triangle',
                    'label' => $unpaidCount === 1 ? '1 cotisation impayée' : "{$unpaidCount} cotisations impayées",
                    'route' => route('admin.users.index'),
                ];
            }

            $incompleteProfiles = User::active()->withIncompleteProfile()->count();
            if ($incompleteProfiles > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => 'o-user-circle',
                    'label' => $incompleteProfiles === 1 ? '1 profil membre incomplet' : "{$incompleteProfiles} profils membres incomplets",
                    'route' => route('admin.users.index'),
                ];
            }

            if ($currentSeason) {
                $nonAffiliatedCount = User::active()
                    ->whereDoesntHave('subscriptions', fn ($q) => $q
                        ->where('season_id', $currentSeason->id)
                        ->whereIn('status', ['pending', 'confirmed', 'paid'])
                    )
                    ->count();
                if ($nonAffiliatedCount > 0) {
                    $alerts[] = [
                        'type' => 'info',
                        'icon' => 'o-user-minus',
                        'label' => $nonAffiliatedCount === 1 ? '1 membre actif non affilié' : "{$nonAffiliatedCount} membres actifs non affiliés",
                        'route' => route('admin.users.index'),
                    ];
                }
            }

            $pendingContacts = Contact::byStatus('pending')->count();
            if ($pendingContacts > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => 'o-envelope',
                    'label' => $pendingContacts === 1 ? '1 message en attente' : "{$pendingContacts} messages en attente",
                    'route' => route('admin.website.contacts.index'),
                ];
            }
        }

        if ($showTreasurer || $isAdmin) {
            $pendingPayments = Payment::where('status', 'pending')->count();
            if ($pendingPayments > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'o-banknotes',
                    'label' => $pendingPayments === 1 ? '1 paiement en attente' : "{$pendingPayments} paiements en attente",
                    'route' => route('admin.treasury.payments'),
                ];
            }
        }

        if ($showCaptain || $isAdmin) {
            $pendingSelections = Interclub::where('start_date_time', '>', now())
                ->whereDoesntHave('users')
                ->count();
            if ($pendingSelections > 0) {
                $alerts[] = [
                    'type' => 'error',
                    'icon' => 'o-clipboard-document-check',
                    'label' => $pendingSelections === 1 ? '1 sélection manquante' : "{$pendingSelections} sélections manquantes",
                    'route' => route('admin.interclubs.captain-selection'),
                ];
            }
        }

        // Queue health (admins + committee): a dead worker silently blocks
        // every outgoing email, surface it prominently.
        if ($isAdmin || $user->is_committee_member) {
            if (QueueHealth::isStalled()) {
                $alerts[] = [
                    'type' => 'error',
                    'icon' => 'o-queue-list',
                    'label' => "File d'attente bloquée — aucun email ne part, worker probablement arrêté",
                    'route' => route('admin.queue.index'),
                ];
            }

            $failedJobs = QueueHealth::failedCount();
            if ($failedJobs > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'o-queue-list',
                    'label' => $failedJobs === 1 ? '1 tâche en échec dans la file d\'attente' : "{$failedJobs} tâches en échec dans la file d'attente",
                    'route' => route('admin.queue.index'),
                ];
            }
        }

        return $alerts;
    }

    /**
     * @return array<int, array{icon: string, label: string, sub: string, href: string, color: string}>
     */
    private function buildMemberTiles(User $user): array
    {
        $tiles = [
            ['icon' => 'o-user',                     'label' => 'Mon profil',     'sub' => 'Données personnelles',                    'href' => route('admin.user.profile', $user),                 'color' => 'blue'],
            ['icon' => 'o-clipboard-document-list',  'label' => 'Cotisations',    'sub' => 'Gérer ma cotisation et mes entraînements', 'href' => route('admin.user.registration-management', $user), 'color' => 'emerald'],
            ['icon' => 'o-banknotes',                'label' => 'Mes paiements',  'sub' => 'Suivi & historique',                      'href' => route('admin.user.registration-management', $user), 'color' => 'teal'],
            ['icon' => 'o-calendar',                 'label' => 'Événements',     'sub' => 'Agenda du club',                          'href' => route('admin.user.calendar', $user),                'color' => 'amber'],
            ['icon' => 'o-bell',                     'label' => 'Notifications',  'sub' => 'Infos & tâches',                          'href' => route('notifications.index'),                       'color' => 'rose'],
        ];

        if ($user->is_competitor && Feature::Interclubs->enabled()) {
            $tiles[] = ['icon' => 'o-calendar-days', 'label' => 'Disponibilités', 'sub' => 'Interclubs', 'href' => route('admin.user.calendar', $user),  'color' => 'indigo'];
            $tiles[] = ['icon' => 'o-globe-alt',     'label' => 'Mes matchs',     'sub' => 'Interclubs', 'href' => route('admin.interclubs.my-matches'), 'color' => 'rose'];
        }

        return $tiles;
    }

    /**
     * @return array<int, array{type: string, label: string, sub: string}>
     */
    private function buildUpcomingInternalEvents(): array
    {
        $events = collect();

        Tournament::where('start_date', '>', now())
            ->where('status', '!=', TournamentStatusEnum::CANCELLED)
            ->orderBy('start_date')
            ->take(3)
            ->get()
            ->each(fn (Tournament $t) => $events->push([
                'type' => 'tournament',
                'label' => $t->name,
                'sub' => $t->start_date->translatedFormat('D j M'),
                'sort_at' => $t->start_date,
            ]));

        Meeting::where('scheduled_at', '>', now())
            ->whereNotIn('status', [MeetingStatusEnum::CANCELLED])
            ->orderBy('scheduled_at')
            ->take(3)
            ->get()
            ->each(fn (Meeting $m) => $events->push([
                'type' => 'meeting',
                'label' => $m->title,
                'sub' => $m->scheduled_at->translatedFormat('D j M · H:i'),
                'sort_at' => $m->scheduled_at,
            ]));

        return $events
            ->sortBy('sort_at')
            ->take(4)
            ->map(fn (array $e): array => [
                'type' => $e['type'],
                'label' => $e['label'],
                'sub' => $e['sub'],
            ])
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array{label: string, sub: string}>
     */
    private function buildUpcomingMatches(): array
    {
        return Interclub::where('start_date_time', '>', now())
            ->where('is_bye', false)
            ->orderBy('start_date_time')
            ->take(3)
            ->with(['visitedTeam', 'visitingTeam'])
            ->get()
            ->map(fn (Interclub $i): array => [
                'label' => ($i->visitedTeam?->name ?? '?') . ' vs ' . ($i->visitingTeam?->name ?? '?'),
                'sub' => $i->start_date_time->translatedFormat('D j M · H:i'),
            ])
            ->toArray();
    }

    /**
     * @return array<int, array{label: string, sub: string}>
     */
    private function buildUpcomingTrainings(): array
    {
        return Training::where('start', '>', now())
            ->whereNull('cancelled_at')
            ->orderBy('start')
            ->take(3)
            ->with('room')
            ->get()
            ->map(fn (Training $t): array => [
                'label' => $t->start->translatedFormat('D j M') . ' · ' . $t->start->format('H:i') . '–' . $t->end->format('H:i'),
                'sub' => $t->room?->name ?? '',
            ])
            ->toArray();
    }
}
