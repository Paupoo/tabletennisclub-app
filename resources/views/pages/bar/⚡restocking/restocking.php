<?php

declare(strict_types=1);

namespace Resources\views\Pages\Bar\Restocking;

use App\Domains\Bar\Models\BarRestocking;
use App\Domains\Bar\Models\BarRestockingLine;
use App\Domains\Bar\Services\RestockingList;
use App\Domains\Bar\Services\RestockingTrips;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use App\Support\LocaleSort;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

/*
|--------------------------------------------------------------------------
| Bar — les courses
|--------------------------------------------------------------------------
|
| L'écran se lit sur un téléphone, d'une main, le caddie dans l'autre. Il ne dit
| qu'une chose par ligne, en gros : quoi, et combien de conditionnements. Le
| reste — unités, stock, max — est en petit, pour qui veut vérifier.
|
| Trois états, un seul écran :
|
| - pas de tournée : la liste du moment, et « Je fais les courses » ;
| - ma tournée : la liste figée, une case « dans le caddie » par ligne ;
| - la tournée d'un autre : qui y est, depuis quand, et de quoi la reprendre.
|
*/
new class extends Component
{
    use HasBreadcrumbs, Toast;

    public function render(): View
    {
        return $this->view();
    }

    public function start(RestockingTrips $restockingTrips): void
    {
        try {
            $restockingTrips->start(auth()->user());
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());

            return;
        }

        $this->success(__('The list is yours. Good shopping!'));
    }

    /**
     * Cocher ou décocher une ligne « dans le caddie ».
     *
     * Enregistré tout de suite : la case doit survivre à un rechargement ou à une
     * coupure de réseau au milieu du rayon. Seul celui qui tient la tournée coche —
     * un autre la reprend d'abord.
     */
    public function toggleInCart(int $lineId, bool $inCart): void
    {
        $line = BarRestockingLine::query()->with('restocking')->findOrFail($lineId);

        if (! $line->restocking->isInProgress() || $line->restocking->shopper_id !== auth()->id()) {
            return;
        }

        $line->update(['in_cart' => $inCart]);
    }

    public function with(RestockingList $restockingList): array
    {
        $trip = BarRestocking::inProgress()?->load(['shopper', 'lines.product.category']);

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'trip' => $trip,
            'isMine' => $trip !== null && $trip->shopper_id === auth()->id(),
            'sections' => $trip !== null ? $this->sectionsOfTrip($trip) : $this->sectionsOfList($restockingList->current()),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()->home()->bar()->current(__('Shopping'));
    }

    /**
     * Les lignes rangées par section puis par rayon.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function byCategory(array $lines): array
    {
        $grouped = [];

        foreach (LocaleSort::by(collect($lines), fn (array $line): string => $line['name']) as $line) {
            $grouped[$line['category']][] = $line;
        }

        return LocaleSort::by(collect(array_keys($grouped)), fn (string $category): string => $category)
            ->mapWithKeys(fn (string $category): array => [$category => $grouped[$category]])
            ->all();
    }

    /**
     * « 2 × casier », « 2 × 24 », ou « 7 » pour un produit qui s'achète à l'unité.
     */
    protected function packsLabel(int $packs, int $packSize, ?string $packLabel): string
    {
        if ($packSize === 1 && ($packLabel === null || $packLabel === '')) {
            return (string) $packs;
        }

        return $packs . ' × ' . ($packLabel !== null && $packLabel !== '' ? $packLabel : $packSize);
    }

    /**
     * @param  array{to_buy: list<array<string, mixed>>, if_room: list<array<string, mixed>>}  $list
     * @return array<string, array<string, array<int, array<string, mixed>>>>
     */
    protected function sectionsOfList(array $list): array
    {
        $sections = [];

        foreach ($list as $section => $lines) {
            $sections[$section] = $this->byCategory(array_map(fn (array $line): array => [
                'line_id' => null,
                'name' => $line['name'],
                'category' => $line['category'],
                'packs_label' => $this->packsLabel($line['packs'], $line['pack_size'], $line['pack_label']),
                'units' => $line['units'],
                'stock' => $line['stock'],
                'max' => $line['max'],
                'in_cart' => false,
            ], $lines));
        }

        return $sections;
    }

    /**
     * @return array<string, array<string, array<int, array<string, mixed>>>>
     */
    protected function sectionsOfTrip(BarRestocking $trip): array
    {
        $sections = [BarRestockingLine::SECTION_TO_BUY => [], BarRestockingLine::SECTION_IF_ROOM => []];

        foreach ($trip->lines as $line) {
            if (! array_key_exists($line->section, $sections)) {
                continue;
            }

            $sections[$line->section][] = [
                'line_id' => $line->id,
                'name' => $line->product->name,
                'category' => $line->product->category->name,
                'packs_label' => $this->packsLabel($line->proposed_packs, $line->pack_size, $line->pack_label),
                'units' => $line->proposed_packs * $line->pack_size,
                'stock' => $line->stock_at_start,
                'max' => $line->product->max_stock,
                'in_cart' => $line->in_cart,
            ];
        }

        return array_map(fn (array $lines): array => $this->byCategory($lines), $sections);
    }
};
