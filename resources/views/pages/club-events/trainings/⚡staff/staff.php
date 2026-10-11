<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingStaffReport;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Qui a encadré quoi : les séances données comme coach, les présences
 * confirmées comme sparring, ce qui reste à pointer et ce qui est prévu. Pas
 * de montants : c'est la base sur laquelle les paiements viendront s'appuyer.
 *
 * La saison est une navigation (DS-A) ; le mois et le pack restreignent le
 * décompte, ce sont des filtres.
 */
new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer;

    public bool $detailDrawer = false;

    public ?int $detailUserId = null;

    /** `YYYY-MM`, or empty for the whole season. */
    public string $monthFilter = '';

    public int $packFilter = 0;

    public ?int $viewSeasonId = null;

    public function clearFilters(): void
    {
        $this->monthFilter = '';
        $this->packFilter = 0;

        $this->forgetCounts();
    }

    /**
     * Les séances de la personne ouverte, sur la période et le pack affichés :
     * le tiroir détaille la ligne cliquée, il en reprend donc le périmètre.
     *
     * @return list<array{date: string, pack: string, role: string, status: string}>
     */
    #[Computed]
    public function detail(): array
    {
        $user = $this->detailUser;
        [$from, $to] = $this->range();

        return $user && $from ? app(TrainingStaffReport::class)->detail($user, $from, $to, $this->packFilter ?: null) : [];
    }

    #[Computed]
    public function detailUser(): ?User
    {
        return $this->detailUserId ? User::find($this->detailUserId) : null;
    }

    /**
     * What is on screen, and nothing else: the file name and the audit say
     * which season, month and pack it covers. Saved for who took it, as for the
     * roster — the file says who is paid for what.
     */
    public function export(string $format): ?StreamedResponse
    {
        Gate::authorize('view-training-staff');

        [$from, $to] = $this->range();
        $season = $this->viewSeason;

        if (! $from || ! $season) {
            return null;
        }

        $payload = app(TrainingStaffReport::class)->export($from, $to, $format, $this->packFilter ?: null, $this->scopeLabel());

        activity()
            ->causedBy(Auth::user())
            ->event('training_staff_exported')
            ->withProperties([
                'season' => $season->name,
                'month' => $this->monthFilter ?: null,
                'pack_id' => $this->packFilter ?: null,
                'format' => $format,
            ])
            ->log('training_staff_exported');

        return response()->streamDownload(
            function () use ($payload): void {
                echo $payload['contents'];
            },
            $payload['filename'],
            ['Content-Type' => $payload['mime']],
        );
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->monthFilter !== '') {
            $chips[] = ['key' => 'monthFilter', 'label' => $this->monthLabel($this->monthFilter)];
        }

        if ($this->packFilter !== 0) {
            $chips[] = ['key' => 'packFilter', 'label' => TrainingPack::find($this->packFilter)?->name ?? (string) $this->packFilter];
        }

        return $chips;
    }

    public function mount(): void
    {
        $this->viewSeasonId = Season::where('is_active', true)->value('id')
            ?? Season::orderByDesc('start_at')->value('id');
    }

    /**
     * Les mois de la saison affichée, du plus récent au plus ancien.
     *
     * @return list<array{id: string, name: string}>
     */
    #[Computed]
    public function monthOptions(): array
    {
        $season = $this->viewSeason;

        if (! $season) {
            return [];
        }

        $options = [];
        $first = CarbonImmutable::parse($season->start_at)->startOfMonth();

        for ($month = CarbonImmutable::parse($season->end_at)->startOfMonth(); $month->gte($first); $month = $month->subMonth()) {
            $options[] = ['id' => $month->format('Y-m'), 'name' => $this->monthLabel($month->format('Y-m'))];
        }

        return $options;
    }

    /**
     * Les packs de la saison affichée, stages compris.
     *
     * @return list<array{id: int, name: string}>
     */
    #[Computed]
    public function packOptions(): array
    {
        return TrainingPack::query()
            ->where('season_id', $this->viewSeasonId)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (TrainingPack $pack): array => ['id' => $pack->id, 'name' => $pack->name])
            ->all();
    }

    public function removeFilter(string $key): void
    {
        match ($key) {
            'monthFilter' => $this->monthFilter = '',
            'packFilter' => $this->packFilter = 0,
            default => null,
        };

        $this->forgetCounts();
    }

    /**
     * @return list<array{user_id: int, name: string, coached: int, sparred: int, pending: int, upcoming: int}>
     */
    #[Computed]
    public function rows(): array
    {
        [$from, $to] = $this->range();

        return $from ? app(TrainingStaffReport::class)->rows($from, $to, $this->packFilter ?: null) : [];
    }

    /**
     * Ce que couvrent les chiffres affichés : saison, puis mois et pack s'ils
     * sont choisis.
     */
    public function scopeLabel(): string
    {
        return collect([
            $this->viewSeason?->name,
            $this->monthFilter !== '' ? $this->monthLabel($this->monthFilter) : null,
            $this->packFilter !== 0 ? TrainingPack::find($this->packFilter)?->name : null,
        ])->filter()->implode(' · ');
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    #[Computed]
    public function seasonOptions(): array
    {
        return Season::orderBy('start_at')
            ->get()
            ->map(fn (Season $season): array => [
                'id' => $season->id,
                'name' => $season->name . ($season->is_active ? ' (' . __('Active') . ')' : ''),
            ])
            ->all();
    }

    public function showDetail(int $userId): void
    {
        $this->detailUserId = $userId;
        $this->detailDrawer = true;

        unset($this->detail, $this->detailUser);
    }

    public function updatedMonthFilter(): void
    {
        $this->forgetCounts();
    }

    public function updatedPackFilter(): void
    {
        $this->forgetCounts();
    }

    /**
     * A pack and a month belong to one season: kept across a switch, they
     * would empty the page.
     */
    public function updatedViewSeasonId(): void
    {
        $this->monthFilter = '';
        $this->packFilter = 0;

        unset($this->viewSeason, $this->monthOptions, $this->packOptions);
        $this->forgetCounts();
    }

    #[Computed]
    public function viewSeason(): ?Season
    {
        return $this->viewSeasonId ? Season::find($this->viewSeasonId) : null;
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'filterChips' => $this->getFilterChips(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Coaching staff'));
    }

    private function forgetCounts(): void
    {
        unset($this->rows, $this->detail);
    }

    private function monthLabel(string $month): string
    {
        return ucfirst(CarbonImmutable::createFromFormat('Y-m-d', $month . '-01')->translatedFormat('F Y'));
    }

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private function range(): array
    {
        $season = $this->viewSeason;

        if (! $season) {
            return [null, null];
        }

        if ($this->monthFilter !== '' && preg_match('/^\d{4}-\d{2}$/', $this->monthFilter) === 1) {
            $month = CarbonImmutable::createFromFormat('Y-m-d', $this->monthFilter . '-01')->startOfDay();

            return [$month, $month->endOfMonth()];
        }

        return [CarbonImmutable::parse($season->start_at), CarbonImmutable::parse($season->end_at)];
    }
};
