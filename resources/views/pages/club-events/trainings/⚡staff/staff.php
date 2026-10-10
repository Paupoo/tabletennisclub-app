<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Services\TrainingStaffReport;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Qui a encadré quoi : les séances données comme coach, les présences
 * confirmées comme sparring, et ce qui reste à pointer. Pas de montants :
 * c'est la base sur laquelle les paiements viendront s'appuyer.
 */
new class extends Component
{
    use HasBreadcrumbs;

    public ?int $detailUserId = null;

    /** `season`, or a month of the season as `YYYY-MM`. */
    public string $period = 'season';

    #[Computed]
    public function activeSeason(): ?Season
    {
        return Season::where('is_active', true)->first();
    }

    /**
     * Les séances d'une personne, de la plus ancienne à la plus récente.
     *
     * @return list<array{date: string, pack: string, role: string, status: string}>
     */
    #[Computed]
    public function detail(): array
    {
        $user = $this->detailUserId ? User::find($this->detailUserId) : null;
        [$from, $to] = $this->range();

        return $user && $from ? app(TrainingStaffReport::class)->detail($user, $from, $to) : [];
    }

    #[Computed]
    public function detailUser(): ?User
    {
        return $this->detailUserId ? User::find($this->detailUserId) : null;
    }

    /**
     * Saved for who exported it, as for the roster: the file says who is paid for what.
     */
    public function export(string $format): ?StreamedResponse
    {
        Gate::authorize('view-training-staff');

        [$from, $to] = $this->range();

        if (! $from) {
            return null;
        }

        $payload = app(TrainingStaffReport::class)->export($from, $to, $format);

        activity()
            ->causedBy(Auth::user())
            ->event('training_staff_exported')
            ->withProperties(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'format' => $format])
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
     * La saison entière, puis chacun de ses mois jusqu'à aujourd'hui, le plus
     * récent d'abord.
     *
     * @return list<array{id: string, name: string}>
     */
    #[Computed]
    public function periodOptions(): array
    {
        $season = $this->activeSeason;

        if (! $season) {
            return [];
        }

        $options = [['id' => 'season', 'name' => __('Whole season')]];
        $last = CarbonImmutable::parse(min($season->end_at, now()))->startOfMonth();

        for ($month = $last; $month->gte(CarbonImmutable::parse($season->start_at)->startOfMonth()); $month = $month->subMonth()) {
            $options[] = ['id' => $month->format('Y-m'), 'name' => ucfirst($month->translatedFormat('F Y'))];
        }

        return $options;
    }

    /**
     * @return list<array{user_id: int, name: string, coached: int, sparred: int, pending: int}>
     */
    #[Computed]
    public function rows(): array
    {
        [$from, $to] = $this->range();

        return $from ? app(TrainingStaffReport::class)->rows($from, $to) : [];
    }

    public function showDetail(int $userId): void
    {
        $this->detailUserId = $userId;

        unset($this->detail, $this->detailUser);
    }

    public function updatedPeriod(): void
    {
        unset($this->rows, $this->detail);
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Coaching staff'));
    }

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private function range(): array
    {
        $season = $this->activeSeason;

        if (! $season) {
            return [null, null];
        }

        if ($this->period !== 'season' && preg_match('/^\d{4}-\d{2}$/', $this->period) === 1) {
            $month = CarbonImmutable::createFromFormat('Y-m-d', $this->period . '-01')->startOfDay();

            return [$month, $month->endOfMonth()];
        }

        return [CarbonImmutable::parse($season->start_at), CarbonImmutable::parse($season->end_at)];
    }
};
