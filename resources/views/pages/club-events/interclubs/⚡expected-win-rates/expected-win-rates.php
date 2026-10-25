<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\ExpectedWinRates;

use App\Domains\Competitions\Interclub\Models\ExpectedWinRate;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

/*
|--------------------------------------------------------------------------
| Statistiques des joueurs — la table des victoires attendues
|--------------------------------------------------------------------------
|
| Une ligne par écart de classement : la part de victoires qu'on attend d'un
| joueur face à un adversaire autant de crans plus fort ou plus faible. Le
| score de chaque joueur se calcule à la volée sur cette table ; la changer
| change tous les scores, de toutes les saisons, d'un coup.
|
*/
new class extends Component
{
    use HasBreadcrumbs, Toast;

    /** @var array<int, int> gap => rate in percent */
    public array $rates = [];

    public function mount(): void
    {
        $this->rates = ExpectedWinRate::table();
    }

    public function render(): View
    {
        return $this->view()->title(__('Expected wins'));
    }

    public function save(): void
    {
        Gate::authorize(Permission::PlayerStatsConfigure->value);

        $this->validate(
            collect(range(-ExpectedWinRate::MAX_GAP, ExpectedWinRate::MAX_GAP))
                ->mapWithKeys(fn (int $gap): array => ['rates.' . $gap => ['required', 'integer', 'between:0,100']])
                ->all(),
            attributes: ['rates.*' => __('rate')],
        );

        DB::transaction(function (): void {
            foreach ($this->rates as $gap => $rate) {
                ExpectedWinRate::query()->updateOrCreate(['gap' => (int) $gap], ['rate' => (int) $rate]);
            }
        });

        $this->success(__('Expected wins saved. Every score now reads from them.'));
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'gaps' => range(ExpectedWinRate::MAX_GAP, -ExpectedWinRate::MAX_GAP),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->add(__('Player statistics'), route('admin.interclubs.player-stats'))
            ->current(__('Expected wins'));
    }
};
