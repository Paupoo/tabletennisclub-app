<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarOrderItem;
use App\Domains\Bar\Services\StockService;
use App\Domains\Shared\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Support\LocaleSort;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BarOrderController extends Controller
{
    private const int BAR_DAY_START_HOUR = 6;

    public function __construct(private readonly StockService $stockService)
    {
        $this->middleware('auth');
    }

    public function destroy(BarOrder $order): RedirectResponse
    {
        // La suppression reste à son auteur, elle : elle est irréversible — elle
        // restitue le stock et détruit les lignes — et `bar.orders.manage` la garde
        // déjà côté route. La reprise sert à encaisser et à compléter, pas à effacer
        // le travail d'un autre.
        if ((int) $order->created_by !== (int) auth()->id()) {
            return back()->with('error', "Vous n'êtes pas autorisé à supprimer cette commande.");
        }

        if ($order->is_paid) {
            return back()->with('error', 'Impossible de supprimer une commande payée.');
        }

        DB::transaction(function () use ($order): void {
            $order->load('items');
            foreach ($order->items as $item) {
                $this->stockService->restoreFromOrderItem(
                    (int) $item->id,
                    auth()->id()
                );
            }
            $order->items()->delete();
            $order->delete();
        });

        return redirect()->route('bar.orders.index')
            ->with('success', 'Commande supprimée.');
    }

    /**
     * Order history with filters and KPIs.
     */
    public function history(Request $request): View
    {
        $period = $request->input('period', 'today');
        $status = $request->input('status', 'all');

        $query = BarOrder::query()->with(['items.product', 'createdBy']);
        $periodRange = null;

        switch ($period) {
            case 'today':
                $periodRange = $this->barDayRange();
                break;
            case '7':
                [$periodStart, $periodEnd] = $this->barDayRange();
                $periodRange = [$periodStart->subDays(6), $periodEnd];
                break;
            case '30':
                [$periodStart, $periodEnd] = $this->barDayRange();
                $periodRange = [$periodStart->subDays(29), $periodEnd];
                break;
            case 'all':
            default:
                // no filter
                break;
        }

        if ($periodRange !== null) {
            [$periodStart, $periodEnd] = $periodRange;

            $query->where(function (Builder $query) use ($periodStart, $periodEnd): void {
                $query
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->orWhereBetween('paid_at', [$periodStart, $periodEnd]);
            });
        }

        switch ($status) {
            case 'paid':
                $query->where('is_paid', 1);
                break;
            case 'unpaid':
                $query->where('is_paid', 0);
                break;
            case 'all':
            default:
                // no filter
                break;
        }

        $orders = $query
            ->orderByDesc('id')
            ->get();

        $paidOrders = $orders->filter(function (BarOrder $order) use ($periodRange): bool {
            if (! $order->is_paid || $order->paid_at === null) {
                return false;
            }

            if ($periodRange === null) {
                return true;
            }

            [$periodStart, $periodEnd] = $periodRange;

            return Carbon::parse($order->paid_at)->betweenIncluded($periodStart, $periodEnd);
        });

        $totalRevenue = $paidOrders
            ->where('payment_method', '!=', 'offered')
            ->sum('total_price');
        $totalRevenueUnpaid = $orders->where('is_paid', 0)->sum('total_price');
        $totalRevenueOffered = $paidOrders->where('payment_method', 'offered')->sum('total_price');
        $orderCount = $orders->count();

        return view('bar.orders.history', [
            'orders' => $orders,
            'period' => $period,
            'status' => $status,
            'totalRevenue' => $totalRevenue,
            'totalRevenueUnpaid' => $totalRevenueUnpaid,
            'totalRevenueOffered' => $totalRevenueOffered,
            'orderCount' => $orderCount,
            'periodLabels' => [
                'today' => "Aujourd'hui",
                '7' => '7 jours',
                '30' => '30 jours',
                'all' => 'Tout',
            ],
            'statusLabels' => [
                'all' => 'Tous',
                'paid' => 'Payés',
                'unpaid' => 'Non payés',
            ],
        ]);
    }

    /**
     * List open (unpaid) orders.
     */
    public function index(): View
    {
        // Triée par nom, et non par numéro : celui qui vient régler dit « c'est pour
        // Alpa A », jamais « c'est la 47 ». L'ordre chronologique n'aide à rien pour
        // retrouver une ardoise — c'est un ordre que le client ne connaît pas.
        //
        // Par LocaleSort et non en SQL : un tri octet par octet classerait « Vétérans »
        // après « Zoé ».
        $orders = LocaleSort::by(
            BarOrder::with('items.product', 'createdBy')
                ->where('is_paid', 0)
                ->get(),
            fn (BarOrder $order): string => (string) ($order->name ?? '')
        );

        return view('bar.orders.index', compact('orders'));
    }

    /**
     * Re-open an unpaid order for modification:
     * - load order items into the session cart
     * - remember which order is being edited
     * - redirect back to the main menu page
     */
    public function modify(BarOrder $order): RedirectResponse
    {
        // `bar.orders.takeover` : un bar tourne en équipe, et celui qui encaisse n'est
        // presque jamais celui qui a servi. La permission existait, elle est accordée
        // au rôle BARMAN, et personne ne la vérifiait.
        if ((int) $order->created_by !== (int) auth()->id()
            && auth()->user()?->can(Permission::BarOrdersTakeover->value) !== true) {
            return back()->with('error', "Vous n'êtes pas autorisé à modifier cette commande.");
        }

        if ($order->is_paid) {
            return back()->with('error', 'Commande déjà payée.');
        }

        $order->load('items');

        $cart = $order->items
            ->mapWithKeys(fn (BarOrderItem $item): array => [$item->product_id => (int) $item->quantity])
            ->toArray();

        session()->put('cart', $cart);
        session()->put('editing_order_id', $order->id);
        session()->put('bar_tab_name', $order->name);

        return redirect()->route('bar.index')
            ->with('success', 'Commande chargée dans le panier pour modification.');
    }

    /**
     * Renommer une ardoise.
     *
     * Attendu rare — on nomme à l'ouverture — mais pas impossible : une faute de
     * frappe, ou un nom donné avant de savoir qui c'était vraiment. Passe par la même
     * normalisation et la même règle d'unicité que l'ouverture, sinon le renommage
     * serait la porte dérobée par laquelle deux homonymes rentrent.
     */
    public function rename(Request $request, BarOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
        ]);

        if ($order->is_paid) {
            return back()->with('error', 'Une commande réglée ne se renomme plus.');
        }

        $key = BarOrder::normaliseName($validated['name']);

        if ($key === '') {
            return back()->with('error', 'Donnez un nom à cette ardoise.');
        }

        $clash = BarOrder::openTabNamed($validated['name']);

        if ($clash instanceof BarOrder && $clash->id !== $order->id) {
            return back()->with('error', sprintf(
                'Une ardoise « %s » est déjà ouverte. Encaissez-la ou choisissez un autre nom.',
                $clash->name
            ));
        }

        $order->update(['name' => trim($validated['name']), 'open_name_key' => $key]);

        return back()->with('success', sprintf('Ardoise renommée « %s ».', $order->name));
    }

    /**
     * Return the current bar business day, from 06:00 to 05:59 the next day.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function barDayRange(): array
    {
        $start = now()->startOfDay()->setTime(self::BAR_DAY_START_HOUR, 0);

        if (now()->lt($start)) {
            $start->subDay();
        }

        return [$start, $start->copy()->addDay()];
    }
}
