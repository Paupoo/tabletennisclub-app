<?php

declare(strict_types=1);

namespace Resources\views\Pages\Bar\Inventories;

use App\Domains\Bar\Models\BarInventory;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/*
|--------------------------------------------------------------------------
| Bar — les inventaires
|--------------------------------------------------------------------------
|
| Chaque correction du stock, qui l'a faite et quand. Le comité le lit comme il
| lit les ventes ; seuls ceux qui gèrent le stock y voient le bouton qui compte.
|
*/
new class extends Component
{
    use HasBreadcrumbs, WithPagination;

    public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        $inventories = BarInventory::query()
            ->with(['opener', 'closer', 'lines'])
            ->withCount('lines')
            ->orderByDesc('opened_at')
            ->orderByDesc('bar_inventories.id')
            ->paginate(20);

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'inventories' => $inventories,
            'totals' => $inventories->getCollection()->mapWithKeys(fn (BarInventory $inventory): array => [$inventory->id => $inventory->totals()]),
            'inProgress' => BarInventory::inProgress()?->loadCount('lines')->load('opener'),
            'canCount' => auth()->user()->can('bar.stock.manage'),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        // Pas de lien vers le comptoir : le comité n'y a pas accès.
        return Breadcrumb::make()->home()->current(__('Inventories'));
    }
};
