<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Domains\Bar\Services\BarCartService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarCartController extends Controller
{
    private const ACTION_VALIDATE = 'validate';
    private const ACTION_PAY_NOW = 'pay_now';

    public function __construct(private readonly BarCartService $cartService)
    {
        $this->middleware('auth');
        $this->middleware('throttle:120,1')->only(['add', 'remove', 'validateOrder']);
    }

    public function add(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:bar_products,id'],
        ]);

        $result = $this->cartService->addProductToSessionCart((int) $validated['product_id']);

        return back()->with($result['status'], $result['message']);
    }

    public function remove(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:bar_products,id'],
        ]);

        $this->cartService->removeProductFromSessionCart((int) $validated['product_id']);

        return back();
    }

    public function show(): View
    {
        $data = $this->cartService->getCartViewData();

        return view('bar.carts.index', $data);
    }

    public function clear(): RedirectResponse
    {
        $this->cartService->clearSessionCart();

        return back()->with('success', 'Panier vidé avec succès.');
    }

    public function validateOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:' . self::ACTION_VALIDATE . ',' . self::ACTION_PAY_NOW],
        ]);

        $action = $validated['action'];

        try {
            $order = $this->cartService->checkoutFromSessionCart($action);
        } catch (\RuntimeException $e) {
            return redirect()->route('bar.carts.show')
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('bar.carts.show')
                ->with('error', 'Une erreur est survenue lors de la validation de la commande.');
        }

        if ($action === self::ACTION_PAY_NOW) {
            return redirect()->route('bar.payment.show', $order)
                ->with('success', 'Commande créée. Procédez au paiement.');
        }

        return redirect()->route('bar.orders.index')
            ->with('success', 'Commande validée');
    }
}
