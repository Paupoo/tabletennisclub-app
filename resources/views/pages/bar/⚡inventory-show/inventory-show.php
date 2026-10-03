<?php

declare(strict_types=1);

namespace Resources\views\Pages\Bar\InventoryShow;

use App\Domains\Bar\Models\BarInventory;
use App\Domains\Bar\Models\BarInventoryLine;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Component;

/*
|--------------------------------------------------------------------------
| Bar — un inventaire validé
|--------------------------------------------------------------------------
|
| En lecture seule : ce qui a été compté, l'écart, ce qui s'est passé, et la
| valeur au prix de vente du jour de la validation. Les lignes sans écart sont
| repliées — sur quarante produits, ce sont elles qu'on ne vient pas lire.
|
*/
new class extends Component
{
    use HasBreadcrumbs;

    public BarInventory $inventory;

    public bool $showAll = false;

    public function mount(BarInventory $inventory): void
    {
        abort_unless($inventory->hasCorrectedTheStock(), 404);

        $this->inventory = $inventory;
    }

    public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        $lines = $this->inventory->lines()->with('product')->get()
            ->sortBy(fn (BarInventoryLine $line): array => [$line->gap === 0 ? 1 : 0, $line->gap])
            ->values();

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'lines' => $this->showAll ? $lines : $lines->filter(fn (BarInventoryLine $line): bool => $line->gap !== 0),
            'hiddenCount' => $this->showAll ? 0 : $lines->where('gap', 0)->count(),
            'totals' => $this->inventory->setRelation('lines', $lines)->totals(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()->home()
            ->add(__('Inventories'), route('bar.inventories.index'))
            ->current($this->inventory->opened_at->translatedFormat('j F Y'));
    }
};
