<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin;

use App\Domains\Bar\Models\BarInventory;
use App\Domains\ClubAdmin\Feedback\Services\SurveyPrompts;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Feature;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use App\Http\Controllers\Controller;
use App\Services\ClubAdmin\Dashboard\AgendaBlockBuilder;
use App\Services\ClubAdmin\Dashboard\PendingTasks;
use App\Support\AccountProxy;
use App\Support\QueueHealth;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AgendaBlockBuilder $agendaBlocks,
        private readonly PendingTasks $pendingTasks,
    ) {}

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
        // Managing permissions, not viewing ones: the committee baseline reads the
        // payments, the bank lines and the cash register, and this section is a
        // list of things to do — keying it on a view right would hand every
        // committee member the treasurer's to-do list.
        $showTreasurer = $user->canAny([
            Permission::PaymentsReconcile->value,
            Permission::TransactionsImport->value,
            Permission::CashRegisterEntryCreate->value,
            Permission::FinesIssue->value,
        ]);
        $showCaptain = $isAdmin || $isCaptain;
        // Le Gate, pas la délégation : encadrer un pack ou une séance suffit, et
        // c'est ce que dit déjà TrainingPolicy::recordAttendance(). Trois des cinq
        // entraîneurs du club n'ont aucune délégation.
        $showCoach = Feature::Trainings->enabled() && Gate::allows('access-coach-area');
        // La porte du bar, pas ses chiffres : le comité lit les statistiques du
        // bar (bar.stats.view), il ne sert ni ne range.
        $showBar = Feature::Bar->enabled() && $user->can(Permission::BarAccess->value);
        $showCommittee = $isAdmin || in_array($role, [
            CommitteeRolesEnum::PRESIDENT,
            CommitteeRolesEnum::VICE_PRESIDENT,
            CommitteeRolesEnum::ADMINISTRATOR,
        ]);

        $alerts = $this->buildAlerts($user);
        $coachTiles = $showCoach ? $this->buildCoachTiles($user) : [];
        $barTiles = $showBar ? $this->buildBarTiles($user) : [];
        $memberTiles = $this->buildMemberTiles($user);
        // The proxy tiles are rendered by the act-for component, which holds the
        // action; the dashboard only needs to know how many to count.
        $proxyTileCount = AccountProxy::isActing() ? 1 : $user->managedAccounts()->count();
        $agendaBlocks = $this->agendaBlocks->for($user);

        return view('clubAdmin.dashboard', compact(
            'showSecretary',
            'showTreasurer',
            'showCaptain',
            'showCoach',
            'showBar',
            'showCommittee',
            'coachTiles',
            'barTiles',
            'alerts',
            'memberTiles',
            'proxyTileCount',
            'agendaBlocks',
        ));
    }

    /**
     * The pills over the dashboard: what waits for this reader to act — the
     * same tasks the menu counts, see {@see PendingTasks} — framed by the
     * signals that are not a task of theirs.
     *
     * @return array<int, array{type: string, icon: string, label: string, route: string}>
     */
    private function buildAlerts(User $user): array
    {
        $alerts = [];
        $currentSeason = Season::current();
        $tasks = $this->pendingTasks->for($user);

        if (isset($tasks['my_payments'])) {
            $alerts[] = $tasks['my_payments']->toAlert();
            unset($tasks['my_payments']);
        }

        // No personal "incomplete profile" alert here: the profile.complete
        // middleware sends those members to the onboarding wizard before they
        // can ever reach the dashboard.

        // Personal alert: not affiliated for current season (all users)
        if ($currentSeason) {
            $isAffiliated = $user->subscriptions()
                ->where('season_id', $currentSeason->id)
                ->whereIn('status', Subscription::AFFILIATED_STATUSES)
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

        // Queue health: a dead worker silently blocks every outgoing email,
        // surface it prominently — and first among the work to do.
        if ($user->can(Permission::QueueView->value) && QueueHealth::isStalled()) {
            $alerts[] = [
                'type' => 'error',
                'icon' => 'o-queue-list',
                'label' => "File d'attente bloquée — aucun email ne part, worker probablement arrêté",
                'route' => route('admin.queue.index'),
            ];
        }

        foreach ($tasks as $task) {
            $alerts[] = $task->toAlert();
        }

        // The yearly survey: the member's own answer still to give, and — for
        // the délégation — a season ending without any survey planned.
        $surveyPrompts = new SurveyPrompts;
        foreach ([$surveyPrompts->forMember($user), $surveyPrompts->forDelegation($user)] as $surveyAlert) {
            if ($surveyAlert !== null) {
                $alerts[] = $surveyAlert;
            }
        }

        return $alerts;
    }

    /**
     * Le monde admin d'un entraîneur tient en un écran : `coach.trainings`.
     *
     * On ne le garnit pas de portes fermées — la seconde tuile n'apparaît qu'à
     * qui peut vraiment l'ouvrir, comme le fait déjà le bloc capitaine.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * Les gestes du bar qui reviennent : le comptoir d'un soir pour le barman,
     * le stock d'une semaine pour le magasinier (décidé le 2026-10-31). Le reste
     * — historique, catégories, sorties de stock — vit dans le menu.
     *
     * Chaque tuile reprend le verrou de sa route (routes/bar.php), et le nom et
     * l'icône de l'entrée du menu : un écran, un nom.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildBarTiles(User $user): array
    {
        // Les mêmes comptes que les pastilles du menu : voir PendingTasks.
        $tasks = $this->pendingTasks->for($user);
        $tiles = [];

        if ($user->can(Permission::BarOrdersManage->value)) {
            $tiles[] = [
                'icon' => 'o-shopping-bag',
                'label' => 'Nouvelle commande',
                'sub' => 'Le comptoir',
                'href' => route('bar.index'),
                // La couleur club : l'entrée que le barman doit trouver en premier.
                'color' => 'secondary',
            ];
            $tiles[] = [
                'icon' => 'o-banknotes',
                'label' => 'À encaisser',
                'sub' => 'Ardoises ouvertes',
                'href' => route('bar.orders.index'),
                'badge' => $tasks['bar_tabs']->count ?? 0,
            ];
        }

        if ($user->can(Permission::BarRestockingShop->value)) {
            $tiles[] = [
                'icon' => 'o-shopping-cart',
                'label' => 'Courses',
                'sub' => "Ce qu'il faut acheter",
                'href' => route('bar.restocking.index'),
                'badge' => $tasks['bar_shopping']->count ?? 0,
            ];
        }

        if ($user->can(Permission::BarStockManage->value)) {
            // Le libellé du bouton de la page Inventaires : un comptage laissé à
            // moitié se reprend, il ne se recommence pas.
            $tiles[] = [
                'icon' => 'o-clipboard-document-check',
                'label' => BarInventory::inProgress() !== null ? "Reprendre l'inventaire" : 'Nouvel inventaire',
                'sub' => 'Compter le stock',
                'href' => route('bar.inventories.current'),
            ];
        }

        if ($user->can(Permission::BarProductsManage->value)) {
            $tiles[] = [
                'icon' => 'o-cube',
                'label' => 'Produits',
                'sub' => 'Carte & prix',
                'href' => route('bar.products.index'),
            ];
        }

        return $tiles;
    }

    private function buildCoachTiles(User $user): array
    {
        // Le même compte que la pastille et le menu : voir PendingTasks.
        $toRecord = $this->pendingTasks->for($user)['sessions_to_record']->count ?? 0;

        $tiles = [
            [
                'icon' => 'o-calendar-days',
                'label' => 'Mes séances',
                'sub' => 'Pointage & planning',
                'href' => route('coach.trainings'),
                // La couleur club : l'entrée que cette personne doit trouver en premier.
                'color' => 'secondary',
                'badge' => $toRecord,
            ],
        ];

        if ($user->can(Permission::TrainingsManage->value)) {
            $tiles[] = [
                'icon' => 'o-tag',
                'label' => "Packs d'entraînement",
                'sub' => 'Offre & inscrits',
                'href' => route('admin.trainings.index'),
            ];
        }

        return $tiles;
    }

    private function buildMemberTiles(User $user): array
    {
        $tiles = [
            ['icon' => 'o-user',          'label' => 'Mon profil', 'sub' => 'Données personnelles',                       'href' => route('admin.user.profile', $user)],
            // Same wording and icon as the "Ma saison" entry of the member menu:
            // one screen, one name, wherever the member meets it.
            ['icon' => 'o-academic-cap',  'label' => 'Ma saison',  'sub' => 'Gérer mon affiliation et mes entraînements', 'href' => route('admin.user.registration-management', $user)],
        ];

        if ($user->playsInterclub() && Feature::Interclubs->enabled()) {
            $tiles[] = ['icon' => 'o-globe-alt', 'label' => 'Mes matchs', 'sub' => 'Interclubs', 'href' => route('admin.interclubs.my-matches')];
        }

        $tiles[] = ['icon' => 'o-calendar',  'label' => 'Événements',    'sub' => 'Agenda du club',     'href' => route('admin.user.calendar', $user)];
        $tiles[] = ['icon' => 'o-banknotes', 'label' => 'Mes paiements', 'sub' => 'Suivi & historique', 'href' => route('admin.user.payments', $user)];

        return $tiles;
    }
}
