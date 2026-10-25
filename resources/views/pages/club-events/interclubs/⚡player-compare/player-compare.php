<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\PlayerCompare;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Services\PlayerStats;
use App\Domains\Competitions\Interclub\Services\PlayerStatsFilter;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
|--------------------------------------------------------------------------
| Statistiques des joueurs — comparer
|--------------------------------------------------------------------------
|
| Deux à quatre joueurs en colonnes, chiffre par chiffre : la question « X ou Y
| dans le noyau de la B ? » posée telle quelle. Les mêmes chiffres que la liste,
| plus la ligne par écart de classement de chacun.
|
*/
new class extends Component
{
    use HasBreadcrumbs;

    /** Les joueurs comparés, ids séparés par des virgules. */
    #[Url]
    public string $ids = '';

    #[Url(except: 'interclub')]
    public string $kind = 'interclub';

    #[Url(except: 'current')]
    public string $period = 'current';

    public function render(): View
    {
        return $this->view()->title(__('Compare players'));
    }

    public function with(): array
    {
        $stats = app(PlayerStats::class);
        $kind = in_array($this->kind, PlayerStats::KINDS, true) ? $this->kind : 'interclub';
        $ids = array_slice(array_values(array_filter(array_map('intval', explode(',', $this->ids)))), 0, 4);
        $rows = $stats->rows(PlayerStatsFilter::forPeriod($this->period, ['userIds' => $ids ?: [0], 'includeFormer' => true]))->keyBy('user_id');
        $filter = PlayerStatsFilter::forPeriod($this->period);

        $players = collect($ids)
            ->filter(fn (int $id): bool => $rows->has($id))
            ->map(fn (int $id): array => [
                ...$rows->get($id),
                'by_gap' => $stats->player(User::withTrashed()->findOrFail($id), $filter, $kind)['by_gap'],
            ])
            ->values()
            ->all();

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'kindName' => $kind,
            'kinds' => [
                'interclub' => __('Interclub'),
                'tournaments' => __('Tournaments'),
                'both' => __('Both'),
            ],
            'players' => $players,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->add(__('Player statistics'), route('admin.interclubs.player-stats'))
            ->current(__('Compare players'));
    }
};
