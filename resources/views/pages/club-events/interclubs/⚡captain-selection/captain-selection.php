<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\CaptainSelection;

use App\Data\Interclub\PoolCandidate;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\InterclubAvailabilityService;
use App\Domains\Competitions\Interclub\Services\InterclubPoolService;
use App\Domains\Competitions\Interclub\Services\InterclubPreparationService;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\ComposesInterclubLineup;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use ComposesInterclubLineup, HasBreadcrumbs, HasFilterDrawer, Toast;

    #[Locked]
    public ?int $availabilityRequestId = null;

    public bool $availabilityRequestModal = false;

    public string $captainMeetupInfo = '';

    #[Locked]
    public ?int $currentUserId = null;

    public bool $drawerSelection = false;

    /**
     * Hérité du centre de contrôle : ne garder que ce qui demande une action.
     * C'est un filtre au sens de DS-A — il restreint un ensemble et s'efface.
     */
    public bool $filterAlerts = false;

    public bool $isUpdateMode = false;

    public bool $modalMessage = false;

    /** @var array<int, int> */
    public array $pendingAddedIds = [];

    /** @var array<int, int> */
    public array $pendingRemovedIds = [];

    public string $search = '';

    #[Locked]
    public ?int $selectedInterclubId = null;

    public ?int $selectedMatchDay = null;

    /** @var array<int, int> */
    public array $selectedPlayerIds = [];

    public ?int $selectedSeasonId = null;

    public ?int $selectedTeamId = null;

    /**
     * Whether the modal about to open sends a lineup the captain declared
     * short-handed. Decided on save, acted upon on send: the declaration is
     * only recorded once the team has been told, never on a skipped send.
     */
    #[Locked]
    public bool $sendsShortHanded = false;

    /**
     * Which fixture the "notify the team" modal will mail.
     *
     * Deliberately not selectedInterclubId, which is where the *drawer* is. The
     * two used to be one property, so the send stayed armed on the last fixture
     * opened long after the drawer had moved on — and nothing disarmed it, not
     * sending, not skipping, and above all not closing the modal by the cross.
     * A navigation and a committed intent do not share a variable.
     */
    #[Locked]
    public ?int $sendTargetId = null;

    /**
     * The captain's box in the drawer: « nous jouerons à 3 ». Only offered
     * between the minimum the rules allow and a full team.
     */
    public bool $shortHandedOptIn = false;

    /**
     * A season is a matrix of teams × match days. 'team' reads one row — a team,
     * all its days. 'day' reads one column — a day, all the teams. The control
     * center was that column on a page of its own, and duplicated everything
     * around it to get there.
     */
    public string $viewMode = 'team';

    /**
     * Memoised for the render: `with()` and `getFilterChips()` both need the
     * accessible teams and used to load them twice.
     *
     * @var array<string, \Illuminate\Database\Eloquent\Collection<int, Team>>
     */
    private array $accessibleTeamsCache = [];

    /** Resolved lazily, once per render. */
    private ?InterclubPreparationService $preparation = null;

    public function boot(): void
    {
        $this->currentUserId = Auth::id();
    }

    /**
     * Clearing the filters no longer clears the team: the team is navigation,
     * and there is no "no team" state to fall back to. Only the season resets.
     */
    public function clearFilters(): void
    {
        $this->selectedSeasonId = Season::current()?->id;
        $this->filterAlerts = false;

        $this->ensureSelectedTeamIsReachable();
    }

    /**
     * Arms the confirmation. The request mails the whole team, and it used to
     * fire straight from a bare icon — five clicks meant five rounds of mail to
     * the same people.
     */
    public function confirmAvailabilityRequest(int $interclubId): void
    {
        $interclub = Interclub::findOrFail($interclubId);
        $this->authorizeInterclub($interclub);

        $this->availabilityRequestId = $interclubId;
        $this->availabilityRequestModal = true;
    }

    /**
     * The season is the only filter left. Under DS-A the team determines the
     * object of the page — exactly one, never none — so it is navigation, and
     * navigation does not belong in the filter drawer or in a removable chip.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->selectedSeasonId !== Season::current()?->id) {
            $seasonName = Season::find($this->selectedSeasonId)?->name ?? __('All seasons');
            $chips[] = ['key' => 'selectedSeasonId', 'label' => __('Season') . ': ' . $seasonName];
        }

        if ($this->filterAlerts) {
            $chips[] = ['key' => 'filterAlerts', 'label' => __('Show issues only')];
        }

        return $chips;
    }

    public function mount(): void
    {
        $this->selectedSeasonId = Season::current()?->id;

        $user = Auth::user();

        // Selections delegate, or captain of at least one team.
        Gate::authorize('access-selections');

        $this->selectedTeamId = $this->loadAccessibleTeams($user, Season::current())->first()?->id;
    }

    public function openSelection(int $interclubId): void
    {
        $interclub = Interclub::findOrFail($interclubId);
        $this->authorizeInterclub($interclub);

        if ($interclub->start_date_time < now()) {
            return;
        }

        // The alert banner reaches across teams, so opening a fixture has to
        // bring the page with it. Leaving the filter behind used to compose the
        // lineup out of the *filtered* team's players.
        $ourTeam = $this->ownTeamOf($interclub);

        if ($ourTeam && $ourTeam->id !== $this->selectedTeamId) {
            $this->selectedTeamId = $ourTeam->id;
        }

        $this->selectedInterclubId = $interclubId;

        $this->selectedPlayerIds = $interclub->users()
            ->wherePivot('is_selected', true)
            ->pluck('users.id')
            ->toArray();

        $this->shortHandedOptIn = $interclub->isShortHanded();

        $this->drawerSelection = true;
    }

    public function removeFilter(string $key): void
    {
        if ($key === 'selectedSeasonId') {
            $this->selectedSeasonId = Season::current()?->id;
        }

        if ($key === 'filterAlerts') {
            $this->filterAlerts = false;
        }

        $this->ensureSelectedTeamIsReachable();
    }

    public function render(): View
    {
        return $this->view();
    }

    public function requestAvailability(InterclubAvailabilityService $service): void
    {
        if (! $this->availabilityRequestId) {
            return;
        }

        $interclub = Interclub::findOrFail($this->availabilityRequestId);
        $this->authorizeInterclub($interclub);

        $service->requestAvailability($interclub);

        $this->availabilityRequestModal = false;
        $this->availabilityRequestId = null;

        $this->success(
            __('Availability request sent!'),
            __('Team members who have not responded will receive an email.'),
            position: 'toast-bottom toast-end'
        );
    }

    public function saveSelection(): void
    {
        $interclub = $this->selectedInterclub();

        if (! $interclub) {
            return;
        }

        abort_if($interclub->start_date_time < now(), 403);

        // Contrôle de sûreté : quelqu'un a pu aligner un de ces joueurs pendant
        // que ce tiroir était ouvert. Avec le pool, ce n'est plus une course de
        // quelques millisecondes — c'est la durée d'un coup de téléphone, et le
        // message doit donc nommer le joueur et l'équipe qui l'a pris. « Un ou
        // plusieurs joueurs » était inexploitable dès lors qu'on compose avec
        // des joueurs d'autres équipes.
        $blocked = $this->blockedPlayerData($interclub);

        foreach ($this->selectedPlayerIds as $selectedId) {
            if (! isset($blocked[$selectedId])) {
                continue;
            }

            $taken = User::find($selectedId);

            $this->error(
                __('Selection blocked'),
                __(':player has been lined up in team :team in the meantime.', [
                    'player' => trim(($taken?->last_name ?? '') . ' ' . ($taken?->first_name ?? '')) ?: __('A player'),
                    'team' => $blocked[$selectedId],
                ]),
                position: 'toast-bottom toast-end'
            );

            return;
        }

        $existingIds = $interclub->users()->pluck('users.id')->toArray();
        $maxPlayers = $interclub->total_players;

        if (count($this->selectedPlayerIds) > $maxPlayers) {
            $this->warning(
                __('Too many players'),
                __('Maximum :n players allowed.', ['n' => $maxPlayers]),
                position: 'toast-bottom toast-end'
            );

            return;
        }

        $previouslyConfirmedIds = $interclub->users()
            ->wherePivot('is_selected', true)
            ->wherePivotNotNull('selection_confirmed_at')
            ->pluck('users.id')
            ->toArray();

        DB::transaction(function () use ($interclub, $existingIds): void {
            foreach ($this->selectedPlayerIds as $userId) {
                if (in_array($userId, $existingIds, true)) {
                    $interclub->users()->updateExistingPivot($userId, ['is_selected' => true]);
                } else {
                    $interclub->users()->attach($userId, ['is_selected' => true]);
                }
            }

            $toDeselect = array_diff($existingIds, $this->selectedPlayerIds);
            foreach ($toDeselect as $userId) {
                $interclub->users()->updateExistingPivot($userId, ['is_selected' => false]);
            }
        });

        $this->drawerSelection = false;
        $this->sendTargetId = $interclub->id;

        $selectedCount = count($this->selectedPlayerIds);
        $isShortLineup = $selectedCount >= $interclub->minimumPlayers() && $selectedCount < $maxPlayers;
        $this->sendsShortHanded = $isShortLineup && $this->shortHandedOptIn;

        // La déclaration tombe dès qu'elle ne décrit plus la compo : un 4e trouvé,
        // une équipe passée sous le minimum, ou la case décochée. Elle survit à un
        // simple échange — on joue toujours à 3, et le capitaine l'a déjà dit.
        if ($interclub->isShortHanded() && ! $this->sendsShortHanded) {
            $interclub->update(['short_handed_confirmed_at' => null, 'short_handed_confirmed_by' => null]);
        }

        if ($previouslyConfirmedIds === []) {
            if ($interclub->isSelectionComplete() || $this->sendsShortHanded) {
                $this->isUpdateMode = false;
                $this->modalMessage = true;
            } else {
                $this->sendTargetId = null;
                $this->success(
                    __('Selection saved — :n of :max players.', [
                        'n' => $selectedCount,
                        'max' => $maxPlayers,
                    ]),
                    // Sous le minimum, la rencontre ne peut pas se jouer (C.25.6) :
                    // l'app ne déclare pas le forfait, mais elle le dit.
                    $selectedCount < $interclub->minimumPlayers()
                        ? __('Fewer than :min players: the fixture cannot be played. Report the forfeit at least 48 h before.', ['min' => $interclub->minimumPlayers()])
                        : null,
                    position: 'toast-bottom toast-end'
                );
            }

            return;
        }

        $added = array_values(array_diff($this->selectedPlayerIds, $previouslyConfirmedIds));
        $removed = array_values(array_diff($previouslyConfirmedIds, $this->selectedPlayerIds));

        // Une compo déjà amputée à 3, enregistrée sans la case, que le capitaine
        // revient déclarer : rien n'a changé dans la liste, mais l'équipe doit
        // encore apprendre qu'elle joue à 3.
        $declaresNow = $this->sendsShortHanded && ! $interclub->isShortHanded();

        if ($added === [] && $removed === [] && ! $declaresNow) {
            $this->sendTargetId = null;
            $this->success(__('Selection saved.'), position: 'toast-bottom toast-end');

            return;
        }

        $this->pendingAddedIds = $added;
        $this->pendingRemovedIds = $removed;
        $this->isUpdateMode = true;
        $this->modalMessage = true;
    }

    /**
     * The day view is bounded by the same rule as the team switcher: it never
     * shows a team the caller could not already reach on their own. A captain
     * sees their own teams on that day, a club-wide selector sees every team.
     */
    public function selectDay(?int $weekNumber): void
    {
        $this->selectedMatchDay = $weekNumber;
        $this->selectedInterclubId = null;
        $this->selectedPlayerIds = [];
        $this->drawerSelection = false;
    }

    /**
     * Switching team is a navigation, so it authorises like one: a captain only
     * reaches the teams they lead, whatever id arrives from the client.
     */
    public function selectTeam(int $teamId): void
    {
        $user = Auth::user();
        $season = $this->selectedSeasonId ? Season::find($this->selectedSeasonId) : Season::current();

        if (! $this->loadAccessibleTeams($user, $season)->contains('id', $teamId)) {
            return;
        }

        $this->selectedTeamId = $teamId;
        $this->selectedInterclubId = null;
        $this->selectedPlayerIds = [];
        $this->drawerSelection = false;
    }

    public function sendLineupToTeam(InterclubAvailabilityService $service): void
    {
        $interclub = $this->sendTarget();

        if (! $interclub) {
            return;
        }

        // La déclaration « nous jouerons à 3 » n'existe qu'une fois l'équipe
        // prévenue : un envoi sauté ne règle rien. Elle précède les services,
        // qui lisent la rencontre pour savoir quoi dire aux joueurs.
        $announceShortHanded = $this->sendsShortHanded && ! $interclub->isShortHanded();

        if ($this->sendsShortHanded) {
            $interclub->update([
                'short_handed_confirmed_at' => now(),
                'short_handed_confirmed_by' => Auth::id(),
            ]);
        }

        // Ce que l'envoi va réellement faire, décidé avant que resetSendModal()
        // n'efface le diff. Une compo déjà envoyée puis ramenée sous le complet
        // ne prévient que les joueurs écartés : notifySelectionChange() sort
        // avant le reste, et c'est le bon comportement. Ce qui ne l'était pas,
        // c'est le compte rendu — « toute l'équipe a été notifiée », qui était
        // faux, sur le seul écran qui dise au capitaine ce qui est parti.
        $removedCount = count($this->pendingRemovedIds);
        $onlyRemovedAreNotified = $this->isUpdateMode && ! $interclub->isLineupReady();

        if ($this->isUpdateMode) {
            $service->notifySelectionChange($interclub, $this->pendingAddedIds, $this->pendingRemovedIds, $this->captainMeetupInfo, $announceShortHanded);
        } else {
            $service->confirmSelection($interclub, $this->captainMeetupInfo);
        }

        $this->resetSendModal();

        if ($onlyRemovedAreNotified) {
            $this->success(
                trans_choice('Notified the removed player.|Notified the :count removed players.', $removedCount, ['count' => $removedCount]),
                __('The rest of the team will be told once the lineup is complete again.'),
                icon: 'o-paper-airplane'
            );

            return;
        }

        $this->success(
            __('Lineup sent to the whole team!'),
            __('All team members have been notified.'),
            icon: 'o-paper-airplane'
        );
    }

    /** Reading direction of the matrix. Anything else is ignored. */
    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['team', 'day'], true)) {
            return;
        }

        $this->viewMode = $mode;
        $this->selectedInterclubId = null;
        $this->selectedPlayerIds = [];
        $this->drawerSelection = false;
    }

    public function skipSending(): void
    {
        $this->resetSendModal();
        $this->success(__('Selection saved.'), position: 'toast-bottom toast-end');
    }

    public function togglePlayer(int $userId): void
    {
        $interclub = $this->selectedInterclub();
        $maxPlayers = $interclub?->total_players ?? 4;

        if (in_array($userId, $this->selectedPlayerIds)) {
            $this->selectedPlayerIds = array_values(array_diff($this->selectedPlayerIds, [$userId]));

            return;
        }

        if ($interclub && $this->isPlayerDoubleBooked($userId, $interclub)) {
            $this->warning(
                __('Player already aligned'),
                __('This player is already selected in another team for week :n.', ['n' => $interclub->week_number]),
                position: 'toast-bottom toast-end'
            );

            return;
        }

        // Le pool ne propose jamais un joueur que C.22 interdit, mais l'identité
        // arrive par le réseau : la recherche de remplaçant, un tiroir resté
        // ouvert pendant qu'une compo bougeait, ou simplement une requête forgée.
        if ($interclub && $this->isForbiddenByRule($userId, $interclub)) {
            $this->warning(
                __('Lineup rule'),
                __('Rule C.22: this player would be stronger than the third player of the superior team.'),
                position: 'toast-bottom toast-end'
            );

            return;
        }

        if (count($this->selectedPlayerIds) >= $maxPlayers) {
            $this->warning(
                __('Team full'),
                __('Maximum :n players.', ['n' => $maxPlayers]),
                position: 'toast-bottom toast-end'
            );

            return;
        }

        $this->selectedPlayerIds[] = $userId;
    }

    /**
     * Closing the confirmation by the cross, the backdrop or Escape never goes
     * through requestAvailability(), so the armed fixture used to survive the
     * dismissal. Same disease as the send modal: an outgoing action that keeps
     * its target after the window that justified it is gone.
     */
    public function updatedAvailabilityRequestModal(bool $open): void
    {
        if (! $open) {
            $this->availabilityRequestId = null;
        }
    }

    /**
     * A composition the captain walked away from is not a composition. It used
     * to survive the closing of the drawer and turn up in the next summary.
     */
    public function updatedDrawerSelection(bool $open): void
    {
        if (! $open) {
            $this->selectedInterclubId = null;
            $this->selectedPlayerIds = [];
            $this->search = '';
            $this->shortHandedOptIn = false;
        }
    }

    /**
     * The cross, the backdrop and Escape all close the modal without passing
     * through skipSending(), and used to leave the target armed and the diff
     * of the previous fixture behind. Dismissing is an answer too.
     */
    public function updatedModalMessage(bool $open): void
    {
        if (! $open) {
            $this->resetSendModal();
        }
    }

    public function updatedSelectedSeasonId(): void
    {
        $user = Auth::user();
        $season = $this->selectedSeasonId ? Season::find($this->selectedSeasonId) : Season::current();
        $this->selectedTeamId = $this->loadAccessibleTeams($user, $season)->first()?->id;
        $this->selectedInterclubId = null;
        $this->selectedPlayerIds = [];
        $this->drawerSelection = false;
    }

    public function updatedSelectedTeamId(): void
    {
        $this->selectedInterclubId = null;
        $this->selectedPlayerIds = [];
        $this->drawerSelection = false;
    }

    public function with(): array
    {
        $user = Auth::user();
        $isAdminOrCommittee = $user->can(Permission::InterclubsManage->value);
        $canSearchSubstitute = $user->can(Permission::SelectionsManage->value);

        $seasons = Season::orderBy('start_at')->get();
        $season = $this->selectedSeasonId
            ? $seasons->firstWhere('id', $this->selectedSeasonId)
            : Season::current();

        $teams = $this->loadAccessibleTeams($user, $season);

        // Single pass: every fixture this render needs, loaded once with the
        // pivot rows the status rule depends on. The team cards and the
        // preparation matrix both read from here — they used to each query per
        // team, and the matrix additionally queried per cell, which is what put
        // a nine-team season past a thousand queries per render.
        $fixtures = $this->loadFixtures($teams->pluck('id')->all());

        $teamsData = $teams->map(fn (Team $team): array => $this->buildTeamData($team, $fixtures));

        if ($this->selectedTeamId && ! $teamsData->firstWhere('id', $this->selectedTeamId)) {
            $this->selectedTeamId = $teamsData->first()['id'] ?? null;
        }

        // The banner routes to the teams that are *not* on screen. The urgent
        // fixtures of the visible team are rows in the list right below it;
        // repeating them there only spent the top of the page saying it twice.
        $alertMatches = $teamsData
            ->reject(fn ($t): bool => $t['id'] === $this->selectedTeamId)
            ->flatMap(fn ($t) => collect($t['matches'])->map(fn ($m): array => array_merge($m, ['team_name' => $t['name'], 'team_id' => $t['id']])))
            ->filter(fn ($m): bool => $m['status'] === 'urgent')
            ->values();

        $selectedTeamData = $teamsData->firstWhere('id', $this->selectedTeamId);
        $matchGroups = $this->groupMatches($selectedTeamData['matches'] ?? []);

        // ── Lecture par colonne : une journée, toutes les équipes atteignables.
        // Les rencontres viennent de $teamsData, donc du même périmètre que le
        // sélecteur d'équipe : la vue journée ne peut pas montrer davantage.
        // Chronologique, pas numérique : les bulles affichent l'indice de journée
        // (matchDayMap), qui suit les coups d'envoi. Trier par numéro de semaine
        // les mélangeait dès que deux catégories alternent — et une catégorie
        // occupe précisément les semaines de repos de l'autre.
        $matchDays = $teamsData
            ->flatMap(fn (array $t): array => $t['matches'])
            ->sortBy('starts_at')
            ->pluck('wk')
            ->unique()
            ->values()
            ->all();

        if ($this->selectedMatchDay === null || ! in_array($this->selectedMatchDay, $matchDays, true)) {
            $this->selectedMatchDay = $this->firstMatchDayNeedingAttention($teamsData, $matchDays);
        }

        $dayMatches = $teamsData
            ->flatMap(fn (array $t): array => collect($t['matches'])
                ->where('wk', $this->selectedMatchDay)
                ->map(fn (array $m): array => array_merge($m, [
                    'team_id' => $t['id'],
                    'team_name' => $t['name'],
                    'team_division' => $t['division'],
                ]))
                ->all())
            ->sortBy('team_name')
            ->values()
            ->all();

        $dayGroups = $this->groupMatches($dayMatches);

        // Drawer data: roster for the selected match
        $drawerInterclub = null;
        $roster = collect();
        $maxPlayers = 4;
        $blockedPlayerIds = [];
        $poolRows = collect();
        $poolWaiting = collect();
        $poolHiddenCount = 0;
        $poolMaybeCount = 0;
        $poolMaybeTeams = [];
        $lineupConstraint = null;

        if ($this->selectedInterclubId && $this->drawerSelection) {
            $drawerInterclub = $fixtures->firstWhere('id', $this->selectedInterclubId)
                ?? Interclub::find($this->selectedInterclubId);

            if ($drawerInterclub) {
                // The roster belongs to the fixture, never to the filter: a
                // filter is a display preference, the roster is a rule.
                $fixtureTeam = $this->ownTeamOf($drawerInterclub);
                $selectedTeam = ($fixtureTeam ? $teams->firstWhere('id', $fixtureTeam->id) : null)
                    ?? $teams->firstWhere('id', $this->selectedTeamId);
                $maxPlayers = $drawerInterclub->total_players;

                $pivotMap = $drawerInterclub->users
                    ->keyBy('id')
                    ->map(fn ($u) => $u->registration);

                $blockedPlayerIds = array_keys($this->blockedPlayerData($drawerInterclub));

                $roster = $this->buildLineupRoster(
                    $drawerInterclub,
                    $selectedTeam,
                    $season,
                    $fixtures,
                    $this->selectedPlayerIds,
                );

                // ── Article C.22 ────────────────────────────────────────────
                // Le verdict se lit sur le plus fort de la composition en cours,
                // donc il change à chaque case cochée. L'effectif propre est
                // *grisé* et jamais masqué : on masque ce qu'on n'a jamais
                // promis, on désactive ce qu'on a déjà montré.
                ['constraint' => $lineupConstraint, 'verdict' => $verdictFor] = $this->lineupLegality(
                    $drawerInterclub,
                    $this->selectedPlayerIds,
                    // Les indices sont déjà sur les lignes de l'effectif, y compris
                    // pour les remplaçants venus d'ailleurs : inutile de les relire.
                    $roster->whereIn('id', $this->selectedPlayerIds)->pluck('force_index')->values()->all(),
                );

                $roster = $roster->map(function (array $player) use ($verdictFor): array {
                    $verdict = $verdictFor($player['force_index'] ?? null);

                    $player['legality'] = $verdict->state;
                    $player['legality_reason'] = $verdict->isWorthShowing() ? $verdict->reason->label() : null;
                    $player['is_illegal'] = $verdict->isForbidden();

                    return $player;
                });

                // ── Les joueurs libres ──────────────────────────────────────
                $pool = app(InterclubPoolService::class)->poolFor($drawerInterclub, $this->weekFixtures($drawerInterclub));
                $poolCategory = $drawerInterclub->league?->category;

                $poolRows = $pool->freePlayers
                    ->reject(fn (PoolCandidate $candidate): bool => in_array($candidate->user->id, $this->selectedPlayerIds, true))
                    ->map(fn (PoolCandidate $candidate): array => $this->buildPoolRow($candidate, $poolCategory, $verdictFor, $fixtures, $selectedTeam, $season))
                    ->sortBy([['force_sort', 'asc'], ['last_name', 'asc'], ['first_name', 'asc']])
                    ->values();

                // Rouge : illégal quoi que fasse l'équipe supérieure. On cesse de
                // le proposer plutôt que de refuser le geste ensuite — mais on
                // dit combien, sinon la liste semble simplement vide.
                $poolHiddenCount = $poolRows->where('is_illegal', true)->count();
                $poolRows = $poolRows->where('is_illegal', false)->values();

                $poolMaybeCount = $pool->maybePlayers->count();
                $poolMaybeTeams = $pool->maybePlayers
                    ->map(fn (PoolCandidate $candidate): string => $candidate->originTeam->name)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $poolWaiting = $pool->waitingTeams;
            }
        }

        // ── La modale d'envoi lit sa propre cible ────────────────────────
        // Elle lisait $roster, que with() ne construit que si le *tiroir* est
        // ouvert — or saveSelection() vient de le fermer. Elle annonçait donc
        // « Compo sélectionnée (4/4) » sans un seul nom, à chaque fois. La
        // compo qui part est celle qui est en base, pas celle qui reste en
        // mémoire : c'est elle qu'on affiche.
        $pendingAddedNames = [];
        $pendingRemovedNames = [];
        $modalIsComplete = true;
        $sendLineupNames = [];
        $sendMaxPlayers = $maxPlayers;
        $sendTargetLabel = '';

        if ($this->modalMessage && $this->sendTargetId) {
            $modalInterclub = Interclub::with(['visitedTeam.club', 'visitingTeam.club'])
                ->find($this->sendTargetId);

            if ($modalInterclub) {
                $sendMaxPlayers = $modalInterclub->total_players;
                // La déclaration n'est tamponnée qu'à l'envoi : tant que la fenêtre
                // est ouverte, c'est l'intention du capitaine qui fait foi.
                $modalIsComplete = $this->sendsShortHanded || $modalInterclub->isLineupReady();

                // Une action qui engage douze e-mails nomme sa cible : une
                // cible erronée doit se voir avant le clic, pas après.
                $sendTargetLabel = trim(sprintf(
                    'vs %s — %s',
                    $this->opponentNameOf($modalInterclub),
                    $modalInterclub->start_date_time->format('d/m/Y'),
                ));

                $sendLineupNames = $modalInterclub->users()
                    ->wherePivot('is_selected', true)
                    ->get()
                    ->map(fn (User $u): string => $u->last_name . ' ' . $u->first_name)
                    ->values()
                    ->all();
            }

            if ($this->isUpdateMode) {
                $pendingAddedNames = User::whereIn('id', $this->pendingAddedIds)
                    ->get()
                    ->map(fn (User $u): string => $u->last_name . ' ' . $u->first_name)
                    ->values()
                    ->all();
                $pendingRemovedNames = User::whereIn('id', $this->pendingRemovedIds)
                    ->get()
                    ->map(fn (User $u): string => $u->last_name . ' ' . $u->first_name)
                    ->values()
                    ->all();
            }
        }

        $searchResults = collect();
        $searchNote = null;
        if ($canSearchSubstitute && strlen($this->search) >= 2) {
            $selectedTeam = $teams->firstWhere('id', $this->selectedTeamId);
            $teamCategory = $selectedTeam?->league?->category;
            $veteranCutoff = $season ? $season->end_at->copy()->subYears(40) : null;
            $excludedIds = array_merge($this->selectedPlayerIds, $blockedPlayerIds);

            // Interclub-eligible members matching the name, before the category /
            // alignment filters — so we can explain what got silently removed (I2).
            // NA and non-competitive members are out of scope and never surface.
            $nameMatches = User::interclubEligible()
                ->where(fn ($q) => $q
                    ->where('first_name', 'like', '%' . $this->search . '%')
                    ->orWhere('last_name', 'like', '%' . $this->search . '%'))
                ->get();

            $matchesCategory = fn (User $u): bool => match ($teamCategory) {
                'MEN' => $u->gender === Gender::MEN,
                'WOMEN' => $u->gender === Gender::WOMEN,
                'VETERANS' => $veteranCutoff !== null && $u->birthdate !== null
                    && $u->birthdate->toDateString() <= $veteranCutoff->toDateString(),
                default => true,
            };

            $eligible = $nameMatches
                ->reject(fn (User $u): bool => in_array($u->id, $excludedIds, true))
                ->filter($matchesCategory);

            $searchResults = $eligible->take(8)->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->last_name . ' ' . $u->first_name,
                'rank' => $u->ranking->getLabel(),
            ])->values();

            if ($searchResults->isEmpty()) {
                $searchNote = $this->buildSearchNote($nameMatches, $excludedIds, $matchesCategory, $teamCategory);
            }
        }

        $matchDayMap = $season ? Interclub::matchDayMap($season->id, $teams->pluck('id')->toArray()) : [];

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'seasons_list' => $seasons->map(fn ($s): array => ['id' => $s->id, 'name' => $s->name]),
            'teams_list' => $teams->map(fn ($t): array => ['id' => $t->id, 'name' => ($t->club?->name ?? '?') . ' ' . $t->name]),
            // DS-A: navigation, not a filter — so it is always offered, and it
            // is offered outside the filter drawer.
            'teams_for_switcher' => $teams
                ->sortBy(fn (Team $t): string => sprintf('%d_%s', ['MEN' => 0, 'WOMEN' => 1, 'VETERANS' => 2][$t->league?->category] ?? 99, $t->name))
                ->map(fn (Team $t): array => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'label' => $t->name . ($t->league?->category ? ' · ' . $this->categoryLabel($t->league->category) : ''),
                ])->values()->all(),
            // Le titre du tiroir se calculait dans le template, à coups de
            // flatMap sur toutes les rencontres. C'est de la présentation, mais
            // pas du gabarit.
            'drawerTitle' => $drawerInterclub
                ? __('Selection') . ' — ' . __('Match day') . ' ' . ($matchDayMap[$drawerInterclub->week_number] ?? $drawerInterclub->week_number)
                : __('Selection'),
            'drawerSubtitle' => $drawerInterclub
                ? trim(sprintf(
                    'vs %s — %s',
                    $this->opponentNameOf($drawerInterclub),
                    $drawerInterclub->start_date_time->format('d/m/Y'),
                ))
                : '',
            'teamsData' => $teamsData,
            'selectedTeamData' => $selectedTeamData,
            'matchGroups' => $matchGroups,
            'matchDays' => $matchDays,
            'dayGroups' => $dayGroups,
            // La matrice de la saison, bornée aux mêmes équipes que le reste de
            // la page : elle ne montre jamais plus que les deux modes de lecture.
            'weekSummary' => $isAdminOrCommittee
                ? app(InterclubPreparationService::class)->summary($teams, $fixtures)
                : null,
            'alertMatches' => $alertMatches,
            'roster' => $roster,
            'poolRows' => $poolRows,
            'poolWaiting' => $poolWaiting,
            'poolHiddenCount' => $poolHiddenCount,
            'poolMaybeCount' => $poolMaybeCount,
            'poolMaybeTeams' => $poolMaybeTeams,
            'lineupConstraint' => $lineupConstraint,
            'maxPlayers' => $maxPlayers,
            'searchResults' => $searchResults,
            'searchNote' => $searchNote,
            'drawerInterclub' => $drawerInterclub,
            'isAdminOrCommittee' => $isAdminOrCommittee,
            'canSearchSubstitute' => $canSearchSubstitute,
            'matchDayMap' => $matchDayMap,
            'filterChips' => $this->getFilterChips(),
            'sendLineupNames' => $sendLineupNames,
            'sendMaxPlayers' => $sendMaxPlayers,
            'sendTargetLabel' => $sendTargetLabel,
            'pendingAddedNames' => $pendingAddedNames,
            'pendingRemovedNames' => $pendingRemovedNames,
            'modalIsComplete' => $modalIsComplete,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            // Un seul nom pour cet écran : le fil d'Ariane, le titre et l'entrée
            // de menu en disaient trois différents.
            ->current(__('Selections'));
    }

    /**
     * Mirrors the scoping of loadAccessibleTeams(): admins, committee members and
     * selectors reach every team, a captain only the teams they lead.
     */
    /**
     * Delegates to InterclubPolicy::selectLineup, so the rule lives in one place
     * rather than being restated here — this component and the policy answered
     * the same question in two different ways before.
     */
    private function authorizeInterclub(Interclub $interclub): void
    {
        Gate::authorize('selectLineup', $interclub);
    }

    private function buildPoolRow(
        PoolCandidate $candidate,
        ?string $category,
        \Closure $verdictFor,
        \Illuminate\Database\Eloquent\Collection $fixtures,
        ?Team $targetTeam,
        ?Season $season,
    ): array {
        $player = $candidate->user;
        $forceIndex = $player->forceListFor($category);
        $verdict = $verdictFor($forceIndex);

        return [
            'id' => $player->id,
            'name' => $player->last_name . ' ' . $player->first_name,
            'last_name' => $player->last_name ?? '',
            'first_name' => $player->first_name ?? '',
            'rank' => $player->ranking->getLabel(),
            'force_index' => $forceIndex,
            // Les indices manquants en fin de liste : un `null` n'est pas un
            // joueur fort, c'est un joueur qu'on ne sait pas classer.
            'force_sort' => $forceIndex ?? PHP_INT_MAX,
            'availability' => $candidate->availability,
            'availability_note' => $candidate->availabilityNote,
            'origin_team' => $candidate->originTeam->name,
            'phone_number' => $player->phone_number,
            'email' => $player->email,
            // Ce que le capitaine doit surveiller pour rester en règle : un
            // emprunt répété finit par peser sur la feuille de match.
            'played_for_us' => $season && $targetTeam
                ? $this->countPoolFixtures($player->id, $targetTeam->id, $season, $fixtures)
                : 0,
            'legality' => $verdict->state,
            'legality_reason' => $verdict->isWorthShowing() ? $verdict->reason->label() : null,
            'is_illegal' => $verdict->isForbidden(),
        ];
    }

    private function buildSearchNote(Collection $nameMatches, array $excludedIds, callable $matchesCategory, ?string $teamCategory): ?string
    {
        if ($nameMatches->isEmpty()) {
            return null;
        }

        $hiddenByAlignment = $nameMatches->contains(fn (User $u): bool => in_array($u->id, $excludedIds, true));
        $hiddenByCategory = $nameMatches
            ->reject(fn (User $u): bool => in_array($u->id, $excludedIds, true))
            ->contains(fn (User $u): bool => ! $matchesCategory($u));

        $reasons = [];

        if ($hiddenByCategory) {
            $reasons[] = match ($teamCategory) {
                'MEN' => __('this team only lines up men'),
                'WOMEN' => __('this team only lines up women'),
                'VETERANS' => __('this team only lines up veterans (40 and over)'),
                default => null,
            };
        }

        if ($hiddenByAlignment) {
            $reasons[] = __('some are already selected here or lined up in another team this week');
        }

        $reasons = array_filter($reasons);

        if ($reasons === []) {
            return null;
        }

        return __('Some players match your search but are hidden: :reasons.', [
            'reasons' => implode(' ; ', $reasons),
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Interclub>  $fixtures
     * @return array<string, mixed>
     */
    private function buildTeamData(Team $team, \Illuminate\Database\Eloquent\Collection $fixtures): array
    {
        $teamUserIds = $team->users->pluck('id');
        $teamMemberCount = $teamUserIds->count();

        $interclubs = $this->fixturesForTeam($fixtures, $team->id);

        $matches = $interclubs->map(function (Interclub $ic) use ($teamMemberCount): array {
            $ourTeam = $ic->visitedTeam?->club?->is_own_club
                ? $ic->visitedTeam
                : $ic->visitingTeam;

            $isHome = $ic->visitedTeam?->id === $ourTeam?->id;
            $opponentTeam = $isHome ? $ic->visitingTeam : $ic->visitedTeam;
            $opponent = trim(($opponentTeam?->club?->name ?? '') . ' ' . ($opponentTeam?->name ?? '')) ?: '—';

            $icUsers = $ic->users;

            $availableCount = $icUsers->filter(fn ($u): bool => $u->registration?->availability === 'available')->count();
            $maybeCount = $icUsers->filter(fn ($u): bool => $u->registration?->availability === 'maybe')->count();
            $unavailCount = $icUsers->filter(fn ($u): bool => $u->registration?->availability === 'unavailable')->count();
            $respondedCount = $availableCount + $maybeCount + $unavailCount;
            $pendingCount = max(0, $teamMemberCount - $respondedCount);

            $selectedCount = $icUsers->filter(fn ($u) => $u->registration?->is_selected)->count();

            $isPast = $ic->start_date_time < now();
            $daysUntil = (int) now()->diffInDays($ic->start_date_time, false);

            $status = $this->fixtureStatus($ic);

            $selectedPlayerNames = $isPast
                ? $icUsers->filter(fn ($u) => $u->registration?->is_selected)
                    ->map(fn ($u): string => $u->last_name . ' ' . $u->first_name)
                    ->values()
                    ->toArray()
                : [];

            return [
                'id' => $ic->id,
                'wk' => $ic->week_number,
                'date' => $ic->start_date_time->format('d/m/Y'),
                'starts_at' => $ic->start_date_time->getTimestamp(),
                'time' => $ic->start_date_time->format('H:i'),
                'opponent' => $opponent,
                'is_home' => $isHome,
                'status' => $status,
                'is_past' => $isPast,
                'days_until' => $daysUntil,
                'available_count' => $availableCount,
                // Le sondage et la capacité à aligner une équipe sont deux
                // questions différentes : « 3 dispo sur 4 » n'en répondait
                // clairement à aucune des deux.
                'responded_count' => $respondedCount,
                'team_member_count' => $teamMemberCount,
                'maybe_count' => $maybeCount,
                'unavail_count' => $unavailCount,
                'pending_count' => $pendingCount,
                'selected_count' => $selectedCount,
                'max_players' => $ic->total_players,
                'selected_player_names' => $selectedPlayerNames,
            ];
        });

        return [
            'id' => $team->id,
            'name' => $team->name,
            'division' => $team->league?->division ?? '—',
            'captain_name' => trim(($team->captain?->last_name ?? '') . ' ' . ($team->captain?->first_name ?? '')),
            'matches' => $matches->values()->toArray(),
            'has_alert' => $matches->where('status', 'urgent')->isNotEmpty(),
        ];
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'MEN' => __('Men'),
            'WOMEN' => __('Women'),
            'VETERANS' => __('Veterans'),
            default => $category,
        };
    }

    /**
     * Combien de fois ce joueur a déjà été aligné dans cette équipe cette saison.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Interclub>  $fixtures
     */
    private function countPoolFixtures(int $userId, int $teamId, Season $season, \Illuminate\Database\Eloquent\Collection $fixtures): int
    {
        return $this->fixturesForTeam($fixtures, $teamId)
            ->filter(fn (Interclub $ic): bool => $ic->season_id === $season->id
                && $ic->users->contains(fn (User $u): bool => $u->id === $userId && (bool) $u->registration?->is_selected))
            ->count();
    }

    /**
     * A season change can strand the selection on a team that does not exist in
     * the new season. Falling back to the first reachable team keeps the page's
     * "exactly one team, never none" invariant true.
     */
    private function ensureSelectedTeamIsReachable(): void
    {
        $user = Auth::user();
        $season = $this->selectedSeasonId ? Season::find($this->selectedSeasonId) : Season::current();
        $teams = $this->loadAccessibleTeams($user, $season);

        if (! $teams->contains('id', $this->selectedTeamId)) {
            $this->selectedTeamId = $teams->first()?->id;
        }
    }

    /**
     * Which day to land on. The first one still holding work, or failing that
     * the first one still to be played — never day 1 of a season that is over.
     *
     * @param  Collection<int, array<string, mixed>>  $teamsData
     * @param  array<int, int>  $matchDays
     */
    private function firstMatchDayNeedingAttention(Collection $teamsData, array $matchDays): ?int
    {
        $matches = $teamsData->flatMap(fn (array $t): array => $t['matches']);

        // Par coup d'envoi, pas par numéro de semaine : la journée la plus proche
        // n'est pas celle qui porte le plus petit numéro.
        $needsWork = $matches
            ->filter(fn (array $m): bool => in_array($m['status'], ['urgent', 'actionable'], true))
            ->sortBy('starts_at')
            ->pluck('wk')
            ->first();

        if ($needsWork !== null) {
            return $needsWork;
        }

        $upcoming = $matches
            ->reject(fn (array $m): bool => $m['is_past'])
            ->sortBy('starts_at')
            ->pluck('wk')
            ->first();

        return $upcoming ?? ($matchDays === [] ? null : end($matchDays));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Interclub>  $fixtures
     * @return \Illuminate\Database\Eloquent\Collection<int, Interclub>
     */
    private function fixturesForTeam(\Illuminate\Database\Eloquent\Collection $fixtures, int $teamId): \Illuminate\Database\Eloquent\Collection
    {
        return $fixtures->filter(fn (Interclub $ic): bool => $ic->visited_team_id === $teamId
            || $ic->visiting_team_id === $teamId)->values();
    }

    /**
     * The rule lives in InterclubPreparationService; this page only reads it.
     * Resolved once per render rather than once per fixture — this runs for
     * every fixture of every team, on every click.
     */
    private function fixtureStatus(Interclub $interclub): string
    {
        return ($this->preparation ??= app(InterclubPreparationService::class))
            ->fixtureStatus($interclub);
    }

    /**
     * A captain opens this page to answer "what needs me today?". Chronological
     * order answered "what happened first?" instead, and put the fixtures already
     * played at the top. The four groups are ordered by that question, and they
     * are the same in both reading directions.
     *
     * "Under control" is deliberately its own group rather than part of what is
     * coming: a lineup already sent is a settled deadline, not an approaching one.
     *
     * @param  array<int, array<string, mixed>>  $matches
     * @return array{todo: array<int, array<string, mixed>>, controlled: array<int, array<string, mixed>>, upcoming: array<int, array<string, mixed>>, played: array<int, array<string, mixed>>}
     */
    private function groupMatches(array $matches): array
    {
        $groups = ['todo' => [], 'controlled' => [], 'upcoming' => [], 'played' => []];

        foreach ($matches as $match) {
            $key = match (true) {
                $match['is_past'] => 'played',
                in_array($match['status'], ['urgent', 'actionable'], true) => 'todo',
                in_array($match['status'], InterclubPreparationService::SETTLED, true) => 'controlled',
                default => 'upcoming',
            };

            $groups[$key][] = $match;
        }

        // Most recent first: the last result is the one a captain looks up.
        $groups['played'] = array_reverse($groups['played']);

        if ($this->filterAlerts) {
            return ['todo' => $groups['todo'], 'controlled' => [], 'upcoming' => [], 'played' => []];
        }

        return $groups;
    }

    /**
     * Explain why a substitute search returned nothing: matching competitors do
     * exist, but the category rule and/or the same-week alignment hid them (I2).
     *
     * @param  Collection<int, User>  $nameMatches
     * @param  array<int, int>  $excludedIds
     * @param  callable(User): bool  $matchesCategory
     */
    /**
     * Une ligne du pool : le joueur, d'où il vient, et ce que C.22 en dit.
     *
     * Les coordonnées y figurent, opt-in `contact_visibility` outrepassé. C'est
     * l'élargissement délibéré de la décision T8, qui ne valait jusqu'ici que
     * pour l'effectif du capitaine : le samedi matin à neuf heures, un capitaine
     * appelle — il n'envoie pas une invitation et n'attend pas une réponse.
     *
     * @param  \Closure(?int): LineupVerdict  $verdictFor
     * @param  \Illuminate\Database\Eloquent\Collection<int, Interclub>  $fixtures
     * @return array<string, mixed>
     */
    /**
     * L'article C.22 interdit-il formellement d'ajouter ce joueur ?
     *
     * Seul le rouge bloque : l'orange dépend d'une composition que personne n'a
     * encore faite, et refuser sur une incertitude coûterait plus cher que de
     * laisser le capitaine décider en connaissance de cause.
     */
    private function isForbiddenByRule(int $userId, Interclub $interclub): bool
    {
        ['verdict' => $verdictFor] = $this->lineupLegality($interclub, $this->selectedPlayerIds);

        $player = User::find($userId);

        if (! $player instanceof User) {
            return false;
        }

        $interclub->loadMissing('league');

        return $verdictFor($player->forceListFor($interclub->league?->category))->isForbidden();
    }

    private function loadAccessibleTeams(User $user, ?Season $season): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = (string) ($season?->id ?? 'none');

        if (isset($this->accessibleTeamsCache[$cacheKey])) {
            return $this->accessibleTeamsCache[$cacheKey];
        }

        $query = Team::with(['league', 'club', 'captain', 'users'])->inClub();

        if ($season) {
            $query->where('season_id', $season->id);
        }

        // A club-wide selector sees every team; a captain, only theirs.
        if (! $user->can(Permission::SelectionsManage->value)) {
            $query->where('captain_id', $user->id);
        }

        return $this->accessibleTeamsCache[$cacheKey] = $query->orderBy('name')->get();
    }

    /**
     * Every fixture the render needs, in one query. Both the team cards and the
     * preparation matrix filter this collection in memory instead of querying
     * per team and per cell.
     *
     * @param  array<int, int>  $teamIds
     * @return \Illuminate\Database\Eloquent\Collection<int, Interclub>
     */
    private function loadFixtures(array $teamIds): \Illuminate\Database\Eloquent\Collection
    {
        if ($teamIds === []) {
            return Interclub::newModelInstance()->newCollection();
        }

        return Interclub::with(['league', 'visitedTeam.club', 'visitingTeam.club', 'users'])
            ->where(fn ($q) => $q
                ->whereIn('visited_team_id', $teamIds)
                ->orWhereIn('visiting_team_id', $teamIds))
            ->orderBy('start_date_time')
            ->orderBy('id')
            ->get();
    }

    /** The other club's side of a fixture, named for display. */
    private function opponentNameOf(Interclub $interclub): string
    {
        $ourTeam = $this->ownTeamOf($interclub);
        $opponent = $interclub->visitedTeam?->id === $ourTeam?->id
            ? $interclub->visitingTeam
            : $interclub->visitedTeam;

        return trim(($opponent?->club?->name ?? '') . ' ' . ($opponent?->name ?? '')) ?: '—';
    }

    /**
     * The own-club side of a fixture. Both sides are teams; only one of them is
     * ours, and which one it is decides whose roster the drawer shows.
     */
    private function ownTeamOf(Interclub $interclub): ?Team
    {
        return $interclub->visitedTeam?->club?->is_own_club
            ? $interclub->visitedTeam
            : $interclub->visitingTeam;
    }

    private function resetSendModal(): void
    {
        $this->sendTargetId = null;
        $this->modalMessage = false;
        $this->captainMeetupInfo = '';
        $this->isUpdateMode = false;
        $this->pendingAddedIds = [];
        $this->pendingRemovedIds = [];
        $this->sendsShortHanded = false;
        $this->shortHandedOptIn = false;
    }

    private function selectedInterclub(): ?Interclub
    {
        if (! $this->selectedInterclubId) {
            return null;
        }

        $interclub = Interclub::find($this->selectedInterclubId);

        if ($interclub) {
            $this->authorizeInterclub($interclub);
        }

        return $interclub;
    }

    private function sendTarget(): ?Interclub
    {
        if (! $this->sendTargetId) {
            return null;
        }

        $interclub = Interclub::find($this->sendTargetId);

        if ($interclub) {
            $this->authorizeInterclub($interclub);
        }

        return $interclub;
    }
};
