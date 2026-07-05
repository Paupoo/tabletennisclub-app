<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubAdmin\Planning\Board;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\AgeCategoryEnum;
use App\Domains\Shared\Enums\TrainingPlanStatusEnum;
use App\Domains\Trainings\Models\TrainingPlan;
use App\Domains\Trainings\Models\TrainingPlanAssignment;
use App\Domains\Trainings\Models\TrainingPlanPack;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Services\ClubAdmin\Planning\ExportTrainingPlanService;
use App\Services\ClubAdmin\Planning\ImportTrainingPlanService;
use App\Services\ClubAdmin\Planning\OptimizeTrainingPlanService;
use App\Services\ClubAdmin\Planning\SeedTrainingPlanService;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    use HasBreadcrumbs, Toast, WithFileUploads;

    /** Whether the current user belongs to the management group (decision #18). */
    public bool $canManage = false;

    /** Confirm-modal visibility for destructive actions. */
    public bool $confirmArchiveModal = false;

    public bool $confirmDeleteModal = false;

    public bool $confirmRemovePackModal = false;

    /** Id of the plan pack being edited; null = creating a new hypothetical pack. */
    public ?int $editingPackId = null;

    /** Uploaded CSV file for import. */
    public ?TemporaryUploadedFile $importFile = null;

    /** Name for the plan being created from the season. */
    public string $newPlanName = '';

    /** Number of packs over capacity in the selected plan (overview tension counter). */
    public int $overCapacityCount = 0;

    /** Pack form: day of week 1-7 (nullable). */
    public ?int $packDayOfWeek = null;

    /** Pack form: level (nullable). */
    public ?string $packLevel = null;

    /** Pack form: capacity (nullable = unlimited). */
    public ?int $packMaxParticipants = null;

    /** Pack form: name (required). */
    public string $packName = '';

    public ?int $pendingPackId = null;

    /** Target ids stored while a confirm modal is open. */
    public ?int $pendingPlanId = null;

    /** Pool-only filter: age category value (AgeCategoryEnum) or '' for all. */
    public string $poolAgeFilter = '';

    /** Pool-only filter: ranking series (A–E or NC) or '' for all. */
    public string $poolSeriesFilter = '';

    /** Currently opened plan; null = plan management view. */
    #[Url]
    public ?int $selectedPlanId = null;

    /** Whether the import modal is open. */
    public bool $showImportModal = false;

    /** Whether the add/edit pack modal is open. */
    public bool $showPackModal = false;

    /**
     * Cache of the active season's subscriptions keyed by user id (avoids N+1
     * when building member cards).
     *
     * @var Collection<int, Subscription>|null
     */
    private ?Collection $seasonSubscriptions = null;

    /**
     * Add a hypothetical pack (source null) to the selected plan, appended last
     * (decision #9: model the offer; decision #10: snapshot only, never the real pack).
     */
    public function addPack(): void
    {
        Gate::authorize('manage-season');

        $plan = $this->currentPlan();

        if ($plan === null) {
            return;
        }

        $this->validatePackForm();

        TrainingPlanPack::create([
            'training_plan_id' => $plan->id,
            'source_training_pack_id' => null,
            'name' => $this->packName,
            'level' => $this->packLevel,
            'day_of_week' => $this->packDayOfWeek,
            'max_participants' => $this->packMaxParticipants,
            'position' => ((int) $plan->packs()->max('position')) + 1,
        ]);

        $this->showPackModal = false;
        $this->resetPackForm();

        $this->success(__('Group added.'));
    }

    /**
     * Archive a plan (decision #18: management only).
     */
    public function archivePlan(?int $planId = null): void
    {
        Gate::authorize('manage-season');

        $planId ??= $this->pendingPlanId;

        if ($planId === null) {
            return;
        }

        $plan = $this->seasonPlan($planId);
        $plan->update(['status' => TrainingPlanStatusEnum::ARCHIVED->value]);

        if ($this->selectedPlanId === $planId) {
            $this->selectedPlanId = null;
        }

        $this->confirmArchiveModal = false;
        $this->pendingPlanId = null;

        $this->success(__('Plan archived.'));
    }

    /** Close the add/edit pack modal and clear its form + any validation errors. */
    public function closePackModal(): void
    {
        $this->showPackModal = false;
        $this->resetPackForm();
        $this->resetValidation();
    }

    public function closePlan(): void
    {
        $this->selectedPlanId = null;
    }

    /** Open the confirm modal before archiving a plan. */
    public function confirmArchivePlan(int $planId): void
    {
        $this->pendingPlanId = $planId;
        $this->confirmArchiveModal = true;
    }

    /** Open the confirm modal before deleting a plan. */
    public function confirmDeletePlan(int $planId): void
    {
        $this->pendingPlanId = $planId;
        $this->confirmDeleteModal = true;
    }

    /** Open the confirm modal before removing a plan pack. */
    public function confirmRemovePack(int $packId): void
    {
        $this->pendingPackId = $packId;
        $this->confirmRemovePackModal = true;
    }

    /**
     * Create a draft plan seeded from the active season (decision #18: management only).
     */
    public function createPlan(SeedTrainingPlanService $seeder): void
    {
        Gate::authorize('manage-season');

        $this->validate([
            'newPlanName' => ['required', 'string', 'max:255'],
        ]);

        $season = $this->activeSeason();

        if ($season === null) {
            throw ValidationException::withMessages([
                'newPlanName' => __('No active season.'),
            ]);
        }

        /** @var User $user */
        $user = auth()->user();

        $plan = $seeder->seedFromSeason($season, $this->newPlanName, $user);

        $this->newPlanName = '';
        $this->selectedPlanId = $plan->id;

        $this->success(__('Plan created.'));
    }

    /**
     * Delete a plan entirely (decision #18: management only).
     */
    public function deletePlan(?int $planId = null): void
    {
        Gate::authorize('manage-season');

        $planId ??= $this->pendingPlanId;

        if ($planId === null) {
            return;
        }

        $plan = $this->seasonPlan($planId);
        $plan->delete();

        if ($this->selectedPlanId === $planId) {
            $this->selectedPlanId = null;
        }

        $this->confirmDeleteModal = false;
        $this->pendingPlanId = null;

        $this->success(__('Plan deleted.'));
    }

    /**
     * Open the modal pre-filled with a pack's snapshot for editing.
     */
    public function editPack(int $packId): void
    {
        Gate::authorize('manage-season');

        $pack = $this->planPack($packId);

        if ($pack === null) {
            return;
        }

        $this->editingPackId = $pack->id;
        $this->packName = $pack->name;
        $this->packLevel = $pack->level;
        $this->packDayOfWeek = $pack->day_of_week;
        $this->packMaxParticipants = $pack->max_participants;
        $this->showPackModal = true;
    }

    /**
     * Export the selected plan to a spreadsheet (decision #19). Visible to the
     * committee (read-only), no `manage-season` gate required.
     */
    public function export(string $format, ExportTrainingPlanService $exporter): ?StreamedResponse
    {
        $plan = $this->currentPlan();

        if ($plan === null) {
            return null;
        }

        $payload = $exporter->export($plan, $format);

        return response()->streamDownload(
            function () use ($payload): void {
                echo $payload['contents'];
            },
            $payload['filename'],
            ['Content-Type' => $payload['mime']],
        );
    }

    /**
     * Import a CSV into the selected plan, moving members between packs/pool
     * (decisions #10/#19). Management only.
     */
    public function import(ImportTrainingPlanService $importer): void
    {
        Gate::authorize('manage-season');

        $plan = $this->currentPlan();

        if ($plan === null) {
            return;
        }

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $contents = (string) file_get_contents($this->importFile->getRealPath());

        $summary = $importer->import($plan, $contents);

        $this->showImportModal = false;
        $this->reset('importFile');

        $this->success(__(':updated updated, :unmatched not found, :skipped skipped.', [
            'updated' => $summary['updated'],
            'unmatched' => count($summary['unmatched']),
            'skipped' => count($summary['skipped']),
        ]));
    }

    public function mount(): void
    {
        $this->canManage = Gate::allows('manage-season');
    }

    /**
     * `wire:sort` handler — move an assignment to another column (pack or pool)
     * and persist its new position. The group id is `pool` or `pack-{id}`.
     */
    public function moveAssignment(int $assignmentId, int $position, string $groupId): void
    {
        Gate::authorize('manage-season');

        $plan = $this->currentPlan();

        if ($plan === null) {
            return;
        }

        /** @var TrainingPlanAssignment|null $assignment */
        $assignment = $plan->assignments()->whereKey($assignmentId)->first();

        if ($assignment === null) {
            return;
        }

        $packId = $this->resolvePackId($plan, $groupId);

        $assignment->update([
            'training_plan_pack_id' => $packId,
            'position' => max(0, $position),
        ]);
    }

    /**
     * Open the modal to add a new hypothetical pack to the selected plan.
     */
    public function openAddPack(): void
    {
        Gate::authorize('manage-season');

        $this->resetPackForm();
        $this->showPackModal = true;
    }

    /**
     * Open the CSV import modal (decision #18: management only).
     */
    public function openImport(): void
    {
        Gate::authorize('manage-season');

        $this->reset('importFile');
        $this->resetValidation();
        $this->showImportModal = true;
    }

    /**
     * Suggest a homogeneous layout: fill the pool into the existing packs
     * (level first, then age, capacity respected). Non-destructive — already
     * placed members never move (decision #18: management only).
     */
    public function optimize(OptimizeTrainingPlanService $optimizer): void
    {
        Gate::authorize('manage-season');

        $plan = $this->currentPlan();

        if ($plan === null) {
            return;
        }

        $summary = $optimizer->optimize($plan);

        $this->success(__(':assigned members placed, :left left in the pool.', [
            'assigned' => $summary['assigned'],
            'left' => $summary['left_in_pool'],
        ]));
    }

    /**
     * Derive the ranking series from a raw ranking string. A leading letter A–E
     * (e.g. "B2", "e6") maps to that uppercase letter; anything else (empty,
     * "NC", "NA") falls back to "NC" for non-classified members.
     */
    public function rankingSeries(?string $ranking): string
    {
        $first = strtoupper(substr(trim((string) $ranking), 0, 1));

        return in_array($first, ['A', 'B', 'C', 'D', 'E'], true) ? $first : 'NC';
    }

    /**
     * Delete a plan pack, returning its members to the pool (assignments kept,
     * `training_plan_pack_id` nulled) so no member disappears.
     */
    public function removePack(?int $packId = null): void
    {
        Gate::authorize('manage-season');

        $packId ??= $this->pendingPackId;

        if ($packId === null) {
            return;
        }

        $pack = $this->planPack($packId);

        if ($pack === null) {
            return;
        }

        $pack->assignments()->update(['training_plan_pack_id' => null]);
        $pack->delete();

        $this->confirmRemovePackModal = false;
        $this->pendingPackId = null;

        $this->success(__('Group removed; members moved to the pool.'));
    }

    public function render(): View
    {
        return $this->view();
    }

    /**
     * Persist the edited pack snapshot. Never touches `source_training_pack_id`
     * nor the real `TrainingPack` (decision #10).
     */
    public function savePack(): void
    {
        Gate::authorize('manage-season');

        if ($this->editingPackId === null) {
            return;
        }

        $pack = $this->planPack($this->editingPackId);

        if ($pack === null) {
            return;
        }

        $this->validatePackForm();

        $pack->update([
            'name' => $this->packName,
            'level' => $this->packLevel,
            'day_of_week' => $this->packDayOfWeek,
            'max_participants' => $this->packMaxParticipants,
        ]);

        $this->showPackModal = false;
        $this->resetPackForm();

        $this->success(__('Group updated.'));
    }

    public function selectPlan(int $planId): void
    {
        $this->selectedPlanId = $planId;
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $season = $this->activeSeason();
        $plan = $this->currentPlan();

        $columns = $plan !== null ? $this->buildColumns($plan) : [];
        $this->overCapacityCount = collect($columns)->where('over_capacity', true)->count();

        // Explicit child → teen → adult order (Pint sorts enum cases alphabetically).
        $poolAgeOptions = collect([
            AgeCategoryEnum::CHILD,
            AgeCategoryEnum::TEEN,
            AgeCategoryEnum::ADULT,
        ])->map(fn (AgeCategoryEnum $c): array => ['id' => $c->value, 'name' => $c->getLabel()]);

        $poolSeriesOptions = collect(['A', 'B', 'C', 'D', 'E'])
            ->map(fn (string $s): array => ['id' => $s, 'name' => $s])
            ->push(['id' => 'NC', 'name' => __('Unranked')]);

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'season' => $season,
            'plan' => $plan,
            'plans' => $season !== null ? $this->plansForSeason($season) : collect(),
            'columns' => $columns,
            'canManage' => $this->canManage,
            'poolAgeOptions' => $poolAgeOptions,
            'poolSeriesOptions' => $poolSeriesOptions,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Planning board'));
    }

    /**
     * Build the board columns (real/hypothetical packs + the pool) with their
     * member cards and capacity tension flags.
     *
     * @return array<int, array{
     *   id: string, pack_id: int|null, name: string, is_pool: bool,
     *   level: ?string, day_of_week: ?int, max_participants: ?int,
     *   current_count: int, total_count?: int, over_capacity: bool,
     *   cards: array<int, array<string, mixed>>
     * }>
     */
    protected function buildColumns(TrainingPlan $plan): array
    {
        // Eager-load to avoid N+1 on cards and counts.
        $plan->loadMissing(['packs', 'assignments.user']);

        /** @var Collection<int|string, Collection<int, TrainingPlanAssignment>> $byPack */
        $byPack = $plan->assignments
            // Archived members keep their assignment row (soft delete, no cascade)
            // but drop out of the relation, so skip them here rather than crash.
            ->filter(fn (TrainingPlanAssignment $a): bool => $a->user !== null)
            ->sortBy('position')
            ->groupBy(fn (TrainingPlanAssignment $a): string => $a->training_plan_pack_id === null
                ? 'pool'
                : (string) $a->training_plan_pack_id);

        $columns = [];

        foreach ($plan->packs->sortBy('position') as $pack) {
            /** @var TrainingPlanPack $pack */
            $assignments = $byPack->get((string) $pack->id, collect());
            $count = $assignments->count();
            $max = $pack->max_participants;

            $columns[] = [
                'id' => 'pack-' . $pack->id,
                'pack_id' => $pack->id,
                'name' => $pack->name,
                'is_pool' => false,
                'level' => $pack->level,
                'day_of_week' => $pack->day_of_week,
                'max_participants' => $max,
                'current_count' => $count,
                'over_capacity' => $max !== null && $count > $max,
                'cards' => $assignments->map(fn (TrainingPlanAssignment $a): array => $this->toCard($a))->values()->all(),
            ];
        }

        // Pool column always last. Filters (age category + ranking series) apply
        // to the pool only (decision: fast manual composition), never to groups.
        $poolAssignments = $byPack->get('pool', collect());
        $poolCards = $poolAssignments
            ->map(fn (TrainingPlanAssignment $a): array => $this->toCard($a))
            ->filter(fn (array $card): bool => $this->cardMatchesPoolFilters($card))
            ->values();

        $columns[] = [
            'id' => 'pool',
            'pack_id' => null,
            'name' => __('Pool'),
            'is_pool' => true,
            'level' => null,
            'day_of_week' => null,
            'max_participants' => null,
            // `current_count` reflects the displayed subset; `total_count` keeps the
            // full pool size so the header can show "shown / total" when filtering.
            'current_count' => $poolCards->count(),
            'total_count' => $poolAssignments->count(),
            'over_capacity' => false,
            'cards' => $poolCards->all(),
        ];

        return $columns;
    }

    /**
     * Member card — reuses the season roster's row shape (decision #11):
     * name, ranking, derived age category, competitive/driving/captain/volunteer badges.
     *
     * @return array{
     *   id: int, user_id: int, name: string, ranking: ?string,
     *   age_category: ?string, age_label: ?string, is_competitive: bool,
     *   can_drive: bool, seats_available: ?int, wants_to_be_captain: bool,
     *   volunteer_help: bool
     * }
     */
    protected function toCard(TrainingPlanAssignment $assignment): array
    {
        $user = $assignment->user;
        $sub = $this->seasonSubscriptionFor($user->id);

        $category = $user->birthdate !== null
            ? AgeCategoryEnum::fromBirthdate($user->birthdate)
            : null;

        return [
            'id' => $assignment->id,
            'user_id' => $user->id,
            'name' => $user->first_name . ' ' . $user->last_name,
            'ranking' => $user->ranking,
            'age_category' => $category?->value,
            'age_label' => $category?->getLabel(),
            'is_competitive' => (bool) $sub?->is_competitive,
            'can_drive' => (bool) $sub?->can_drive,
            'seats_available' => $sub?->seats_available,
            'wants_to_be_captain' => (bool) $sub?->wants_to_be_captain,
            'volunteer_help' => (bool) $sub?->volunteer_help,
        ];
    }

    private function activeSeason(): ?Season
    {
        return Season::active()->first();
    }

    /**
     * Whether a pool card passes the active age-category and ranking-series filters.
     * An empty filter matches everything.
     *
     * @param  array<string, mixed>  $card
     */
    private function cardMatchesPoolFilters(array $card): bool
    {
        if ($this->poolAgeFilter !== '' && $card['age_category'] !== $this->poolAgeFilter) {
            return false;
        }

        if ($this->poolSeriesFilter !== '' && $this->rankingSeries((string) $card['ranking']) !== $this->poolSeriesFilter) {
            return false;
        }

        return true;
    }

    private function currentPlan(): ?TrainingPlan
    {
        if ($this->selectedPlanId === null) {
            return null;
        }

        $season = $this->activeSeason();

        if ($season === null) {
            return null;
        }

        return TrainingPlan::query()
            ->where('season_id', $season->id)
            ->whereKey($this->selectedPlanId)
            ->first();
    }

    /**
     * Resolve a plan pack that belongs to the selected plan (guards cross-plan access).
     */
    private function planPack(int $packId): ?TrainingPlanPack
    {
        $plan = $this->currentPlan();

        if ($plan === null) {
            return null;
        }

        return $plan->packs()->whereKey($packId)->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, TrainingPlan>
     */
    private function plansForSeason(Season $season)
    {
        return TrainingPlan::query()
            ->where('season_id', $season->id)
            ->withCount('packs', 'assignments')
            ->orderByDesc('created_at')
            ->get();
    }

    private function resetPackForm(): void
    {
        $this->editingPackId = null;
        $this->packName = '';
        $this->packLevel = null;
        $this->packDayOfWeek = null;
        $this->packMaxParticipants = null;
    }

    /**
     * Resolve a `wire:sort` group id (`pool` or `pack-{id}`) to a pack id within
     * the plan, or null for the pool.
     */
    private function resolvePackId(TrainingPlan $plan, string $groupId): ?int
    {
        if ($groupId === 'pool') {
            return null;
        }

        $packId = (int) str_replace('pack-', '', $groupId);

        return $plan->packs()->whereKey($packId)->exists() ? $packId : null;
    }

    /**
     * Resolve a plan that belongs to the active season (guards cross-season access).
     */
    private function seasonPlan(int $planId): TrainingPlan
    {
        $season = $this->activeSeason();

        return TrainingPlan::query()
            ->where('season_id', $season?->id)
            ->findOrFail($planId);
    }

    private function seasonSubscriptionFor(int $userId): ?Subscription
    {
        if ($this->seasonSubscriptions === null) {
            $season = $this->activeSeason();

            $this->seasonSubscriptions = $season === null
                ? collect()
                : Subscription::query()
                    ->where('season_id', $season->id)
                    ->get()
                    ->keyBy('user_id');
        }

        return $this->seasonSubscriptions->get($userId);
    }

    private function validatePackForm(): void
    {
        $this->validate([
            'packName' => ['required', 'string', 'max:255'],
            'packLevel' => ['nullable', 'string', 'max:50'],
            'packDayOfWeek' => ['nullable', 'integer', 'between:1,7'],
            'packMaxParticipants' => ['nullable', 'integer', 'min:0'],
        ]);
    }
};
