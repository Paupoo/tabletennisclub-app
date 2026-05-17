<?php

declare(strict_types=1);

use App\Models\ClubEvents\Interclub\Season;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public ?int $activateId = null;

    // ── Activate modal ────────────────────────────────────────────────────────
    public bool $activateModal = false;

    public string $activateName = '';

    public string $createEndAt = '';

    // ── Create modal ──────────────────────────────────────────────────────────
    public bool $createModal = false;

    public string $createName = '';

    public string $createStartAt = '';

    public string $editEndAt = '';

    public ?int $editId = null;

    // ── Edit modal ────────────────────────────────────────────────────────────
    public bool $editModal = false;

    public string $editName = '';

    public string $editStartAt = '';

    // ── Provision modal ───────────────────────────────────────────────────────
    public bool $provisionModal = false;

    // ── Computed ──────────────────────────────────────────────────────────────

    public bool $showAllPastSeasons = false;

    public function confirmActivate(): void
    {
        $season = Season::findOrFail($this->activateId);
        $this->authorize('update', $season);

        $season->activate();

        unset($this->seasons);
        $this->activateModal = false;
        $this->success(
            title: __('Season :name is now active.', ['name' => $season->name]),
            icon: 'o-check-circle',
        );
    }

    public function confirmProvision(): void
    {
        $this->authorize('create', Season::class);

        Artisan::call('season:provision');
        $output = trim(Artisan::output());

        unset($this->seasons);
        $this->provisionModal = false;
        $this->success($output ?: __('Upcoming seasons are up to date.'), icon: 'o-calendar');
    }

    public function createSeason(): void
    {
        $this->authorize('create', Season::class);

        $this->validate([
            'createName' => 'required|string|max:50|unique:seasons,name',
            'createStartAt' => 'required|date',
            'createEndAt' => 'required|date|after:createStartAt',
        ]);

        try {
            Season::create([
                'name' => $this->createName,
                'start_at' => Carbon::parse($this->createStartAt)->startOfDay(),
                'end_at' => Carbon::parse($this->createEndAt)->endOfDay(),
                'is_active' => false,
                'registrations_open' => false,
            ]);
        } catch (DomainException $e) {
            $this->addError('createStartAt', __('These dates overlap with an existing season.'));

            return;
        }

        unset($this->seasons);
        $this->createModal = false;
        $this->success(__('Season created.'), icon: 'o-calendar');
    }

    // ── Activate ──────────────────────────────────────────────────────────────

    public function openActivate(int $seasonId): void
    {
        $season = Season::findOrFail($seasonId);
        $this->authorize('update', $season);

        $this->activateId = $season->id;
        $this->activateName = $season->name;
        $this->activateModal = true;
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->authorize('create', Season::class);

        // Default to the year after the latest season
        $latest = Season::orderByDesc('start_at')->first();
        $nextStartYear = $latest
            ? (int) $latest->start_at->format('Y') + 1
            : (int) now()->format('Y');

        $this->createName = $nextStartYear . '-' . ($nextStartYear + 1);
        $this->createStartAt = Carbon::create($nextStartYear, 9, 1)->toDateString();
        $this->createEndAt = Carbon::create($nextStartYear + 1, 6, 30)->toDateString();
        $this->createModal = true;
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function openEdit(int $seasonId): void
    {
        $season = Season::findOrFail($seasonId);
        $this->authorize('update', $season);

        $this->editId = $season->id;
        $this->editName = $season->name;
        $this->editStartAt = $season->start_at->toDateString();
        $this->editEndAt = $season->end_at->toDateString();
        $this->editModal = true;
    }

    // ── Provision ─────────────────────────────────────────────────────────────

    public function openProvision(): void
    {
        $this->authorize('create', Season::class);
        $this->provisionModal = true;
    }

    /** @return Collection<int, Season> */
    #[Computed]
    public function seasons(): Collection
    {
        return Season::orderBy('start_at')->get();
    }

    public function updateSeason(): void
    {
        $season = Season::findOrFail($this->editId);
        $this->authorize('update', $season);

        $this->validate([
            'editName' => 'required|string|max:50|unique:seasons,name,' . $this->editId,
            'editStartAt' => 'required|date',
            'editEndAt' => 'required|date|after:editStartAt',
        ]);

        try {
            $season->update([
                'name' => $this->editName,
                'start_at' => Carbon::parse($this->editStartAt)->startOfDay(),
                'end_at' => Carbon::parse($this->editEndAt)->endOfDay(),
            ]);
        } catch (DomainException $e) {
            $this->addError('editStartAt', __('These dates overlap with an existing season.'));

            return;
        }

        unset($this->seasons);
        $this->editModal = false;
        $this->success(__('Season updated.'), icon: 'o-check-circle');
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $all = $this->seasons;
        $pastCount = $all->filter(fn (Season $s) => $s->isPast())->count();
        $hiddenPastCount = $this->showAllPastSeasons ? 0 : max(0, $pastCount - 1);

        return [
            'seasons' => $hiddenPastCount > 0 ? $all->slice($hiddenPastCount)->values() : $all,
            'hiddenPastCount' => $hiddenPastCount,
            'pastCount' => $pastCount,
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Seasons'))
                ->toArray(),
        ];
    }
};
