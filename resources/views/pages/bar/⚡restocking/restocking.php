<?php

declare(strict_types=1);

namespace Resources\views\Pages\Bar\Restocking;

use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarRestocking;
use App\Domains\Bar\Models\BarRestockingLine;
use App\Domains\Bar\Services\RestockingList;
use App\Domains\Bar\Services\RestockingTrips;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\Shared\Enums\Feature;
use App\Domains\Shared\Rules\ValidIban;
use App\Domains\Shared\Support\IbanNormalizer;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use App\Support\LocaleSort;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
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
    use HasBreadcrumbs, Toast, WithFileUploads;

    /** Le plafond de justificatifs d'une note de frais, comme sur l'écran du membre. */
    private const int MAX_TICKET_FILES = 5;

    public bool $abandonModal = false;

    /**
     * Conditionnements achetés, par ligne de la liste figée.
     *
     * @var array<int, int|string>
     */
    public array $bought = [];

    /** L'écran de retour du magasin, à la place de la liste. */
    public bool $closing = false;

    public ?int $extraProductId = null;

    /**
     * Conditionnements achetés hors liste, par produit.
     *
     * @var array<int, int|string>
     */
    public array $extras = [];

    /** Qui a payé : `me`, `club` ou `nobody` — voir BarRestocking::PAID_BY_*. */
    public ?string $paidBy = null;

    public string $refundIban = '';

    public bool $takeOverModal = false;

    public string $ticketAmount = '';

    /** @var array<int, mixed> */
    public array $ticketFiles = [];

    public function abandon(RestockingTrips $restockingTrips): void
    {
        $this->abandonModal = false;

        $trip = BarRestocking::inProgress();

        if ($trip !== null) {
            $restockingTrips->abandon($trip, auth()->user());
            $this->success(__('The trip is abandoned. The list is free.'));
        }
    }

    /**
     * Ajouter un produit du catalogue qui n'était pas sur la liste.
     *
     * Choisi dans le catalogue, jamais créé ici : créer un produit reste le
     * travail de l'écran Produits, avec son prix et sa catégorie.
     */
    public function addExtra(): void
    {
        $trip = $this->myTrip();

        if ($trip === null || $this->extraProductId === null) {
            return;
        }

        $productId = $this->extraProductId;
        $this->extraProductId = null;

        if ($trip->lines->contains('product_id', $productId) || ! BarProduct::query()->whereKey($productId)->exists()) {
            return;
        }

        $this->extras[$productId] ??= 1;
    }

    public function close(RestockingTrips $restockingTrips): void
    {
        $trip = $this->myTrip();

        if ($trip === null) {
            return;
        }

        $this->ticketAmount = str_replace([',', ' ', '€'], ['.', '', ''], $this->ticketAmount);

        $claims = $this->paidBy === BarRestocking::PAID_BY_ME;

        // Avant toute validation : un mineur n'a pas à se voir réclamer un IBAN pour
        // une note qu'il ne peut pas soumettre.
        if ($claims && ! $this->canClaim()) {
            $this->resetValidation();
            $this->addError('paidBy', __('Only an adult member, acting for themself, may claim an expense back.'));

            return;
        }

        $this->validate([
            'bought.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'extras.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'paidBy' => ['required', Rule::in([BarRestocking::PAID_BY_ME, BarRestocking::PAID_BY_CLUB, BarRestocking::PAID_BY_NOBODY])],
            'ticketAmount' => $claims ? ['required', 'numeric', 'min:0.01', 'max:99999.99'] : ['nullable'],
            'refundIban' => $claims ? ['required', new ValidIban] : ['nullable'],
            'ticketFiles' => $claims ? ['required', 'array', 'min:1', 'max:' . self::MAX_TICKET_FILES] : ['nullable', 'array'],
            'ticketFiles.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [], [
            'paidBy' => __('Who paid'),
            'ticketAmount' => __('Receipt total'),
            'refundIban' => __('Refund account (IBAN)'),
            'ticketFiles' => __('Receipt'),
        ]);

        try {
            $restockingTrips->close(
                $trip,
                auth()->user(),
                $this->bought,
                $this->extras,
                (string) $this->paidBy,
                $claims ? ['amount' => round((float) $this->ticketAmount, 2), 'iban' => $this->refundIban, 'files' => $this->ticketFiles] : null,
            );
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());

            return;
        }

        $this->reset('closing', 'bought', 'extras', 'extraProductId', 'paidBy', 'ticketAmount', 'ticketFiles', 'refundIban');
        $this->success($claims
            ? __('Thank you! The stock is updated and your expense report is submitted.')
            : __('Thank you! The stock is updated.'));
    }

    /**
     * Passer à l'écran du retour, pré-rempli par les cases cochées.
     *
     * Coché = la quantité proposée, non coché = 0 (« pas trouvé ») : on ne corrige
     * plus que les exceptions, et un article oublié en rayon n'entre pas en stock.
     */
    public function openClosing(): void
    {
        $trip = $this->myTrip();

        if ($trip === null) {
            return;
        }

        $this->bought = $trip->lines
            ->mapWithKeys(fn (BarRestockingLine $line): array => [$line->id => $line->in_cart ? $line->proposed_packs : 0])
            ->all();
        $this->extras = [];
        $this->refundIban = (string) IbanNormalizer::format(auth()->user()->iban);
        $this->closing = true;
    }

    public function removeExtra(int $productId): void
    {
        unset($this->extras[$productId]);
    }

    public function removeTicketFile(int $index): void
    {
        unset($this->ticketFiles[$index]);
        $this->ticketFiles = array_values($this->ticketFiles);
    }

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

    public function takeOver(RestockingTrips $restockingTrips): void
    {
        $this->takeOverModal = false;

        $trip = BarRestocking::inProgress();

        if ($trip !== null) {
            $restockingTrips->takeOver($trip, auth()->user());
            $this->success(__('The trip is yours now.'));
        }
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

        $sections = $trip !== null ? $this->sectionsOfTrip($trip) : $this->sectionsOfList($restockingList->current());

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'canClaim' => $this->closing && $this->canClaim(),
            'listText' => $this->asText($sections),
            'trip' => $trip,
            'isMine' => $trip !== null && $trip->shopper_id === auth()->id(),
            'sections' => $sections,
            'extraOptions' => $this->closing && $trip !== null ? $this->extraOptions($trip) : [],
            'extraProducts' => BarProduct::query()->whereKey(array_keys($this->extras))->get()->keyBy('id'),
        ];
    }

    /**
     * La liste en texte brut, à coller dans une conversation.
     *
     * Pour qui préfère le papier, ou part à deux et se partage les rayons. Aucun
     * signe de ponctuation propre à une langue : le texte se colle tel quel.
     *
     * @param  array<string, array<string, array<int, array<string, mixed>>>>  $sections
     */
    protected function asText(array $sections): string
    {
        $blocks = [];

        foreach (['to_buy' => __('To buy'), 'if_room' => __('If you have room')] as $section => $title) {
            if (($sections[$section] ?? []) === []) {
                continue;
            }

            $lines = [$title];

            foreach ($sections[$section] as $category => $products) {
                $lines[] = '— ' . $category;

                foreach ($products as $product) {
                    $lines[] = "• {$product['name']} · {$product['packs_label']} ({$product['units']})";
                }
            }

            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
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
     * Celui qui rentre peut-il se faire rembourser par une note de frais ?
     *
     * La même règle que partout ailleurs : un adulte, agissant pour lui-même, et
     * les notes de frais ouvertes au club.
     */
    protected function canClaim(): bool
    {
        return Feature::ExpenseReports->enabled() && Gate::allows('create', ExpenseReport::class);
    }

    /**
     * Les produits qu'on peut ajouter : ceux du catalogue qui ne sont pas déjà sur la liste.
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function extraOptions(BarRestocking $trip): array
    {
        $taken = array_merge($trip->lines->pluck('product_id')->all(), array_keys($this->extras));

        return LocaleSort::byKey(
            BarProduct::query()->whereKeyNot($taken)->get()->map(fn (BarProduct $p): array => ['id' => $p->id, 'name' => $p->name]),
            'name'
        )->values()->all();
    }

    /**
     * La tournée en cours, si c'est celle de l'utilisateur.
     */
    protected function myTrip(): ?BarRestocking
    {
        $trip = BarRestocking::inProgress();

        return $trip !== null && $trip->shopper_id === auth()->id() ? $trip : null;
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
                'packs_label' => RestockingList::packsLabel($line['packs'], $line['pack_size'], $line['pack_label']),
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
                'packs_label' => RestockingList::packsLabel($line->proposed_packs, $line->pack_size, $line->pack_label),
                'pack_size' => $line->pack_size,
                'pack_label' => $line->pack_label,
                'units' => $line->proposed_packs * $line->pack_size,
                'stock' => $line->stock_at_start,
                'max' => $line->product->max_stock,
                'in_cart' => $line->in_cart,
            ];
        }

        return array_map($this->byCategory(...), $sections);
    }
};
