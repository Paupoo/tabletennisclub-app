<?php

declare(strict_types=1);

namespace Resources\views\Pages\Bar\Counter;

use App\Actions\Bar\SettleBarOrder;
use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Services\BarCartService;
use App\Domains\Bar\Services\ClubRounds;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use App\Support\LocaleSort;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

/*
|--------------------------------------------------------------------------
| Bar — le comptoir
|--------------------------------------------------------------------------
|
| On sert d'abord, on décide ensuite. L'écran s'ouvre sur la carte : un tap sur une
| tuile ajoute une consommation, et « Terminer » demande où va la tournée — régler
| en cash ou par QR, la poser sur une ardoise, ou l'offrir comme verre du club.
|
| L'ancien comptoir demandait « pour qui sert-on ? » avant de montrer la carte, puis
| passait par une page ticket et une page d'encaissement : 7 taps et 4 écrans pour
| deux bières payées en cash, sur un téléphone tenu d'une main. C'est le seul écran
| de l'application où la latence fait partie de la tâche ; un point de vente lent se
| fait contourner, on note sur un papier, et le stock devient faux.
|
| Le cash et le verre du club n'ont plus de confirmation : ils s'annulent pendant
| 10 secondes. La commande est enregistrée tout de suite, **non payée** ; le
| règlement ne part qu'à la fin du délai. « On ne rouvre pas une commande payée »
| tient donc toujours : on n'annule que ce qui n'a pas encore été réglé. Le délai
| vit en session, pour qu'un barman qui quitte l'écran ne laisse pas la commande en
| suspens : elle se règle à son prochain passage.
|
| Le retour d'information est l'état, pas un message : la pastille qui passe de 2
| à 3 EST la confirmation. Les toasts sont réservés à ce qui empêche l'action, et à
| la tournée qui part sur une ardoise, qu'aucun autre signe ne montre.
|
*/
new class extends Component
{
    use HasBreadcrumbs, Toast;

    /**
     * Le délai pendant lequel un cash ou un verre du club peut être annulé.
     */
    public const int UNDO_SECONDS = 10;

    public bool $clubSheet = false;

    public bool $finishSheet = false;

    public string $otherBeneficiary = '';

    public string $search = '';

    public ?int $tabCardId = null;

    public bool $tabCardSheet = false;

    public string $tabNameInput = '';

    public bool $tabSheet = false;

    public bool $tabsSheet = false;

    public function add(int $productId, BarCartService $cartService): void
    {
        $this->authorizeOrders();

        // Servir le client suivant, c'est clore la tournée précédente : son délai
        // d'annulation s'arrête là.
        $this->confirmPending();

        $result = $cartService->addProductToSessionCart($productId);

        // Seul l'échec parle. Trente toasts « ajouté » dans une tournée finiraient
        // par masquer le seul qui compte.
        if ($result['status'] !== 'success') {
            $this->error($result['message']);
        }

        $this->forgetCart();
    }

    /**
     * Servir sur une ardoise ouverte : son contenu se charge, et « Terminer »
     * l'enregistrera. Un `−` corrige alors aussi ce qui avait été servi avant.
     */
    public function addToTab(int $orderId, BarCartService $cartService): void
    {
        $this->authorizeOrders();
        $this->confirmPending();

        $order = BarOrder::query()->where('is_paid', 0)->find($orderId);

        if (! $order instanceof BarOrder || $order->name === null) {
            $this->error('Cette ardoise n’est plus ouverte.');

            return;
        }

        $result = $cartService->openTab((string) $order->name);

        if ($result['status'] !== 'success') {
            $this->error($result['message']);

            return;
        }

        $this->tabCardSheet = false;
        $this->tabsSheet = false;
        $this->forgetCart();
    }

    public function cancelPending(BarCartService $cartService): void
    {
        $this->authorizeOrders();

        $pending = $this->pending;

        if ($pending === null) {
            return;
        }

        session()->forget('bar_pending');

        $order = BarOrder::query()->find($pending['order_id']);

        // Une ardoise réglée depuis sa fiche n'a rien à recharger : elle reste ouverte,
        // telle quelle. Une tournée qu'on vient de servir, elle, revient au panier.
        if ($order instanceof BarOrder && ! $order->is_paid && $pending['restore']) {
            $cartService->loadOrderIntoSessionCart($order);
        }

        $this->forgetCart();
    }

    #[Computed]
    public function canPay(): bool
    {
        return auth()->user()?->can(Permission::BarOrdersPay->value) === true;
    }

    #[Computed]
    public function cart(): array
    {
        return collect(session()->get('cart', []))
            ->map(fn ($qty): int => (int) $qty)
            ->filter(fn (int $qty): bool => $qty > 0)
            ->all();
    }

    #[Computed]
    public function cartCount(): int
    {
        return array_sum($this->cart);
    }

    /**
     * La carte, rayon par rayon. Les rayons vides disparaissent pendant une recherche.
     *
     * @return Collection<int, BarCategory>
     */
    #[Computed]
    public function catalogue(): Collection
    {
        $search = trim($this->search);

        return BarCategory::query()
            ->when($search !== '', fn ($query) => $query->whereHas(
                'products',
                fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%")
            ))
            ->with(['products' => fn ($q) => $q
                ->withStock()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    /**
     * Les équipes visiteuses du jour, pour le verre du club.
     *
     * @return Collection<int, array{name: string, category: string, cap: int, served_at: string}>
     */
    #[Computed]
    public function clubTeams(): Collection
    {
        return app(ClubRounds::class)->teamsFor(now(), now());
    }

    /**
     * Ce qui reste à régler de la commande en délai d'annulation.
     *
     * Appelée par la barre d'annulation à la fin du décompte, et par toute action qui
     * passe à autre chose. Sans délai en cours, elle ne fait rien.
     */
    public function confirmPending(): void
    {
        $pending = $this->pending;

        if ($pending === null) {
            return;
        }

        session()->forget('bar_pending');

        $order = BarOrder::query()->find($pending['order_id']);

        if (! $order instanceof BarOrder || $order->is_paid) {
            return;
        }

        if ($pending['method'] === 'offered') {
            $refusal = app(ClubRounds::class)->refusal(
                (int) $order->items()->sum('quantity'),
                (string) $pending['reason'],
                $pending['manual'],
                now(),
                now(),
            );

            // Une autre caisse a pu servir la même équipe pendant le délai. La tournée
            // n'est pas perdue : elle revient au panier, à régler autrement.
            if ($refusal !== null) {
                app(BarCartService::class)->loadOrderIntoSessionCart($order);
                $this->error($refusal);
                $this->forgetCart();

                return;
            }
        }

        app(SettleBarOrder::class)($order, $pending['method'], $pending['reason']);
        unset($this->openTabs);
    }

    /**
     * L'ardoise chargée pour la compléter ou la corriger, ou null.
     */
    #[Computed]
    public function editingTab(): ?string
    {
        $name = session()->get('bar_tab_name');

        return is_string($name) && $name !== '' && session()->has('editing_order_id') ? $name : null;
    }

    /**
     * Offrir la tournée à une équipe visiteuse, ou à un bénéficiaire tapé à la main.
     */
    public function giveClubRound(string $beneficiary, bool $manual, BarCartService $cartService): void
    {
        $this->authorizePayments();

        $refusal = app(ClubRounds::class)->refusal($this->cartCount, $beneficiary, $manual, now(), now());

        if ($refusal !== null) {
            $this->error($refusal);

            return;
        }

        $order = $this->checkout($cartService);

        if (! $order instanceof BarOrder) {
            return;
        }

        $this->hold($order, 'offered', trim($beneficiary), $manual, restore: true,
            label: sprintf('Verre du club · %s', trim($beneficiary)));
    }

    public function giveOtherClubRound(BarCartService $cartService): void
    {
        $this->giveClubRound($this->otherBeneficiary, true, $cartService);
    }

    public function leaveTab(BarCartService $cartService): void
    {
        $this->authorizeOrders();

        $cartService->clearSessionCart();

        $this->forgetCart();
    }

    public function mount(): void
    {
        // Un délai qu'on a laissé courir en quittant l'écran est arrivé à son terme.
        if ($this->pending !== null && $this->pendingSecondsLeft === 0) {
            $this->confirmPending();
        }
    }

    public function openClubSheet(): void
    {
        $this->finishSheet = false;
        $this->otherBeneficiary = '';
        unset($this->clubTeams);
        $this->clubSheet = true;
    }

    public function openFinishSheet(): void
    {
        if ($this->cartCount === 0) {
            return;
        }

        $this->finishSheet = true;
    }

    /**
     * @return Collection<int, BarOrder>
     */
    #[Computed]
    public function openTabs(): Collection
    {
        return LocaleSort::by(
            BarOrder::query()->with('items.product')->where('is_paid', 0)->whereNotNull('name')->get(),
            fn (BarOrder $order): string => (string) $order->name
        );
    }

    public function openTabSheet(): void
    {
        $this->finishSheet = false;
        $this->tabNameInput = '';
        unset($this->openTabs);
        $this->tabSheet = true;
    }

    /**
     * Régler la tournée en cash : enregistrée tout de suite, réglée au bout du délai.
     */
    public function payCash(BarCartService $cartService): void
    {
        $this->authorizePayments();

        $order = $this->checkout($cartService);

        if (! $order instanceof BarOrder) {
            return;
        }

        $this->hold($order, 'cash', null, false, restore: true,
            label: sprintf('Réglé en cash · %s', euros((int) $order->total_price)));
    }

    /**
     * Régler par QR : l'écran du code, qui sert lui-même de confirmation.
     */
    public function payQr(BarCartService $cartService): void
    {
        $this->authorizePayments();

        $order = $this->checkout($cartService);

        if ($order instanceof BarOrder) {
            $this->redirectRoute('bar.payment.show', ['order' => $order->id, 'method' => 'qr']);
        }
    }

    /**
     * La commande en délai d'annulation, ou null.
     *
     * @return array{order_id: int, method: string, reason: ?string, manual: bool, restore: bool, label: string, at: int}|null
     */
    #[Computed]
    public function pending(): ?array
    {
        $pending = session()->get('bar_pending');

        return is_array($pending) && isset($pending['order_id'], $pending['method'], $pending['at']) ? [
            'order_id' => (int) $pending['order_id'],
            'method' => (string) $pending['method'],
            'reason' => isset($pending['reason']) ? (string) $pending['reason'] : null,
            'manual' => (bool) ($pending['manual'] ?? false),
            'restore' => (bool) ($pending['restore'] ?? false),
            'label' => (string) ($pending['label'] ?? ''),
            'at' => (int) $pending['at'],
        ] : null;
    }

    #[Computed]
    public function pendingSecondsLeft(): int
    {
        $pending = $this->pending;

        if ($pending === null) {
            return 0;
        }

        return max(0, self::UNDO_SECONDS - (now()->getTimestamp() - $pending['at']));
    }

    /**
     * Poser la tournée sur une ardoise : celle qu'on a chargée, ou celle qu'on nomme.
     */
    public function putOnTab(?string $name, BarCartService $cartService): void
    {
        $this->authorizeOrders();
        $this->confirmPending();

        try {
            $order = $this->editingTab !== null
                ? $cartService->checkoutFromSessionCart('validate')
                : $cartService->checkoutOntoTab($name ?? $this->tabNameInput);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->tabSheet = false;
        $this->finishSheet = false;
        $this->tabNameInput = '';
        $this->forgetCart();

        $this->success(sprintf('Ajouté à « %s » — %s à régler.', (string) $order->name, euros((int) $order->total_price)));
    }

    public function remove(int $productId, BarCartService $cartService): void
    {
        $this->authorizeOrders();

        $cartService->removeProductFromSessionCart($productId);

        $this->forgetCart();

        if ($this->cartCount === 0) {
            $this->finishSheet = false;
        }
    }

    public function render(): View
    {
        return $this->view();
    }

    /**
     * Régler une ardoise ouverte en cash, depuis sa fiche.
     */
    public function settleTabCash(int $orderId): void
    {
        $this->authorizePayments();
        $this->confirmPending();

        $order = BarOrder::query()->where('is_paid', 0)->find($orderId);

        if (! $order instanceof BarOrder) {
            $this->error('Cette ardoise n’est plus ouverte.');

            return;
        }

        $this->tabCardSheet = false;
        $this->tabsSheet = false;

        $this->hold($order, 'cash', null, false, restore: false,
            label: sprintf('%s réglée en cash · %s', (string) ($order->name ?? '#' . $order->id), euros((int) $order->total_price)));
    }

    public function settleTabQr(int $orderId): void
    {
        $this->authorizePayments();
        $this->confirmPending();

        $this->redirectRoute('bar.payment.show', ['order' => $orderId, 'method' => 'qr']);
    }

    public function showTab(int $orderId): void
    {
        $this->tabCardId = $orderId;
        $this->tabsSheet = false;
        $this->tabCardSheet = true;
    }

    public function showTabs(): void
    {
        unset($this->openTabs);
        $this->tabsSheet = true;
    }

    /**
     * L'état d'un produit dans la grille.
     *
     * La pastille ne parle que quand le stock compte : « Plus que 2 » ou « Épuisé ».
     * « 12 en stock » sur chaque tuile n'apprenait rien au comptoir, et coûtait la
     * place d'un nom sur un petit écran.
     *
     * @return object{qty:int, isUnavailable:bool, isOut:bool, disablePlus:bool, note:?string}
     */
    public function stateOf(BarProduct $product): object
    {
        $qty = $this->cart[$product->id] ?? 0;
        $stock = (int) $product->stock;
        $isUnavailable = ! $product->is_available;
        $isOut = $stock <= 0;
        $left = max(0, $stock - $qty);

        $note = match (true) {
            $isUnavailable => __('Unavailable'),
            $isOut => __('Sold out'),
            $left === 0 => __('Stock maximum reached'),
            $stock <= $product->effective_low_stock_threshold => __('Only :count left', ['count' => $left]),
            default => null,
        };

        return (object) [
            'qty' => $qty,
            'isUnavailable' => $isUnavailable,
            'isOut' => $isOut,
            'disablePlus' => $isUnavailable || $isOut || $left === 0,
            'note' => $note,
        ];
    }

    /**
     * L'ardoise montrée dans sa fiche.
     */
    #[Computed]
    public function tabCard(): ?BarOrder
    {
        return $this->tabCardId === null
            ? null
            : BarOrder::query()->with('items.product')->where('is_paid', 0)->find($this->tabCardId);
    }

    /**
     * Les noms proposés sous le champ « nouvelle ardoise » : les ardoises ouvertes qui
     * ressemblent à ce qu'on tape, puis les équipes du jour — les nôtres comme les
     * visiteuses — qui sont les ardoises les plus fréquentes un soir d'interclub.
     *
     * @return Collection<int, array{name: string, total: ?int}>
     */
    #[Computed]
    public function tabSuggestions(): Collection
    {
        $typed = BarOrder::normaliseName($this->tabNameInput);
        $matches = fn (string $name): bool => $typed === '' || str_contains(BarOrder::normaliseName($name), $typed);

        $open = $this->openTabs
            ->filter(fn (BarOrder $order): bool => $typed !== '' && $matches((string) $order->name))
            ->map(fn (BarOrder $order): array => ['name' => (string) $order->name, 'total' => (int) $order->total_price]);

        $openKeys = $this->openTabs->map(fn (BarOrder $order): string => BarOrder::normaliseName($order->name))->all();

        $teams = $this->teamsPlayingToday()
            ->reject(fn (string $name): bool => in_array(BarOrder::normaliseName($name), $openKeys, true))
            ->filter($matches)
            ->map(fn (string $name): array => ['name' => $name, 'total' => null]);

        return $open->concat($teams)->take(6)->values();
    }

    #[Computed]
    public function totalPrice(): int
    {
        $cart = $this->cart;

        if ($cart === []) {
            return 0;
        }

        return (int) BarProduct::query()
            ->whereIn('id', array_keys($cart))
            ->get()
            ->sum(fn (BarProduct $p): int => (int) $p->sale_price * (int) ($cart[$p->id] ?? 0));
    }

    public function updatedSearch(): void
    {
        unset($this->catalogue);
    }

    public function updatedTabNameInput(): void
    {
        unset($this->tabSuggestions);
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'undoSeconds' => self::UNDO_SECONDS,
        ];
    }

    protected function authorizeOrders(): void
    {
        abort_unless(auth()->user()?->can(Permission::BarOrdersManage->value), 403);
    }

    protected function authorizePayments(): void
    {
        $this->authorizeOrders();
        abort_unless($this->canPay, 403);
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()->home()->bar()->current(__('New order'));
    }

    /**
     * Enregistrer le panier en commande à régler, ou dire pourquoi c'est impossible.
     */
    private function checkout(BarCartService $cartService): ?BarOrder
    {
        $this->confirmPending();

        try {
            $order = $cartService->checkoutFromSessionCart('pay_now');
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return null;
        } catch (\Throwable $e) {
            report($e);
            $this->error('Une erreur est survenue lors de la validation de la commande.');

            return null;
        }

        $this->finishSheet = false;
        $this->clubSheet = false;
        $this->forgetCart();

        return $order;
    }

    private function forgetCart(): void
    {
        unset($this->cart, $this->cartCount, $this->totalPrice, $this->catalogue, $this->openTabs, $this->pending, $this->pendingSecondsLeft, $this->editingTab);
    }

    /**
     * Ouvrir le délai d'annulation d'un règlement.
     */
    private function hold(BarOrder $order, string $method, ?string $reason, bool $manual, bool $restore, string $label): void
    {
        session()->put('bar_pending', [
            'order_id' => (int) $order->id,
            'method' => $method,
            'reason' => $reason,
            'manual' => $manual,
            'restore' => $restore,
            'label' => $label,
            'at' => now()->getTimestamp(),
        ]);

        $this->forgetCart();
    }

    /**
     * Les noms complets des équipes qui jouent chez nous aujourd'hui, des deux côtés.
     *
     * @return Collection<int, string>
     */
    private function teamsPlayingToday(): Collection
    {
        [$start, $end] = ClubRounds::businessDay(now());

        return Interclub::query()
            ->where('start_date_time', '>=', $start)
            ->where('start_date_time', '<', $end)
            ->whereHas('visitedTeam.club', fn ($query) => $query->where('is_own_club', true))
            ->with(['visitedTeam.club', 'visitingTeam.club'])
            ->get()
            ->flatMap(fn ($interclub): array => [$interclub->visitedTeam?->fullName(), $interclub->visitingTeam?->fullName()])
            ->filter(fn (?string $name): bool => is_string($name) && $name !== '')
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
};
