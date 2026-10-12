<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\DiscontinueTrainingPackAction;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Trainings\Models\TrainingLevel;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingRosterExport;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    use HasBreadcrumbs;
    use HasFilterDrawer;
    use Toast;

    public bool $discontinuePackModal = false;

    public string $discontinueReason = '';

    public ?int $discontinuingPackId = null;

    // ── Actions sur le roster ─────────────────────────────────────────────────
    /** Show packs withdrawn from the offer, so they can be found and put back. */
    // ── Gestion des niveaux ───────────────────────────────────────────────────
    public bool $levelDrawer = false;

    /** @var array{id?: int|null, label: string, color: string} */
    public array $levelForm = ['id' => null, 'label' => '', 'color' => 'primary'];

    public bool $showInactive = false;

    // ── View filter ───────────────────────────────────────────────────────────
    public int $viewSeasonId = 0;

    public ?int $withdrawingPackId = null;

    public bool $withdrawPackModal = false;

    /**
     * The season is navigation, not a filter (DS-A): clearing the filters must
     * not send the reader back to another season than the one they are looking at.
     */
    public function clearFilters(): void
    {
        $this->showInactive = false;
    }

    /**
     * Stop the pack for good: cancel what is left, pay people back, tell them.
     */
    public function confirmDiscontinuePack(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        if (! $this->discontinuingPackId) {
            return;
        }

        $pack = TrainingPack::findOrFail($this->discontinuingPackId);

        $result = (new DiscontinueTrainingPackAction)($pack, $this->discontinueReason ?: null);

        unset($this->packs);
        $this->discontinuePackModal = false;
        $this->discontinuingPackId = null;
        $this->discontinueReason = '';

        $this->warning(
            title: __('Pack stopped.'),
            description: __(':sessions session(s) cancelled, :members member(s) notified, :amount € to refund.', [
                'sessions' => $result['sessions'],
                'members' => $result['members'],
                'amount' => number_format($result['refunded'], 2),
            ]),
            icon: 'o-x-circle',
        );
    }

    public function confirmWithdrawPack(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        if ($this->withdrawingPackId) {
            $this->withdrawPack($this->withdrawingPackId);
        }
    }

    /**
     * Supprime un niveau que rien ne référence.
     *
     * Un niveau porté par un pack ou une séance ne se supprime jamais : le
     * désactiver le retire des listes sans réécrire l'histoire des saisons
     * passées.
     */
    public function deleteLevel(int $levelId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $level = TrainingLevel::findOrFail($levelId);

        if ($level->isInUse()) {
            $this->error(__('This level is still used by a pack or a session. Retire it instead of deleting it.'));

            return;
        }

        $level->delete();

        unset($this->levelOptions, $this->levels);

        $this->success(__('Level deleted.'), icon: 'o-trash');
    }

    /**
     * How much of the club this would touch, shown before the committee confirms.
     *
     * Deliberately no euro figure: the refund owed to each member depends on the
     * multi-pack discount they lose, so any total shown here would be a guess
     * that the actual refunds then contradict. The toast reports the real total
     * once the refunds have been computed member by member.
     *
     * @return array{members: int, waiting: int, sessions: int}
     */
    #[Computed]
    public function discontinueImpact(): array
    {
        $pack = $this->discontinuingPackId ? TrainingPack::find($this->discontinuingPackId) : null;

        if (! $pack) {
            return ['members' => 0, 'waiting' => 0, 'sessions' => 0];
        }

        return [
            'members' => $pack->committedCount(),
            'waiting' => $pack->waitlistCount(),
            'sessions' => $pack->trainings()
                ->where('status', 'scheduled')
                ->where('start', '>=', Carbon::now())
                ->count(),
        ];
    }

    public function editLevel(int $levelId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $level = TrainingLevel::findOrFail($levelId);

        $this->levelForm = [
            'id' => $level->id,
            'label' => $level->label,
            'color' => $level->color,
        ];
    }

    /**
     * Everybody tied to a pack of the season shown, on one sheet. The file
     * carries contact details, so it asks for what the members list asks
     * for, not only for the trainings — and saying who took it is the price
     * of letting it leave the application.
     */
    public function exportRoster(string $format, TrainingRosterExport $exporter): ?StreamedResponse
    {
        abort_unless($this->mayExportRoster, 403);

        $season = $this->viewSeason;

        if (! $season instanceof Season) {
            return null;
        }

        $payload = $exporter->export($season, $format);

        activity()
            ->causedBy(Auth::user())
            ->event('training_roster_exported')
            ->withProperties([
                'season_id' => $season->id,
                'season' => $season->name,
                'format' => $format,
            ])
            ->log('training_roster_exported');

        return response()->streamDownload(
            function () use ($payload): void {
                echo $payload['contents'];
            },
            $payload['filename'],
            ['Content-Type' => $payload['mime']],
        );
    }

    /** @return array<int, array{key: string, label: string}> */
    #[Computed]
    public function filterChips(): array
    {
        return $this->getFilterChips();
    }

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->showInactive) {
            $chips[] = ['key' => 'showInactive', 'label' => __('Withdrawn packs shown')];
        }

        return $chips;
    }

    #[Computed]
    public function levelOptions(): array
    {
        return TrainingLevel::active()
            ->get()
            ->map(fn (TrainingLevel $level): array => ['id' => $level->id, 'name' => $level->label])
            ->toArray();
    }

    /** @return Collection<int, TrainingLevel> */
    #[Computed]
    public function levels(): Collection
    {
        return TrainingLevel::ordered()->get();
    }

    #[Computed]
    public function mayExportRoster(): bool
    {
        return Gate::allows(Permission::TrainingsView->value) && Gate::allows(Permission::UsersView->value);
    }

    /**
     * Whether the visitor builds the offer, or only reads it: the committee reads
     * every pack, its roster and its attendance at the baseline.
     */
    #[Computed]
    public function mayManage(): bool
    {
        return Gate::allows(Permission::TrainingsManage->value);
    }

    public function mount(): void
    {
        $this->viewSeasonId = Season::where('is_active', true)->value('id') ?? 0;

        // Les liens d'avant la fiche en page à part (compteur de tâches,
        // anciens mails) visaient `?pack=` : ils mènent à la fiche.
        $packId = (int) request()->query('pack', 0);

        if ($packId > 0 && TrainingPack::whereKey($packId)->exists()) {
            $this->redirectRoute('admin.trainings.show', ['pack' => $packId]);
        }
    }

    public function newLevel(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->levelForm = ['id' => null, 'label' => '', 'color' => 'primary'];
    }

    public function openDiscontinuePack(int $packId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->discontinuingPackId = $packId;
        $this->discontinueReason = '';
        $this->discontinuePackModal = true;
    }

    public function openWithdrawPack(int $packId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->withdrawingPackId = $packId;
        $this->withdrawPackModal = true;
    }

    /** @return Collection<int, TrainingPack> */
    #[Computed]
    public function packs(): Collection
    {
        if (! $this->viewSeason) {
            return new Collection;
        }

        // Le tri porte sur la position du niveau, pas sur son libellé : la
        // délégation décide de l'ordre (du plus jeune au plus fort), et le
        // renommer ne doit pas réordonner l'écran. Jointure à gauche pour que
        // les packs sans niveau restent listés.
        return TrainingPack::with(['room', 'trainer', 'eventPost', 'level'])
            ->select('training_packs.*')
            ->leftJoin('training_levels', 'training_levels.id', '=', 'training_packs.training_level_id')
            ->where('training_packs.season_id', $this->viewSeason->id)
            ->when(! $this->showInactive, fn (Builder $q) => $q->where('training_packs.is_active', true))
            ->orderBy('training_packs.is_active', 'desc')
            ->orderBy('training_levels.position')
            ->orderBy('training_packs.name')
            ->get();
    }

    public function refreshPacks(): void
    {
        unset($this->packs);
    }

    public function removeFilter(string $key): void
    {
        $this->reset([$key]);
    }

    public function restorePack(int $packId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        TrainingPack::findOrFail($packId)->update(['is_active' => true]);
        unset($this->packs);
        $this->success(__('Pack back in the offer.'));
    }

    /**
     * Crée ou met à jour un niveau.
     *
     * Un niveau neuf se place en fin de liste : l'ordre est une décision de la
     * délégation, pas un effet de bord de la date de création.
     */
    public function saveLevel(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->validate([
            'levelForm.label' => 'required|string|min:2|max:60',
            'levelForm.color' => 'required|string|max:30',
        ]);

        $attributes = [
            'label' => $this->levelForm['label'],
            'color' => $this->levelForm['color'],
        ];

        if (! empty($this->levelForm['id'])) {
            TrainingLevel::findOrFail($this->levelForm['id'])->update($attributes);
        } else {
            TrainingLevel::create($attributes + [
                'position' => (int) TrainingLevel::max('position') + 1,
                'is_active' => true,
            ]);
        }

        $this->newLevel();

        unset($this->levelOptions, $this->levels);

        $this->success(__('Level saved.'), icon: 'o-check-circle');
    }

    // ── Options ───────────────────────────────────────────────────────────────

    #[Computed]
    public function seasonOptions(): array
    {
        return Season::orderBy('start_at')
            ->get()
            ->map(fn (Season $s): array => [
                'id' => $s->id,
                'name' => $s->name . ($s->is_active ? ' (' . __('Active') . ')' : ''),
            ])
            ->toArray();
    }

    /**
     * Ouvre ou ferme le libre-service sur un pack.
     *
     * Indépendant de « Retirer de l'offre » : un pack peut rester affiché sur
     * le site, complet, avec ses inscriptions closes. Les offres de liste
     * d'attente déjà envoyées ne sont pas rappelées — fermer empêche d'entrer,
     * pas d'avancer.
     */
    public function toggleEnrollments(int $packId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $pack = TrainingPack::findOrFail($packId);
        $pack->update(['enrollments_open' => ! $pack->enrollments_open]);

        unset($this->packs);

        $pack->enrollments_open
            ? $this->success(__('Enrolments reopened for :pack.', ['pack' => $pack->name]), icon: 'o-lock-open')
            : $this->warning(__('Enrolments closed for :pack. The committee can still add members.', ['pack' => $pack->name]), icon: 'o-lock-closed');
    }

    /** Retire un niveau des listes sans toucher aux packs qui le portent. */
    public function toggleLevel(int $levelId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $level = TrainingLevel::findOrFail($levelId);
        $level->update(['is_active' => ! $level->is_active]);

        unset($this->levelOptions, $this->levels);
    }

    #[Computed]
    public function viewSeason(): ?Season
    {
        return $this->viewSeasonId ? Season::find($this->viewSeasonId) : null;
    }

    public function with(): array
    {
        return [
            'filterChips' => $this->filterChips,
            'discontinueImpact' => $this->discontinueImpact,
            'viewSeason' => $this->viewSeason,
            'packs' => $this->packs,
            'seasonOptions' => $this->seasonOptions,
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    /**
     * Take the pack off the offer without touching what is already running:
     * sessions go ahead, enrolled members keep their place and hear nothing.
     */
    public function withdrawPack(int $packId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        TrainingPack::findOrFail($packId)->update(['is_active' => false]);
        unset($this->packs);
        $this->withdrawPackModal = false;
        $this->withdrawingPackId = null;
        $this->warning(__('Pack withdrawn from the offer. Its sessions still run.'));
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Trainings'));
    }
};
