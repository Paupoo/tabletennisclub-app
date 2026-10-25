<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\PlayerStat;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Services\PlayerStats;
use App\Domains\Competitions\Interclub\Services\PlayerStatsFilter;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
|--------------------------------------------------------------------------
| Statistiques des joueurs — un joueur
|--------------------------------------------------------------------------
|
| Tout ce que la liste résume, pour une personne : contre quels classements
| elle gagne, ses perfs et ses contre-perfs, ses adversaires, ses équipes, son
| classement de feuille en feuille et sa courbe mois par mois.
|
| Un ancien membre s'ouvre aussi, archivé compris : la liste les propose sur
| demande. Un membre anonymisé jamais — il n'a plus de nom à montrer.
|
*/
new class extends Component
{
    use HasBreadcrumbs;

    #[Url(except: 'interclub')]
    public string $kind = 'interclub';

    #[Locked]
    public int $memberId;

    #[Url(except: 'current')]
    public string $period = 'current';

    public function mount(int $member): void
    {
        $found = User::withTrashed()->find($member);

        abort_if($found === null || str_ends_with((string) $found->email, '@anonymous.local'), 404);

        $this->memberId = $found->id;
    }

    public function render(): View
    {
        return $this->view()->title($this->member()->first_name . ' ' . $this->member()->last_name);
    }

    public function with(): array
    {
        $stats = app(PlayerStats::class);
        $kind = in_array($this->kind, PlayerStats::KINDS, true) ? $this->kind : 'interclub';
        $member = $this->member();
        $row = $stats->rows(PlayerStatsFilter::forPeriod($this->period, ['userIds' => [$member->id], 'includeFormer' => true]))->first();
        $detail = $stats->player($member, PlayerStatsFilter::forPeriod($this->period), $kind);

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'detail' => $detail,
            'figures' => $row[$kind] ?? null,
            'kindName' => $kind,
            'kinds' => [
                'interclub' => __('Interclub'),
                'tournaments' => __('Tournaments'),
                'both' => __('Both'),
            ],
            'member' => $member,
            'monthly' => array_map(fn (array $month): array => [
                'label' => Carbon::parse($month['label'] . '-01')->translatedFormat('M y'),
                'long' => Carbon::parse($month['label'] . '-01')->translatedFormat('F Y'),
                'value' => (float) ($month['score_per_match'] ?? 0),
                'played' => $month['played'],
                'won' => $month['won'],
            ], $detail['monthly']),
            'periods' => [
                ['id' => 'current', 'name' => __('This season')],
                ['id' => 'months-3', 'name' => __('Last 3 months')],
                ['id' => 'months-12', 'name' => __('Last 12 months')],
                ['id' => 'all', 'name' => __('All seasons')],
                ...Season::query()->orderByDesc('start_at')->get()
                    ->map(fn (Season $season): array => ['id' => (string) $season->id, 'name' => $season->name])
                    ->all(),
            ],
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->add(__('Player statistics'), route('admin.interclubs.player-stats'))
            ->current($this->member()->first_name . ' ' . $this->member()->last_name);
    }

    private function member(): User
    {
        return User::withTrashed()->findOrFail($this->memberId);
    }
};
