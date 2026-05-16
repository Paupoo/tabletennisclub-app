<?php

declare(strict_types=1);

use App\Data\Tournament\SimulationResult;
use App\Data\Tournament\TournamentConfig;
use App\Enums\NewsPostCategoryEnum;
use App\Enums\NewsPostStatusEnum;
use App\Enums\TournamentObjectiveEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Club\Table;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Pool;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubPosts\NewsPost;
use App\Notifications\Tournament\TournamentCancelledNotification;
use App\Notifications\Tournament\TournamentInvitationNotification;
use App\Notifications\Tournament\TournamentUpdatedNotification;
use App\Notifications\Tournament\TournamentWaitlistRemovedNotification;
use App\Services\TournamentMatchService;
use App\Services\TournamentPoolService;
use App\Services\TournamentService;
use App\Services\TournamentSimulator;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, WithFileUploads;

    public string $articleContent = '';

    public ?string $articleExistingImage = null;

    public mixed $articleImage = null;

    public string $articleSavedStatus = '';

    public string $articleTitle = '';

    public bool $bulkDrawer = false;

    public bool $inviteIncludeArticle = false;

    public string $inviteMessage = '';

    public int $logistics_buffer = 3;

    public string $matchType = 'single';

    // ── Limite d'inscriptions (0 = illimité)
    public int $maxUsers = 0;

    // ── Étape 2 – Invitations
    public string $memberSearch = '';

    // ── Étape 1 – Config principale
    public string $name = '';

    public int $nb_poules = 4;

    public int $nb_qualifies = 2;

    /** Manual fallback when no rooms are selected. */
    public int $nb_tables = 8;

    public int $pool_size = 4;

    // ── Options statiques
    public array $poolSizeOptions = [
        ['id' => 3, 'name' => '3 joueurs'],
        ['id' => 4, 'name' => '4 joueurs'],
        ['id' => 5, 'name' => '5 joueurs'],
        ['id' => 6, 'name' => '6 joueurs'],
    ];

    // ── Pools staleness flag
    public bool $poolsStale = false;

    // ── Frais d'inscription (0 = gratuit)
    public float $price = 0;

    // ── Article
    public string $publicationDate = '';

    public bool $publicRegistration = false;

    public ?int $publishedArticleId = null;

    public array $selectedMembers = [];

    public string $selectedObjective = '';

    // ── Étape 3 – Inscriptions
    public array $selectedPeople = [];

    // ── Contraintes physiques
    public array $selectedRooms = [];

    public array $selectedTags = [];

    public array $setOptions = [
        ['id' => 1, 'name' => '1'],
        ['id' => 2, 'name' => '2'],
        ['id' => 3, 'name' => '3'],
        ['id' => 4, 'name' => '4'],
        ['id' => 5, 'name' => '5'],
    ];

    // ── UI state
    public bool $showCancelModal = false;

    public bool $showRequireCloseRegistrationsModal = false;

    public bool $showCloseRegistrationsModal = false;

    public bool $showInviteModal = false;

    public bool $showOpenRegistrationsModal = false;

    public bool $showLaunchModal = false;

    public bool $showPublishModal = false;

    public bool $showRegisterModal = false;

    public array $sortBy = ['column' => 'registered_at', 'direction' => 'asc'];

    public string $startTime = '';

    public string $step = '1';

    public array $tagOptions = [
        ['id' => 1, 'name' => 'Tournoi'],
        ['id' => 2, 'name' => 'Interclubs'],
        ['id' => 3, 'name' => 'Jeunes'],
    ];

    // ── Paramètres sportifs
    public int $totalSets = 3;

    public bool $deuceEnabled = true;

    public bool $hasHandicapPoints = true;

    public string $registration_deadline = '';

    public int $memberToRegister = 0;

    public int $tournament_minutes = 180;

    public string $tournamentDate = '';

    // ── Identity (create vs edit)
    public ?int $tournamentId = null;

    // ── Objective suggestion

    public function applyObjectiveSuggestion(): void
    {
        if (empty($this->selectedObjective)) {
            $this->warning(__('Please select an objective first.'));

            return;
        }

        $config = app(TournamentSimulator::class)->suggestOptimalConfig(
            durationMinutes: $this->tournament_minutes,
            nbTables: $this->nbTables,
            objective: TournamentObjectiveEnum::from($this->selectedObjective),
        );

        $this->nb_poules = $config->nbPools;
        $this->pool_size = $config->poolSize;
        $this->nb_qualifies = $config->nbQualifiersPerPool;
        $this->totalSets = $config->setsToWin;
        $this->deuceEnabled = $config->deuceEnabled;
        $this->hasHandicapPoints = $config->hasHandicapPoints;
        $this->logistics_buffer = $config->logisticsBufferMinutes;
        $this->matchType = $config->matchType;

        $this->success(
            title: __('Suggestion applied!'),
            description: TournamentObjectiveEnum::from($this->selectedObjective)->label(),
            icon: 'o-sparkles',
        );
    }

    // ── Computed: rooms from DB

    #[Computed]
    public function availableRooms(): array
    {
        return Room::select(['id', 'name', 'total_playable_tables'])
            ->orderBy('name')
            ->get()
            ->map(fn ($room) => [
                'id' => $room->id,
                'name' => $room->name . ' (' . $room->total_playable_tables . ' tables)',
            ])
            ->toArray();
    }

    public function cancelUserRegistration(int $userId): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);
        $user = User::findOrFail($userId);

        app(TournamentService::class)->cancelRegistration($tournament, $user);

        unset($this->registrations);
        unset($this->waitlist);

        $this->error($user->full_name . ' ' . __('has been unregistered.'));
    }

    public function confirmBulkCancel(): void
    {
        if (empty($this->selectedPeople) || ! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);
        $service = app(TournamentService::class);

        foreach ($this->selectedPeople as $userId) {
            $user = User::find($userId);
            if ($user) {
                $service->cancelRegistration($tournament, $user);
            }
        }

        $this->resetPostAction();
        $this->error(__('Registrations cancelled.'));
    }

    public function confirmBulkNoShow(): void
    {
        if (empty($this->selectedPeople) || ! $this->tournamentId) {
            return;
        }

        DB::table('tournament_user')
            ->where('tournament_id', $this->tournamentId)
            ->whereIn('user_id', $this->selectedPeople)
            ->update(['registration_status' => 'no_show']);

        $this->resetPostAction();
        $this->warning(__('No-shows recorded.'));
    }

    public function confirmBulkPresence(): void
    {
        if (empty($this->selectedPeople) || ! $this->tournamentId) {
            return;
        }

        DB::table('tournament_user')
            ->where('tournament_id', $this->tournamentId)
            ->whereIn('user_id', $this->selectedPeople)
            ->update(['registration_status' => 'confirmed']);

        $count = count($this->selectedPeople);
        $this->resetPostAction();
        $this->success("{$count} " . __('presences confirmed.'));
    }

    public function confirmPresence(int $userId): void
    {
        if (! $this->tournamentId) {
            return;
        }

        DB::table('tournament_user')
            ->where('tournament_id', $this->tournamentId)
            ->where('user_id', $userId)
            ->update(['registration_status' => 'confirmed']);

        unset($this->registrations);
        $this->success(__('Presence confirmed.'));
    }

    // ── Computed: current tournament model

    #[Computed]
    public function currentTournament(): ?Tournament
    {
        return $this->tournamentId ? Tournament::find($this->tournamentId) : null;
    }

    public function generateMatches(): void
    {
        if (! $this->poolsGenerated) {
            $this->error(__('Generate pools first.'));

            return;
        }

        $tournament = Tournament::with(['pools.users', 'pools.tournament'])->findOrFail($this->tournamentId);
        app(TournamentMatchService::class)->generateTournamentMatches($tournament);

        $this->success(__('Matches generated!'), icon: 'o-table-cells');
    }

    // ── Pools

    public function generatePools(): void
    {
        if (! $this->tournamentId) {
            $this->error(__('Save the tournament first.'));

            return;
        }

        $tournament = Tournament::with('users')->findOrFail($this->tournamentId);

        if ($tournament->users()->count() === 0) {
            $this->error(__('No registered players.'));

            return;
        }

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, $this->nb_poules);

        $this->poolsStale = false;
        $this->success(__('Pools generated!'), icon: 'o-user-group');
    }

    /** True when at least one player is registered or confirmed. */
    #[Computed]
    public function hasRegisteredUsers(): bool
    {
        if (! $this->tournamentId) {
            return false;
        }

        return DB::table('tournament_user')
            ->where('tournament_id', $this->tournamentId)
            ->whereIn('registration_status', ['registered', 'confirmed', 'spot_offered'])
            ->exists();
    }

    // ── Computed: invitation history

    #[Computed]
    public function invitationHistory(): array
    {
        if (! $this->tournamentId) {
            return [];
        }

        return DB::table('tournament_invitations')
            ->where('tournament_id', $this->tournamentId)
            ->orderByDesc('sent_at')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'count' => $row->user_count,
                'sent_at' => $row->sent_at,
                'status' => 'Envoyé',
            ])
            ->toArray();
    }

    // ── Computed: field-locking milestones

    /** Name + price are locked once the tournament is validated (status LOCKED or beyond). */
    #[Computed]
    public function isContractLocked(): bool
    {
        if (! $this->tournamentId) {
            return false;
        }

        $status = $this->currentTournament?->status;

        return $status !== null && ! in_array($status, [
            TournamentStatusEnum::DRAFT,
            TournamentStatusEnum::CANCELLED,
        ]);
    }

    public function validateAndLock(): void
    {
        if (! $this->tournamentId) {
            return;
        }

        if (empty($this->name) || empty($this->registration_deadline)) {
            $this->error(__('Tournament name and registration deadline are required before locking.'));

            return;
        }

        Tournament::findOrFail($this->tournamentId)->update(['status' => TournamentStatusEnum::LOCKED]);

        unset($this->currentTournament, $this->isContractLocked);

        $this->step = '4';
        $this->success(__('Tournament validated! Name and price are now locked.'), icon: 'o-lock-closed');
    }

    public function cancelTournament(): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::with('users')->findOrFail($this->tournamentId);
        $tournament->update(['status' => TournamentStatusEnum::CANCELLED]);

        $tournament->users()
            ->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered', 'waiting'])
            ->get()
            ->each->notify(new TournamentCancelledNotification($tournament));

        unset($this->currentTournament, $this->isContractLocked);
        $this->showCancelModal = false;
        $this->success(__('Tournament cancelled. All registered players have been notified.'), icon: 'o-x-circle');
    }

    public function registerMember(): void
    {
        if (! $this->tournamentId || ! $this->memberToRegister) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);
        $user = User::findOrFail($this->memberToRegister);

        try {
            app(TournamentService::class)->registerUser($tournament, $user);
        } catch (\LogicException $e) {
            $this->error($e->getMessage());

            return;
        }

        unset($this->registrations, $this->waitlist, $this->members);

        $this->memberToRegister = 0;
        $this->showRegisterModal = false;
        $this->success($user->full_name . ' ' . __('has been registered.'));
    }

    #[Computed]
    public function registerableMembersOptions(): array
    {
        $alreadyRegistered = $this->tournamentId
            ? DB::table('tournament_user')
                ->where('tournament_id', $this->tournamentId)
                ->whereIn('registration_status', ['registered', 'confirmed', 'spot_offered', 'waiting'])
                ->pluck('user_id')
                ->toArray()
            : [];

        return User::where('is_active', true)
            ->whereNotIn('id', $alreadyRegistered)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->full_name . ' (' . ($u->ranking ?? 'NC') . ')',
            ])
            ->toArray();
    }

    // ── Launch

    public function launch(): void
    {
        if (! $this->registrationClosed) {
            $this->showRequireCloseRegistrationsModal = true;

            return;
        }

        if (! $this->matchesGenerated) {
            $this->error(__('Generate matches first.'));

            return;
        }

        if ($this->poolsStale) {
            $this->error(__('Configuration changed — regenerate pools and matches first.'));

            return;
        }

        $this->showLaunchModal = true;
        $this->js('$wire.processLaunch()');
    }

    public function confirmCloseAndLaunch(): void
    {
        $this->confirmCloseRegistrations();
        $this->showRequireCloseRegistrationsModal = false;
        $this->launch();
    }

    public function markNoShow(int $userId): void
    {
        if (! $this->tournamentId) {
            return;
        }

        DB::table('tournament_user')
            ->where('tournament_id', $this->tournamentId)
            ->where('user_id', $userId)
            ->update(['registration_status' => 'no_show']);

        unset($this->registrations);
        $this->warning(__('No-show recorded.'));
    }

    // ── Computed: matches list for verification

    #[Computed]
    public function matchesByPool(): array
    {
        if (! $this->tournamentId) {
            return [];
        }

        return Tournament::find($this->tournamentId)
            ->matches()
            ->with(['player1', 'player2', 'pool'])
            ->whereNotNull('pool_id')
            ->orderBy('pool_id')
            ->orderBy('match_order')
            ->get()
            ->groupBy('pool_id')
            ->map(fn ($matches, $poolId) => [
                'name' => $matches->first()->pool?->name ?? "Pool {$poolId}",
                'matches' => $matches->map(fn ($m) => [
                    'order' => $m->match_order,
                    'p1' => $m->player1?->full_name ?? '—',
                    'p2' => $m->player2?->full_name ?? '—',
                ])->toArray(),
            ])
            ->toArray();
    }

    #[Computed]
    public function matchesGenerated(): bool
    {
        return $this->tournamentId !== null
            && (bool) Tournament::find($this->tournamentId)?->matches()->exists();
    }

    // ── Computed: members from DB

    #[Computed]
    public function members(): array
    {
        $query = User::where('is_active', true)->orderBy('last_name');

        if ($this->selectedObjective === TournamentObjectiveEnum::Competitive->value) {
            $query->where('is_competitor', true);
        }

        if ($this->tournamentId) {
            $alreadyInvolved = DB::table('tournament_user')
                ->where('tournament_id', $this->tournamentId)
                ->whereIn('registration_status', ['registered', 'confirmed', 'spot_offered', 'waiting'])
                ->pluck('user_id');

            $query->whereNotIn('id', $alreadyInvolved);
        }

        return $query->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->full_name,
                'email' => $u->email,
                'ranking' => $u->ranking ?? 'NC',
            ])
            ->toArray();
    }

    // ── Lifecycle

    public function mount(?Tournament $tournament = null): void
    {
        $this->publicationDate = today()->format('Y-m-d');
        $this->tournamentDate = today()->addWeek()->format('Y-m-d');

        if ($tournament !== null) {
            $this->tournamentId = $tournament->id;
            $this->name = $tournament->name;
            $this->tournamentDate = $tournament->start_date?->format('Y-m-d') ?? $this->tournamentDate;
            $this->startTime = $tournament->start_time ?? '';
            $this->registration_deadline = $tournament->registration_deadline?->format('Y-m-d') ?? '';
            $this->publicRegistration = false;
            $this->tournament_minutes = $tournament->duration_minutes;
            $this->logistics_buffer = $tournament->logistics_buffer_minutes;
            $this->totalSets = $tournament->sets_to_win;
            $this->deuceEnabled = $tournament->deuce_enabled;
            $this->hasHandicapPoints = $tournament->has_handicap_points;
            $this->matchType = $tournament->match_type;
            $this->nb_poules = $tournament->nb_pools;
            $this->pool_size = $tournament->pool_size;
            $this->nb_qualifies = $tournament->nb_qualifiers_per_pool;
            $this->maxUsers = $tournament->max_users;
            $this->price = (float) ($tournament->price ?? 0);
            $this->selectedObjective = $tournament->objective?->value ?? '';
            $this->selectedRooms = $tournament->rooms->pluck('id')->toArray();
            $this->nb_tables = (int) $tournament->rooms->sum('total_playable_tables') ?: 8;

            $this->step = match ($tournament->status) {
                TournamentStatusEnum::LOCKED    => '4',
                TournamentStatusEnum::PUBLISHED => '4',
                TournamentStatusEnum::SETUP     => '5',
                TournamentStatusEnum::PENDING,
                TournamentStatusEnum::CLOSED    => '6',
                default                         => '1',
            };

            $newsPost = $tournament->newsPost;
            if ($newsPost) {
                $this->publishedArticleId = $newsPost->id;
                $this->articleTitle = $newsPost->title;
                $this->articleContent = $newsPost->content ?? '';
                $this->articleExistingImage = $newsPost->image;
                $this->articleSavedStatus = $newsPost->status->value;
            } else {
                $this->articleTitle = $tournament->name;
            }
        }
    }

    // ── Computed: effective table count

    #[Computed]
    public function nbTables(): int
    {
        if (empty($this->selectedRooms)) {
            return $this->nb_tables;
        }

        $total = Room::whereIn('id', $this->selectedRooms)->sum('total_playable_tables');

        return (int) ($total ?: $this->nb_tables);
    }

    // ── Computed: pools from DB

    #[Computed]
    public function pools(): array
    {
        if (! $this->tournamentId) {
            return [];
        }

        return Tournament::find($this->tournamentId)
            ->pools()
            ->with(['users' => fn ($q) => $q
                ->orderByRaw('ranking IS NULL')
                ->orderBy('ranking')
                ->orderBy('last_name')
                ->orderBy('first_name'),
            ])
            ->get()
            ->mapWithKeys(fn (Pool $pool) => [
                $pool->id => [
                    'name' => $pool->name,
                    'players' => $pool->users->map(fn (User $u) => [
                        'id' => $u->id,
                        'name' => $u->full_name,
                        'rank' => $u->ranking ?? 'NC',
                        'pts' => 0,
                    ])->toArray(),
                ],
            ])
            ->toArray();
    }

    // ── Computed: pool/match generation guards

    #[Computed]
    public function poolsGenerated(): bool
    {
        return $this->tournamentId !== null
            && (bool) Tournament::find($this->tournamentId)?->pools()->exists();
    }

    public function processLaunch(): mixed
    {
        if (! $this->tournamentId) {
            return null;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);

        if (! $tournament->pools()->exists()) {
            $this->error(__('Generate pools first.'));

            return null;
        }

        if (! $tournament->matches()->exists()) {
            $this->error(__('Generate matches first.'));

            return null;
        }

        $tournament->update(['status' => TournamentStatusEnum::PENDING]);

        // Populate table_tournament pivot from the tournament's linked rooms
        $tableIds = Table::whereHas('room', fn ($q) => $q->whereIn('rooms.id', $tournament->rooms()->pluck('rooms.id')))
            ->pluck('id');

        $tournament->tables()->sync(
            $tableIds->mapWithKeys(fn ($id) => [$id => ['is_table_free' => true]])->all()
        );

        return redirect()->route('admin.tournaments.live-center', $tournament->id);
    }

    public function promoteFromWaitlist(int $userId): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);

        if ($tournament->activeRegistrationsCount() >= $tournament->max_users && $tournament->max_users > 0) {
            $this->error(__('No available spot. Cancel a registration first.'));

            return;
        }

        $user = User::findOrFail($userId);

        DB::table('tournament_user')
            ->where('tournament_id', $this->tournamentId)
            ->where('user_id', $userId)
            ->update([
                'registration_status' => 'registered',
                'waitlist_position' => null,
            ]);

        app(TournamentService::class)->countRegisteredUsers($tournament);
        unset($this->registrations);
        unset($this->waitlist);

        $this->success($user->full_name . ' ' . __('has been moved to the registered list.'));
    }

    public function publishArticle(string $status = 'draft'): void
    {
        $this->validate([
            'articleTitle' => 'required|min:5',
            'articleContent' => 'required',
            'articleImage' => 'nullable|image|max:4096',
        ]);

        $imagePath = $this->articleExistingImage;

        if ($this->articleImage) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $this->articleImage->store('clubPosts', 'public');
            $this->articleExistingImage = $imagePath;
            $this->articleImage = null;
        }

        $newsStatus = NewsPostStatusEnum::from($status);

        $baseSlug = Str::slug($this->articleTitle);
        $slug = $baseSlug;
        $i = 1;
        while (
            NewsPost::where('slug', $slug)
                ->when($this->publishedArticleId, fn ($q) => $q->where('id', '!=', $this->publishedArticleId))
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $i++;
        }

        $data = [
            'title' => $this->articleTitle,
            'slug' => $slug,
            'content' => $this->articleContent,
            'category' => NewsPostCategoryEnum::COMPETITION,
            'status' => $newsStatus,
            'is_public' => $newsStatus === NewsPostStatusEnum::PUBLISHED,
            'image' => $imagePath,
            'user_id' => auth()->id(),
        ];

        if ($this->publishedArticleId) {
            NewsPost::findOrFail($this->publishedArticleId)->update($data);
        } else {
            $newsPost = NewsPost::create($data);
            $this->publishedArticleId = $newsPost->id;

            if ($this->tournamentId) {
                Tournament::whereKey($this->tournamentId)->update(['news_post_id' => $newsPost->id]);
            }
        }

        $this->articleSavedStatus = $status;

        $this->success(
            title: $newsStatus === NewsPostStatusEnum::PUBLISHED
                ? __('Article published!')
                : __('Article saved as draft!'),
            description: $newsStatus === NewsPostStatusEnum::DRAFT
                ? __('You can now include a link to it in your invitations.')
                : null,
            icon: 'o-document-text',
        );

        $this->showPublishModal = false;
    }

    // ── Computed: registration status

    #[Computed]
    public function registrationClosed(): bool
    {
        return $this->currentTournament !== null && $this->currentTournament->status === TournamentStatusEnum::SETUP;
    }

    #[Computed]
    public function isLaunched(): bool
    {
        $status = $this->currentTournament?->status;

        return $status !== null && in_array($status, [TournamentStatusEnum::PENDING, TournamentStatusEnum::CLOSED]);
    }

    // ── Computed: active registrations (not waiting, not cancelled)

    #[Computed]
    public function registrations(): Collection
    {
        if (! $this->tournamentId) {
            return collect();
        }

        $col = $this->sortBy['column'];
        $dir = $this->sortBy['direction'];

        $rows = Tournament::findOrFail($this->tournamentId)
            ->users()
            ->wherePivotIn('registration_status', ['registered', 'confirmed', 'spot_offered', 'no_show'])
            ->get()
            ->map(fn (User $u) => [
                'id'            => $u->id,
                'name'          => $u->full_name,
                'ranking'       => $u->ranking ?? 'NC',
                'status'        => $u->pivot->registration_status,
                'has_paid'      => (bool) $u->pivot->has_paid,
                'payment_deadline' => $u->pivot->payment_deadline,
                'registered_at' => $u->pivot->created_at,
            ]);

        return $dir === 'asc'
            ? $rows->sortBy($col)->values()
            : $rows->sortByDesc($col)->values();
    }

    public function removeFromWaitlist(int $userId): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);
        $user = User::find($userId);

        DB::table('tournament_user')
            ->where('tournament_id', $this->tournamentId)
            ->where('user_id', $userId)
            ->update(['registration_status' => 'cancelled']);

        if ($user) {
            $user->notify(new TournamentWaitlistRemovedNotification($tournament));
        }

        unset($this->registrations);
        unset($this->waitlist);

        $this->success(__('Removed from waiting list.'));
    }

    // ── Render

    public function render(): mixed
    {
        $search = strtolower($this->memberSearch);
        $filteredMembers = empty($search)
            ? $this->members
            : array_values(array_filter(
                $this->members,
                fn ($m) => str_contains(strtolower($m['name']), $search)
                    || str_contains(strtolower($m['email'] ?? ''), $search)
            ));

        return $this->view([
            'filteredMembers' => $filteredMembers,
            'markdownPreview' => Str::markdown($this->articleContent ?: ''),
        ]);
    }

    // ── Save (create or update)

    public function save(): void
    {
        $this->validate([
            'name' => 'required|min:3|max:255',
            'tournamentDate' => 'required|date',
            'tournament_minutes' => 'required|integer|min:30|max:1440',
            'nb_poules' => 'required|integer|min:1|max:64',
            'pool_size' => 'required|integer|min:2|max:10',
            'nb_qualifies' => 'required|integer|min:1',
            'totalSets' => 'required|integer|min:1|max:5',
            'deuceEnabled' => 'boolean',
            'hasHandicapPoints' => 'boolean',
            'logistics_buffer' => 'required|integer|min:0|max:30',
            'matchType' => 'required|in:single,double',
            'maxUsers' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
        ]);

        // Snapshot logistical values before saving so we can detect changes.
        $logisticsChanged = [];
        if ($this->tournamentId) {
            $existing = Tournament::with('rooms')->find($this->tournamentId);
            if ($existing) {
                $oldDate = $existing->start_date?->format('Y-m-d');
                $oldTime = $existing->start_time;
                $oldRooms = $existing->rooms->pluck('id')->sort()->values()->toArray();

                if ($oldDate !== $this->tournamentDate) {
                    $logisticsChanged[] = 'date';
                }
                if ($oldTime !== ($this->startTime ?: null)) {
                    $logisticsChanged[] = 'time';
                }
                $newRooms = collect($this->selectedRooms)->sort()->values()->toArray();
                if ($oldRooms !== $newRooms) {
                    $logisticsChanged[] = 'rooms';
                }
            }
        }

        $tournament = Tournament::updateOrCreate(
            ['id' => $this->tournamentId],
            [
                'name' => $this->name,
                'start_date' => $this->tournamentDate,
                'start_time' => $this->startTime ?: null,
                'duration_minutes' => $this->tournament_minutes,
                'pool_size' => $this->pool_size,
                'nb_pools' => $this->nb_poules,
                'nb_qualifiers_per_pool' => $this->nb_qualifies,
                'sets_to_win' => $this->totalSets,
                'deuce_enabled' => $this->deuceEnabled,
                'has_handicap_points' => $this->hasHandicapPoints,
                'logistics_buffer_minutes' => $this->logistics_buffer,
                'match_type' => $this->matchType,
                'objective' => $this->selectedObjective ?: null,
                'max_users' => $this->maxUsers,
                'price' => $this->price,
                'registration_deadline' => $this->registration_deadline ?: null,
            ]
        );

        $tournament->rooms()->sync($this->selectedRooms);

        $this->tournamentId = $tournament->id;

        // Notify registered players when logistical details changed.
        if (! empty($logisticsChanged) && $this->hasRegisteredUsers) {
            unset($this->hasRegisteredUsers);
            $tournament->users()
                ->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered'])
                ->get()
                ->each->notify(new TournamentUpdatedNotification($tournament, $logisticsChanged));
        }

        unset($this->isContractLocked, $this->hasRegisteredUsers, $this->currentTournament);

        if ($this->step === '1') {
            $this->step = '2';
        }

        $this->success(
            title: __('Tournament saved!'),
            description: __('Configuration updated.'),
            icon: 'o-check-circle',
        );
    }

    // ── Invitations

    public function selectAllMembers(): void
    {
        $this->selectedMembers = array_column($this->members, 'id');
    }

    public function selectNoMembers(): void
    {
        $this->selectedMembers = [];
    }

    public function sendInvitations(): void
    {
        if (empty($this->selectedMembers) || ! $this->tournamentId) {
            return;
        }

        if (empty($this->registration_deadline)) {
            $this->error(__('A registration deadline is required before sending invitations.'));

            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);

        if (! in_array($tournament->status, [TournamentStatusEnum::LOCKED, TournamentStatusEnum::PUBLISHED])) {
            $this->error(__('Invitations cannot be sent while registrations are closed.'));

            return;
        }
        $users = User::whereIn('id', $this->selectedMembers)->get();

        $notification = new TournamentInvitationNotification(
            tournament: $tournament,
            customMessage: $this->inviteMessage,
            includeArticleLink: $this->inviteIncludeArticle && $this->publishedArticleId !== null,
            newsPostId: $this->inviteIncludeArticle ? $this->publishedArticleId : null,
        );

        foreach ($users as $user) {
            $user->notify($notification);
        }

        DB::table('tournament_invitations')->insert([
            'tournament_id' => $this->tournamentId,
            'user_count' => $users->count(),
            'message' => $this->inviteMessage ?: null,
            'include_article' => $this->inviteIncludeArticle && $this->publishedArticleId !== null,
            'sent_at' => now(),
        ]);

        $count = $users->count();
        $this->success(
            title: __('Invitations sent!'),
            description: "{$count} " . __('members have been notified.'),
            icon: 'o-paper-airplane',
        );

        // First invitation transitions locked → published and advances to registrations.
        if ($tournament->status === TournamentStatusEnum::LOCKED) {
            $tournament->update(['status' => TournamentStatusEnum::PUBLISHED]);
            unset($this->currentTournament, $this->isContractLocked);
            $this->step = '5';
        }

        $this->showInviteModal = false;
        $this->inviteMessage = '';
        $this->selectedMembers = [];
        unset($this->invitationHistory);
    }

    // ── Computed: simulation

    #[Computed]
    public function simulation(): SimulationResult
    {
        return app(TournamentSimulator::class)->simulate(new TournamentConfig(
            durationMinutes: max(1, $this->tournament_minutes),
            nbTables: max(1, $this->nbTables),
            logisticsBufferMinutes: max(0, $this->logistics_buffer),
            poolSize: max(2, $this->pool_size),
            nbPools: max(1, $this->nb_poules),
            nbQualifiersPerPool: max(1, $this->nb_qualifies),
            setsToWin: max(1, $this->totalSets),
            matchType: $this->matchType,
        ));
    }

    public function toggleMember(int $id): void
    {
        if (in_array($id, $this->selectedMembers)) {
            $this->selectedMembers = array_values(
                array_filter($this->selectedMembers, fn ($m) => $m !== $id)
            );
        } else {
            $this->selectedMembers[] = $id;
        }
    }

    // ── Registrations

    public function openToggleRegistrationsModal(): void
    {
        if ($this->registrationClosed) {
            $this->showOpenRegistrationsModal = true;
        } else {
            $this->showCloseRegistrationsModal = true;
        }
    }

    public function confirmCloseRegistrations(): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);

        // Kick everyone still on the waitlist — they no longer have a chance.
        $tournament->users()
            ->wherePivotIn('registration_status', ['waiting'])
            ->get()
            ->each(function (User $user) use ($tournament) {
                DB::table('tournament_user')
                    ->where('tournament_id', $tournament->id)
                    ->where('user_id', $user->id)
                    ->update(['registration_status' => 'cancelled', 'waitlist_position' => null]);

                $user->notify(new TournamentWaitlistRemovedNotification($tournament));
            });

        $tournament->update(['status' => TournamentStatusEnum::SETUP]);

        unset($this->currentTournament, $this->waitlist, $this->registrations);
        $this->showCloseRegistrationsModal = false;
        $this->success(__('Registrations closed. Waitlisted players have been notified.'), icon: 'o-lock-closed');
    }

    public function confirmOpenRegistrations(): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);
        $tournament->update(['status' => TournamentStatusEnum::PUBLISHED]);

        unset($this->currentTournament);
        $this->showOpenRegistrationsModal = false;
        $this->success(__('Registrations are now open.'), icon: 'o-lock-open');
    }

    public function updatedMatchType(): void
    {
        $this->markPoolsStaleIfGenerated();
    }

    // ── Hooks

    public function updatedNbPoules(): void
    {
        $this->markPoolsStaleIfGenerated();
        $this->suggestMaxUsers();
    }

    public function updatedNbQualifies(): void
    {
        $this->markPoolsStaleIfGenerated();
    }

    public function updatedPoolSize(): void
    {
        $this->markPoolsStaleIfGenerated();
        $this->suggestMaxUsers();
    }

    public function updatedSelectedRooms(): void
    {
        if (! empty($this->selectedRooms)) {
            $total = Room::whereIn('id', $this->selectedRooms)->sum('total_playable_tables');

            if ($total > 0) {
                $this->nb_tables = (int) $total;
            }
        }
    }

    public function updatedTotalSets(): void
    {
        $this->markPoolsStaleIfGenerated();
    }

    public function updateStructure(array $newStructure): void
    {
        foreach ($newStructure as $entry) {
            $pool = Pool::find($entry['teamId']);

            if ($pool && $pool->tournament_id === $this->tournamentId) {
                $pool->users()->sync($entry['memberIds']);
            }
        }
    }

    public function viewBatchDetails(int $_batchId): void {}

    // ── Computed: waiting list ordered by position

    #[Computed]
    public function waitlist(): Collection
    {
        if (! $this->tournamentId) {
            return collect();
        }

        return Tournament::findOrFail($this->tournamentId)
            ->users()
            ->wherePivot('registration_status', 'waiting')
            ->orderByPivot('waitlist_position')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->full_name,
                'ranking' => $u->ranking ?? 'NC',
                'position' => $u->pivot->waitlist_position,
                'registered_at' => $u->pivot->created_at,
            ]);
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->tournaments()
                ->current(__('Setup Wizard'))
                ->toArray(),
            'objectiveOptions' => TournamentObjectiveEnum::toOptions(),
            'maxUsers' => $this->maxUsers,
        ];
    }

    private function markPoolsStaleIfGenerated(): void
    {
        if ($this->poolsGenerated || $this->matchesGenerated) {
            $this->poolsStale = true;
        }
    }

    private function resetPostAction(): void
    {
        $this->selectedPeople = [];
        $this->bulkDrawer = false;
        unset($this->registrations);
        unset($this->waitlist);
    }

    // ── Private helpers

    private function suggestMaxUsers(): void
    {
        $capacity = $this->nb_poules * $this->pool_size;
        // Only auto-update if unset (0) or if it matches the previous auto-computed value.
        if ($this->maxUsers === 0 || $this->maxUsers === $capacity) {
            $this->maxUsers = $capacity;
        }
    }
};
