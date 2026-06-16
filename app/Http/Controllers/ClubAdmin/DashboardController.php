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
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Http\Controllers\Controller;
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

        return view('clubAdmin.dashboard', compact(
            'showSecretary',
            'showTreasurer',
            'showCaptain',
            'showCommittee',
            'alerts',
            'recentActivity',
            'memberTiles',
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

        // Personal alert: incomplete own profile (all users)
        if (! $user->phone_number || ! $user->street || ! $user->city_code || ! $user->city_name || ! $user->birthdate) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'o-user-circle',
                'label' => 'Votre profil est incomplet — merci de le compléter',
                'route' => route('admin.user.profile', $user),
            ];
        }

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
        ];

        if ($user->is_competitor) {
            $tiles[] = ['icon' => 'o-calendar-days', 'label' => 'Disponibilités', 'sub' => 'Interclubs', 'href' => route('admin.user.calendar', $user),  'color' => 'indigo'];
            $tiles[] = ['icon' => 'o-globe-alt',     'label' => 'Mes matchs',     'sub' => 'Interclubs', 'href' => route('admin.interclubs.my-matches'), 'color' => 'rose'];
        }

        return $tiles;
    }
}
