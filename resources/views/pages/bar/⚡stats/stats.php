<?php

declare(strict_types=1);

namespace Resources\views\Pages\Bar\Stats;

use App\Domains\Bar\Services\BarSalesReport;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
|--------------------------------------------------------------------------
| Bar — ce qui se vend
|--------------------------------------------------------------------------
|
| L'écran du comité pour décider des achats : quels produits sortent, lesquels
| dorment et risquent de périmer. Il se lit, il n'écrit rien.
|
| Il vit sous `/bar` sans en prendre le verrou `bar.access` : le comité lit tout
| mais n'a rien à faire au comptoir. Voir routes/bar.php.
|
*/
new class extends Component
{
    use HasBreadcrumbs;

    /** Premier jour couvert, inclus (Y-m-d). */
    #[Url(as: 'from')]
    public string $firstDay = '';

    /** Dernier jour couvert, inclus (Y-m-d). */
    #[Url(as: 'to')]
    public string $lastDay = '';

    /**
     * La période choisie : un preset, ou `custom` quand on a saisi les dates.
     *
     * Par défaut la saison : c'est le rythme d'un club, et celui des achats.
     */
    #[Url]
    public string $period = 'season';

    public function mount(): void
    {
        if ($this->period !== 'custom' || $this->firstDay === '' || $this->lastDay === '') {
            $this->applyPeriod();
        }
    }

    public function render(): View
    {
        return $this->view();
    }

    /**
     * Saisir une date, c'est quitter le preset.
     */
    public function updatedFirstDay(): void
    {
        $this->period = 'custom';
    }

    public function updatedLastDay(): void
    {
        $this->period = 'custom';
    }

    public function updatedPeriod(): void
    {
        $this->applyPeriod();
    }

    public function with(): array
    {
        $first = CarbonImmutable::parse($this->firstDay);
        $last = CarbonImmutable::parse($this->lastDay);

        // Des dates inversées se lisent dans l'autre sens plutôt que de vider l'écran.
        if ($last->lessThan($first)) {
            [$first, $last] = [$last, $first];
        }

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'days' => (int) $first->diffInDays($last) + 1,
            'presets' => [
                'season' => __('This season'),
                'this_month' => __('This month'),
                'last_month' => __('Last month'),
                'last_three_months' => __('Last 3 months'),
                'this_year' => __('This year'),
            ],
            'report' => app(BarSalesReport::class)->between($first, $last),
        ];
    }

    /**
     * Traduire le preset en jours, aujourd'hui compris.
     *
     * « 3 derniers mois » est glissant plutôt qu'un trimestre civil : en cours de
     * saison, un trimestre clos dit moins que les trois mois qu'on vient de vivre.
     */
    protected function applyPeriod(): void
    {
        $today = CarbonImmutable::today();

        [$first, $last] = match ($this->period) {
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'last_three_months' => [$today->subMonthsNoOverflow(3)->addDay(), $today],
            'this_year' => [$today->startOfYear(), $today],
            'custom' => [null, null],
            default => $this->currentSeason($today),
        };

        if ($first === null || $last === null) {
            return;
        }

        $this->period = in_array($this->period, ['this_month', 'last_month', 'last_three_months', 'this_year', 'custom'], true) ? $this->period : 'season';
        $this->firstDay = $first->toDateString();
        $this->lastDay = $last->toDateString();
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        // Pas de lien vers le comptoir : le comité n'y a pas accès.
        return Breadcrumb::make()->home()->current(__('Bar sales'));
    }

    /**
     * La saison que le club a déclarée, ou septembre → juin à défaut, arrêtée à aujourd'hui.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function currentSeason(CarbonImmutable $today): array
    {
        $season = Season::current();

        if ($season !== null) {
            $end = CarbonImmutable::parse($season->end_at)->startOfDay();

            return [CarbonImmutable::parse($season->start_at)->startOfDay(), $end->lessThan($today) ? $end : $today];
        }

        $septemberYear = $today->month >= 9 ? $today->year : $today->year - 1;

        return [CarbonImmutable::create($septemberYear, 9, 1), $today];
    }
};
