<?php

declare(strict_types=1);

namespace App\Services\ClubAdmin\Dashboard;

use App\Data\Dashboard\PendingTask;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Services\RestockingList;
use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackEntry;
use App\Domains\ClubAdmin\Feedback\Models\HelpOffer;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\GuardianDuplicates;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingUser;
use App\Domains\Shared\Enums\ExpenseReportDisplayStatus;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use App\Domains\Shared\Enums\Feature;
use App\Domains\Shared\Enums\FeedbackStatus;
use App\Domains\Shared\Enums\HelpOfferStatus;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Trainings\Models\Training;
use App\Support\QueueHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * What waits for the reader to act, counted once for the dashboard's pills and
 * the menu's counters — the two used to count apart, and to disagree.
 *
 * A task is keyed on the right to do the work, never on reading the screen it
 * lives on: the committee reads nearly everything and acts on little, and a
 * to-do list made of what one may read is somebody else's to-do list.
 *
 * Computed once per request: the menu is drawn on every back-office page, and
 * the dashboard asks a second time for the same figures.
 */
class PendingTasks
{
    /**
     * The number the menu prints next to an entry, or null to print nothing.
     */
    public function badge(User $user, string ...$keys): ?string
    {
        $count = array_sum(array_map(
            fn (string $key): int => $this->for($user)[$key]->count ?? 0,
            $keys,
        ));

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Every task this reader has to do, in the dashboard's reading order,
     * keyed by {@see PendingTask::$key}. A task with nothing to do is absent.
     *
     * @return array<string, PendingTask>
     */
    public function for(User $user): array
    {
        $cacheKey = self::class . ':' . $user->id;
        $request = request();

        if (! $request->attributes->has($cacheKey)) {
            $request->attributes->set($cacheKey, $this->build($user));
        }

        /** @var array<string, PendingTask> */
        return $request->attributes->get($cacheKey);
    }

    private function affiliationsAwaitingDecision(User $user): ?PendingTask
    {
        $season = Season::current();

        if (! $season instanceof Season || ! $user->can(Permission::SubscriptionsManage->value)) {
            return null;
        }

        $count = Subscription::forSeason($season)->awaitingDecision()->count();

        return $this->task('affiliations', $count,
            '1 affiliation en attente', ':count affiliations en attente',
            'o-user-plus', route('admin.users.registrations', ['status' => 'pending']));
    }

    /** The « To buy » section of the shopping list: what justifies a trip. */
    private function barProductsToBuy(User $user): ?PendingTask
    {
        if (! Feature::Bar->enabled() || ! $user->can(Permission::BarRestockingShop->value)) {
            return null;
        }

        return $this->task('bar_shopping', count(app(RestockingList::class)->current()['to_buy']),
            '1 produit à acheter pour le bar', ':count produits à acheter pour le bar',
            'o-shopping-cart', route('bar.restocking.index'));
    }

    /** The queue « To cash in » lists. */
    private function barTabsToCashIn(User $user): ?PendingTask
    {
        if (! Feature::Bar->enabled() || ! $user->can(Permission::BarAccess->value)) {
            return null;
        }

        return $this->task('bar_tabs', BarOrder::where('is_paid', 0)->count(),
            '1 ardoise à encaisser', ':count ardoises à encaisser',
            'o-banknotes', route('bar.orders.index'));
    }

    /**
     * @return array<string, PendingTask>
     */
    private function build(User $user): array
    {
        $tasks = [];

        foreach ([
            $this->myPayments($user),
            $this->affiliationsAwaitingDecision($user),
            $this->campRequestsAwaitingDecision($user),
            $this->unpaidAffiliations($user),
            $this->incompleteProfiles($user),
            $this->guardianDuplicates($user),
            $this->membersNotAffiliated($user),
            $this->newContacts($user),
            $this->feedbackToHandle($user),
            $this->transactionsToReconcile($user),
            $this->expenseReportsToDecide($user),
            $this->expenseReportsToArchive($user),
            $this->barTabsToCashIn($user),
            $this->barProductsToBuy($user),
            $this->sessionsToRecord($user),
            $this->missingSelections($user),
            $this->lineupsToSend($user),
            $this->meetingsToClose($user),
            $this->tournamentsToClose($user),
            $this->draftArticles($user),
            $this->failedJobs($user),
        ] as $task) {
            if ($task instanceof PendingTask) {
                $tasks[$task->key] = $task;
            }
        }

        return $tasks;
    }

    /**
     * Requests on a stage that sorts them: decided on the stage itself, by the
     * same right that validates a season pack. One stage waiting opens it.
     */
    private function campRequestsAwaitingDecision(User $user): ?PendingTask
    {
        if (! Feature::Trainings->enabled() || ! $user->can(Permission::SubscriptionsManage->value)) {
            return null;
        }

        $requests = DB::table('subscription_training_pack')
            ->join('subscriptions', 'subscriptions.id', '=', 'subscription_training_pack.subscription_id')
            ->where('subscription_training_pack.status', 'pending')
            ->where('subscription_training_pack.invoiced_separately', true)
            ->whereNotIn('subscriptions.status', ['cancelled', 'refunded']);

        $count = (clone $requests)->count();
        $camps = (clone $requests)->distinct()->pluck('subscription_training_pack.training_pack_id');

        return $this->task('camp_requests', $count,
            '1 demande de stage à décider', ':count demandes de stage à décider',
            'o-sun', route('admin.trainings.index', $camps->count() === 1 ? ['pack' => $camps->first()] : []));
    }

    private function draftArticles(User $user): ?PendingTask
    {
        if (! Feature::Website->enabled() || ! $user->can(Permission::NewsPostsManage->value)) {
            return null;
        }

        return $this->task('draft_articles', NewsPost::where('status', NewsPostStatusEnum::DRAFT)->count(),
            '1 article en brouillon', ':count articles en brouillon',
            'o-newspaper', route('admin.website.articles.index'), 'info');
    }

    /**
     * Whoever downloads the archives: a decider, or whoever wires refunds.
     * Archiving is downloading a year's ZIP from the report.
     */
    private function expenseReportsToArchive(User $user): ?PendingTask
    {
        if (! Feature::ExpenseReports->enabled() || ! Gate::forUser($user)->allows('archive', ExpenseReport::class)) {
            return null;
        }

        $count = ExpenseReport::query()
            ->whereDisplayStatus(ExpenseReportDisplayStatus::Paid)
            ->whereNull('archived_at')
            ->count();

        return $this->task('expense_reports_to_archive', $count,
            '1 note de frais payée à archiver', ':count notes de frais payées à archiver',
            'o-archive-box', route('admin.treasury.report', ['tab' => 'pieces']), 'info');
    }

    /**
     * Keyed on the right to decide, never on reading the treasury: the
     * committee reads every report and decides on none.
     */
    private function expenseReportsToDecide(User $user): ?PendingTask
    {
        if (! Feature::ExpenseReports->enabled() || ! $user->can(Permission::ExpenseReportsProcess->value)) {
            return null;
        }

        $count = ExpenseReport::where('status', ExpenseReportStatus::Submitted)
            ->where('user_id', '!=', $user->id)
            ->count();

        return $this->task('expense_reports', $count,
            '1 note de frais à traiter', ':count notes de frais à traiter',
            'o-receipt-percent', route('admin.treasury.expense-reports'));
    }

    private function failedJobs(User $user): ?PendingTask
    {
        if (! $user->can(Permission::QueueView->value)) {
            return null;
        }

        return $this->task('failed_jobs', QueueHealth::failedCount(),
            "1 tâche en échec dans la file d'attente", ":count tâches en échec dans la file d'attente",
            'o-queue-list', route('admin.queue.index'));
    }

    /**
     * Opinions nobody has read yet, and offers of help nobody has answered.
     */
    private function feedbackToHandle(User $user): ?PendingTask
    {
        if (! $user->can(Permission::FeedbackManage->value)) {
            return null;
        }

        $count = FeedbackEntry::where('status', FeedbackStatus::New)->whereNull('hidden_at')->count()
            + HelpOffer::where('status', HelpOfferStatus::ToContact)->count();

        return $this->task('feedback', $count,
            "1 avis ou offre d'aide à traiter", ":count avis et offres d'aide à traiter",
            'o-chat-bubble-left-ellipsis', route('admin.feedback.index'), 'info');
    }

    /**
     * Responsible adults on file twice, often found by a member correcting a
     * number from their profile. Merging is done from the file of a member the
     * duplicate answers for, so the task opens the first one.
     */
    private function guardianDuplicates(User $user): ?PendingTask
    {
        if (! $user->can(Permission::UsersUpdate->value)) {
            return null;
        }

        $duplicates = (new GuardianDuplicates)->toMerge();
        $ward = $duplicates->flatMap->users->first();

        return $this->task('guardian_duplicates', $duplicates->count(),
            '1 adulte responsable encodé deux fois', ':count adultes responsables encodés deux fois',
            'o-users', $ward instanceof User ? route('admin.users.show', $ward) : route('admin.users.index'), 'info');
    }

    private function incompleteProfiles(User $user): ?PendingTask
    {
        if (! $user->can(Permission::UsersUpdate->value)) {
            return null;
        }

        return $this->task('incomplete_profiles', User::active()->withIncompleteProfile()->count(),
            '1 profil membre incomplet', ':count profils membres incomplets',
            'o-user-circle', route('admin.users.index'), 'info');
    }

    /**
     * Lineups the captain saved but never sent. Enregistrer ne prévient
     * personne, et c'est l'envoi qui termine la tâche.
     */
    private function lineupsToSend(User $user): ?PendingTask
    {
        if (! Team::where('captain_id', $user->id)->exists()) {
            return null;
        }

        $count = Interclub::query()
            ->with('users')
            ->where('start_date_time', '>', now())
            ->where(fn ($query) => $query
                ->whereHas('visitedTeam', fn ($team) => $team->where('captain_id', $user->id))
                ->orWhereHas('visitingTeam', fn ($team) => $team->where('captain_id', $user->id)))
            ->get()
            ->filter(fn (Interclub $interclub): bool => $interclub->awaitsSending())
            ->count();

        return $this->task('lineups_to_send', $count,
            '1 compo à envoyer à votre équipe', ':count compos à envoyer à votre équipe',
            'o-paper-airplane', route('admin.interclubs.captain-selection'));
    }

    /**
     * A meeting whose date has passed and that was never marked as held, or
     * one held whose minutes were begun and never published. A meeting held
     * without minutes is not one: not every meeting needs them.
     */
    private function meetingsToClose(User $user): ?PendingTask
    {
        if (! Feature::Meetings->enabled() || ! $user->can(Permission::MeetingsManage->value)) {
            return null;
        }

        $count = Meeting::query()
            ->whereNull('archived_at')
            ->where('scheduled_at', '<', now())
            ->where(fn ($query) => $query
                ->whereIn('status', [MeetingStatusEnum::PLANNING, MeetingStatusEnum::CONFIRMED])
                ->orWhere(fn ($held) => $held
                    ->where('status', MeetingStatusEnum::COMPLETED)
                    ->whereHas('minutes', fn ($minutes) => $minutes->whereNull('published_at'))))
            ->count();

        return $this->task('meetings_to_close', $count,
            '1 réunion à clôturer', ':count réunions à clôturer',
            'o-calendar-days', route('admin.meetings.index'));
    }

    private function membersNotAffiliated(User $user): ?PendingTask
    {
        $season = Season::current();

        if (! $season instanceof Season || ! $user->can(Permission::SubscriptionsManage->value)) {
            return null;
        }

        $count = User::active()
            ->whereDoesntHave('subscriptions', fn ($query) => $query
                ->where('season_id', $season->id)
                ->whereIn('status', Subscription::AFFILIATED_STATUSES))
            ->count();

        return $this->task('members_not_affiliated', $count,
            '1 membre actif non affilié', ':count membres actifs non affiliés',
            'o-user-minus', route('admin.users.index'), 'info');
    }

    private function missingSelections(User $user): ?PendingTask
    {
        if (! $user->hasRole(Role::ADMINISTRATOR->value) && ! Team::where('captain_id', $user->id)->exists()) {
            return null;
        }

        $count = Interclub::where('start_date_time', '>', now())->whereDoesntHave('users')->count();

        return $this->task('missing_selections', $count,
            '1 sélection manquante', ':count sélections manquantes',
            'o-clipboard-document-check', route('admin.interclubs.captain-selection'), 'error');
    }

    /**
     * The member's own bills, and those of the accounts they pay for: the
     * same list « My payments » opens on.
     */
    private function myPayments(User $user): ?PendingTask
    {
        $count = Payment::where('status', 'pending')
            ->whereHasMorph('payable', [Subscription::class, TournamentRegistration::class, MeetingUser::class, SubscriptionTrainingPack::class],
                // A stage line names its member through the affiliation.
                fn ($query, string $type) => $type === SubscriptionTrainingPack::class
                    ? $query->whereHas('subscription', fn ($sub) => $sub->whereIn('user_id', $user->payableUserIds()))
                    : $query->whereIn('user_id', $user->payableUserIds()))
            ->count();

        return $this->task('my_payments', $count,
            '1 paiement ouvert à votre nom', ':count paiements ouverts à votre nom',
            'o-banknotes', route('admin.user.payments', $user));
    }

    private function newContacts(User $user): ?PendingTask
    {
        if (! Feature::Contacts->enabled() || ! $user->can(Permission::ContactsManage->value)) {
            return null;
        }

        return $this->task('contacts', Contact::byStatus('new')->count(),
            '1 nouveau message', ':count nouveaux messages',
            'o-envelope', route('admin.website.contacts.index'), 'info');
    }

    /**
     * The coach's own sessions, past, still scheduled and never recorded:
     * the same query as the coach screen.
     */
    private function sessionsToRecord(User $user): ?PendingTask
    {
        if (! Feature::Trainings->enabled() || ! Gate::forUser($user)->allows('access-coach-area')) {
            return null;
        }

        $count = Training::where('trainer_id', $user->id)
            ->where('start', '<', now())
            ->where('status', 'scheduled')
            ->whereNull('attendance_taken_at')
            ->count();

        return $this->task('sessions_to_record', $count,
            '1 séance à pointer', ':count séances à pointer',
            'o-calendar-days', route('coach.trainings'));
    }

    /**
     * Builds the task, or nothing when there is nothing to do.
     *
     * @param  'error'|'warning'|'info'  $type
     */
    private function task(string $key, int $count, string $one, string $many, string $icon, string $route, string $type = 'warning'): ?PendingTask
    {
        if ($count <= 0) {
            return null;
        }

        return new PendingTask(
            key: $key,
            count: $count,
            label: $count === 1 ? $one : str_replace(':count', (string) $count, $many),
            icon: $icon,
            route: $route,
            type: $type,
        );
    }

    /** Begun and never closed: neither closed nor cancelled once its day has come. */
    private function tournamentsToClose(User $user): ?PendingTask
    {
        if (! Feature::Tournaments->enabled() || ! $user->can(Permission::TournamentsManage->value)) {
            return null;
        }

        $count = Tournament::query()
            ->where('start_date', '<', now())
            ->whereNotIn('status', [TournamentStatusEnum::CLOSED, TournamentStatusEnum::CANCELLED])
            ->count();

        return $this->task('tournaments_to_close', $count,
            '1 tournoi à clôturer', ':count tournois à clôturer',
            'o-trophy', route('admin.tournaments.index'));
    }

    /**
     * The lines the bank screen lists as not, or not fully, reconciled. An
     * open payment is not one: it waits for the member, not for the treasurer.
     */
    private function transactionsToReconcile(User $user): ?PendingTask
    {
        if (! Feature::Treasury->enabled() || ! $user->can(Permission::PaymentsReconcile->value)) {
            return null;
        }

        $count = Transaction::unallocated()->count() + Transaction::partiallyAllocated()->count();

        return $this->task('transactions', $count,
            '1 transaction bancaire à rapprocher', ':count transactions bancaires à rapprocher',
            'o-building-library', route('admin.treasury.transactions'));
    }

    /**
     * Validated, still unpaid. A member with no affiliation at all, or one
     * still awaiting a decision, belongs to another pill: each member is
     * counted once.
     */
    private function unpaidAffiliations(User $user): ?PendingTask
    {
        $season = Season::current();

        if (! $season instanceof Season || ! $user->can(Permission::SubscriptionsManage->value)) {
            return null;
        }

        $count = Subscription::forSeason($season)->where('status', 'confirmed')->count();

        return $this->task('unpaid_affiliations', $count,
            '1 cotisation impayée', ':count cotisations impayées',
            'o-exclamation-triangle', route('admin.users.registrations', ['status' => 'confirmed']));
    }
}
