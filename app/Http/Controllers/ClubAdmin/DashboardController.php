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
use App\Domains\Shared\Enums\Feature;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use App\Http\Controllers\Controller;
use App\Services\ClubAdmin\Dashboard\AgendaBlockBuilder;
use App\Support\QueueHealth;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(private readonly AgendaBlockBuilder $agendaBlocks) {}

    public function index(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $role = $user->committee_role;
        $isAdmin = $user->hasRole(Role::ADMINISTRATOR->value);
        $isCaptain = Team::where('captain_id', $user->id)->exists();

        $showSecretary = $isAdmin || in_array($role, [
            CommitteeRolesEnum::SECRETARY,
            CommitteeRolesEnum::PRESIDENT,
            CommitteeRolesEnum::VICE_PRESIDENT,
        ]);
        // Was a third, narrower definition of "treasurer" — the dashboard hid the
        // treasury card from the president while the fines screen let them act.
        // The délégation is now the single answer.
        // Managing permissions, not viewing ones: payments.view belongs to the
        // committee baseline, so keying on it would have shown the treasury
        // section to every committee member — widening what this refactor is
        // meant to tighten.
        $showTreasurer = $user->canAny([
            Permission::PaymentsReconcile->value,
            Permission::TransactionsView->value,
            Permission::CashRegisterView->value,
            Permission::FinesIssue->value,
        ]);
        $showCaptain = $isAdmin || $isCaptain;
        $showCommittee = $isAdmin || in_array($role, [
            CommitteeRolesEnum::PRESIDENT,
            CommitteeRolesEnum::VICE_PRESIDENT,
            CommitteeRolesEnum::ADMINISTRATOR,
        ]);

        $alerts = $this->buildAlerts($user, $isAdmin, $showSecretary, $showTreasurer, $showCaptain);
        $memberTiles = $this->buildMemberTiles($user);
        $agendaBlocks = $this->agendaBlocks->for($user);

        return view('clubAdmin.dashboard', compact(
            'showSecretary',
            'showTreasurer',
            'showCaptain',
            'showCommittee',
            'alerts',
            'memberTiles',
            'agendaBlocks',
        ));
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

            $newContacts = Contact::byStatus('new')->count();
            if ($newContacts > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => 'o-envelope',
                    'label' => $newContacts === 1 ? '1 nouveau message' : "{$newContacts} nouveaux messages",
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
        if ($user->can(Permission::QueueView->value)) {
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
     * @return array<int, array{icon: string, label: string, sub: string, href: string}>
     */
    private function buildMemberTiles(User $user): array
    {
        $tiles = [
            ['icon' => 'o-user',                     'label' => 'Mon profil',     'sub' => 'Données personnelles',                    'href' => route('admin.user.profile', $user)],
            ['icon' => 'o-clipboard-document-list',  'label' => 'Cotisations',    'sub' => 'Gérer ma cotisation et mes entraînements', 'href' => route('admin.user.registration-management', $user)],
            ['icon' => 'o-banknotes',                'label' => 'Mes paiements',  'sub' => 'Suivi & historique',                      'href' => route('admin.user.registration-management', $user)],
            ['icon' => 'o-calendar',                 'label' => 'Événements',     'sub' => 'Agenda du club',                          'href' => route('admin.user.calendar', $user)],
            ['icon' => 'o-bell',                     'label' => 'Notifications',  'sub' => 'Infos & tâches',                          'href' => route('notifications.index')],
        ];

        if ($user->is_competitor && Feature::Interclubs->enabled()) {
            $tiles[] = ['icon' => 'o-calendar-days', 'label' => 'Disponibilités', 'sub' => 'Interclubs', 'href' => route('admin.user.calendar', $user)];
            $tiles[] = ['icon' => 'o-globe-alt',     'label' => 'Mes matchs',     'sub' => 'Interclubs', 'href' => route('admin.interclubs.my-matches')];
        }

        return $tiles;
    }
}
