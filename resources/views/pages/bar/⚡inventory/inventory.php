<?php

declare(strict_types=1);

namespace Resources\views\Pages\Bar\Inventory;

use App\Domains\Bar\Models\BarInventory;
use App\Domains\Bar\Models\BarInventoryLine;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Services\BarInventories;
use App\Domains\Shared\Enums\BarInventoryCause;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use App\Support\LocaleSort;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

/*
|--------------------------------------------------------------------------
| Bar — l'inventaire en cours
|--------------------------------------------------------------------------
|
| On compte debout devant l'étagère, un téléphone à la main : une ligne par
| produit, un grand champ, et rien d'autre tant que le nombre tombe juste. La
| cause n'apparaît que sous une ligne dont l'écart n'est pas nul, sans choix
| présélectionné — un « je ne sais pas » par défaut deviendrait la réponse de
| chaque inventaire.
|
| Chaque nombre s'enregistre en quittant le champ : un téléphone qui se met en
| veille ou un wifi qui tombe ne fait rien perdre. Rien ne touche au stock avant
| « Valider ».
|
*/
new class extends Component
{
    use HasBreadcrumbs, Toast;

    public bool $cancelModal = false;

    /** Le mot laissé au trésorier dans le récapitulatif. */
    public string $comment = '';

    /** `all`, `uncounted` ou `gaps`. */
    public string $filter = 'all';

    public bool $validateModal = false;

    /**
     * Chaque geste repasse par ici, pas seulement l'ouverture de la page : une
     * action Livewire s'appelle sans recharger la route qui la garde.
     */
    public function boot(): void
    {
        abort_unless(Gate::allows('bar.stock.manage'), 403);
    }

    public function cancel(BarInventories $inventories): void
    {
        $this->cancelModal = false;

        $inventory = BarInventory::inProgress();

        if ($inventory !== null) {
            $inventories->cancel($inventory, auth()->user());
            $this->success(__('The inventory is cancelled. The stock has not moved.'), redirectTo: route('bar.inventories.index'));
        }
    }

    public function open(BarInventories $inventories): void
    {
        $inventories->open(auth()->user());
    }

    public function render(): View
    {
        return $this->view();
    }

    public function saveCause(int $productId, string $cause, BarInventories $inventories): void
    {
        $inventory = BarInventory::inProgress();
        $chosen = BarInventoryCause::tryFrom($cause);

        if ($inventory === null || $chosen === null) {
            return;
        }

        try {
            $inventories->explain($inventory, $productId, $chosen);
        } catch (\DomainException $exception) {
            $this->addError('causes', $exception->getMessage());
        }
    }

    /**
     * Enregistrer un comptage en quittant le champ.
     *
     * Un champ vidé ne veut pas dire zéro — on compte zéro, on ne compte pas
     * « rien » : il remet le produit parmi les non comptés.
     */
    public function saveCount(int $productId, ?string $counted, BarInventories $inventories): void
    {
        $inventory = BarInventory::inProgress();

        if ($inventory === null) {
            return;
        }

        if ($counted === null || trim($counted) === '') {
            $inventories->forget($inventory, $productId);

            return;
        }

        if (! ctype_digit(trim($counted))) {
            $this->addError('counts', __('Count a whole number, zero or more.'));

            return;
        }

        $inventories->count($inventory, $productId, (int) trim($counted), auth()->user());
    }

    public function saveNote(int $productId, ?string $note): void
    {
        BarInventory::inProgress()?->lines()->where('product_id', $productId)
            ->update(['note' => filled($note) ? mb_substr(trim($note), 0, 255) : null]);
    }

    public function validateInventory(BarInventories $inventories): void
    {
        $this->validateModal = false;

        $inventory = BarInventory::inProgress();

        if ($inventory === null) {
            return;
        }

        $this->validate(['comment' => ['nullable', 'string', 'max:500']]);

        try {
            $inventories->validate($inventory, auth()->user(), $this->comment);
        } catch (\DomainException $exception) {
            $this->addError('causes', $exception->getMessage());

            return;
        }

        $this->success(__('Inventory validated. The stock is aligned on your count.'), redirectTo: route('bar.inventories.show', $inventory));
    }

    public function with(): array
    {
        $inventory = BarInventory::inProgress()?->load(['opener', 'lines']);
        $lines = $inventory?->lines->keyBy('product_id') ?? collect();
        $products = BarProduct::query()->with('category')->withStock()->get();

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'inventory' => $inventory,
            'lines' => $lines,
            'groups' => $inventory !== null ? $this->groups($products, $lines) : collect(),
            'productCount' => $products->count(),
            'summary' => $this->summary($lines, $products->keyBy('id')),
            'shortageCauses' => $this->options(BarInventoryCause::forShortage()),
            'surplusCauses' => $this->options(BarInventoryCause::forSurplus()),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()->home()->bar()->add(__('Inventories'), route('bar.inventories.index'))->current(__('Inventory in progress'));
    }

    /**
     * Les produits rangés par rayon, comme sur l'étagère et sur l'écran Produits.
     *
     * @param  Collection<int, BarProduct>  $products
     * @param  Collection<int, BarInventoryLine>  $lines
     * @return Collection<int, array{label: string, products: Collection<int, BarProduct>}>
     */
    protected function groups(Collection $products, Collection $lines): Collection
    {
        $kept = $products->filter(fn (BarProduct $product): bool => match ($this->filter) {
            'uncounted' => ! $lines->has($product->id),
            'gaps' => $lines->has($product->id) && $lines[$product->id]->gap !== 0,
            default => true,
        });

        return LocaleSort::by(
            $kept->groupBy('category_id')->map(fn (Collection $rows): array => [
                'label' => (string) $rows->first()->category?->name,
                'products' => LocaleSort::by($rows, fn (BarProduct $p): string => $p->name),
            ])->values(),
            fn (array $group): string => $group['label'],
        );
    }

    /**
     * @param  array<int, BarInventoryCause>  $causes
     * @return array<int, array{id: string, name: string}>
     */
    protected function options(array $causes): array
    {
        return array_map(fn (BarInventoryCause $cause): array => ['id' => $cause->value, 'name' => $cause->label()], $causes);
    }

    /**
     * Ce que l'inventaire écrirait s'il était validé maintenant.
     *
     * @param  Collection<int, BarInventoryLine>  $lines
     * @param  Collection<int, BarProduct>  $products
     * @return array{counted: int, gaps: int, missing: int, surplus: int, added: int, value: int, unexplained: int}
     */
    protected function summary(Collection $lines, Collection $products): array
    {
        $summary = ['counted' => $lines->count(), 'gaps' => 0, 'missing' => 0, 'surplus' => 0, 'added' => 0, 'value' => 0, 'unexplained' => 0];

        foreach ($lines as $line) {
            if ($line->gap === 0) {
                continue;
            }

            if ($line->cause === BarInventoryCause::AddedToBar) {
                $summary['added'] += $line->gap;

                continue;
            }

            $summary['gaps']++;
            $summary[$line->gap < 0 ? 'missing' : 'surplus'] += abs($line->gap);
            $summary['value'] += $line->gap * (int) ($products[$line->product_id]->sale_price ?? 0);
            $summary['unexplained'] += $line->cause === null ? 1 : 0;
        }

        return $summary;
    }
};
