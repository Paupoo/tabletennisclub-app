<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Actions\Bar\RecordBarOrderPayment;
use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Shared\Enums\Permission;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Symfony\Component\HttpFoundation\Response;

class BarPaymentController extends Controller
{
    public function pay(Request $request, BarOrder $order, RecordBarOrderPayment $recordPayment): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::BarOrdersPay->value), 403);

        $validated = $request->validate([
            'method' => 'required|in:cash,offered,qr',
            'reason' => 'nullable|string|max:255',
            'manual_reason' => 'nullable|string|max:255',
        ]);

        $selectedReason = trim((string) ($validated['reason'] ?? ''));
        $manualReason = trim((string) ($validated['manual_reason'] ?? ''));
        $offeredReason = $manualReason !== '' ? $manualReason : $selectedReason;

        if ($validated['method'] === 'offered' && $offeredReason === '') {
            return back()
                ->with('error', 'Veuillez fournir une raison pour un paiement offert.')
                ->withinput();
        }

        if ($validated['method'] === 'offered') {
            $visitingClubs = $this->visitingClubsForOrder($order);
            $clubName = $selectedReason;

            if ($manualReason === '' && ! $visitingClubs->contains('name', $clubName)) {
                return back()
                    ->with('error', 'Sélectionnez un club visiteur prévu aujourd’hui.')
                    ->withInput();
            }

            if ((int) $order->items()->sum('quantity') > 4) {
                return back()
                    ->with('error', 'Une commande offerte ne peut pas contenir plus de 4 articles.')
                    ->withInput();
            }

            [$businessDayStart, $businessDayEnd] = $this->businessDayRangeForPayment();

            $usedClubReasons = BarOrder::query()
                ->where('payment_method', 'offered')
                ->whereNotNull('paid_at')
                ->where('paid_at', '>=', $businessDayStart)
                ->where('paid_at', '<', $businessDayEnd)
                ->pluck('reason')
                ->filter()
                ->map(fn (string $reason): string => BarOrder::normaliseName($reason));

            if ($manualReason === '' && $usedClubReasons->contains(BarOrder::normaliseName($offeredReason))) {
                return back()
                    ->with('error', 'Ce club visiteur a déjà reçu une commande offerte aujourd’hui.')
                    ->withInput();
            }
        }

        if ($order->is_paid) {
            return back()->with('error', 'Commande déjà payée.');
        }

        $order->update([
            'is_paid' => 1,
            'paid_at' => now(),
            'payment_method' => $validated['method'],
            'reason' => $validated['method'] === 'offered' ? $offeredReason : null,
            // L'ardoise est réglée : son nom se libère pour la prochaine tournée, sans
            // que l'historique le perde — `name` reste, seule la clé d'unicité part.
            'open_name_key' => null,
        ]);

        // Le QR a une contrepartie sur le compte du club : la commande entre dans
        // la liste à rapprocher du trésorier, en attente jusqu'à ce que le
        // virement apparaisse sur le relevé. Le cash et l'offert n'y vont pas.
        $recordPayment($order);

        return redirect()
            ->route('bar.orders.index')
            ->with('success', 'Paiement enregistré.');
    }

    public function show(Request $request, BarOrder $order, GeneratePaymentQR $generatePaymentQR): Response
    {
        // `bar.orders.takeover` : un bar tourne en équipe, et celui qui encaisse n'est
        // presque jamais celui qui a servi. La permission existait, elle est accordée
        // au rôle BARMAN, et personne ne la vérifiait.
        if ((int) $order->created_by !== (int) auth()->id()
            && $request->user()?->can(Permission::BarOrdersTakeover->value) !== true) {
            abort(403);
        }

        if ($order->is_paid) {
            return redirect()
                ->route('bar.orders.index')
                ->with('error', 'Commande déjà payée.');
        }

        // load items + product for display
        $order->load('items.product');

        $visitingClubs = $this->availableVisitingClubsForOrder($order);
        $offeredOrderLimitReached = (int) $order->items->sum('quantity') > 4;

        // Get selected payment method
        $method = $request->input('method');
        $qrCode = null;
        if ($method === 'qr') {
            $payment = new Payment([
                'amount_due' => $order->total_price / 100,
                'reference' => "Bar order #{$order->id}",
            ]);
            $qrCode = $generatePaymentQR($payment);
        }

        return response()
            ->view('bar.payments.index', compact(
                'order',
                'method',
                'qrCode',
                'visitingClubs',
                'offeredOrderLimitReached',
            ))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Hide clubs that already received an offered order during this business day.
     *
     * The server-side check in pay() remains authoritative for concurrent pages;
     * this filtering prevents the normal UI path from offering an invalid choice.
     *
     * @return SupportCollection<int, object{name: string}&\stdClass>
     */
    private function availableVisitingClubsForOrder(BarOrder $order): SupportCollection
    {
        $clubs = $this->visitingClubsForOrder($order);
        [$businessDayStart, $businessDayEnd] = $this->businessDayRangeForPayment();

        $usedClubReasons = BarOrder::query()
            ->where('payment_method', 'offered')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $businessDayStart)
            ->where('paid_at', '<', $businessDayEnd)
            ->pluck('reason')
            ->filter()
            ->map(fn (string $reason): string => BarOrder::normaliseName($reason));

        return $clubs
            ->reject(fn (object $club): bool => $usedClubReasons->contains(BarOrder::normaliseName($club->name)))
            ->values();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function businessDayRange(Carbon $date): array
    {
        $start = $date->copy()->startOfDay()->setTime(6, 0);

        if ($date->lt($start)) {
            $start->subDay();
        }

        return [$start, $start->copy()->addDay()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function businessDayRangeForOrder(BarOrder $order): array
    {
        return $this->businessDayRange(Carbon::parse($order->created_at));
    }

    /**
     * The once-per-club rule applies to the day the free order is closed.
     *
     * An open order can be created before the match day and paid later, so its
     * creation date is not the relevant business day for this rule.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function businessDayRangeForPayment(): array
    {
        return $this->businessDayRange(now());
    }

    /**
     * @return SupportCollection<int, object{name: string}&\stdClass>
     */
    private function visitingClubsForOrder(BarOrder $order): SupportCollection
    {
        [$businessDayStart, $businessDayEnd] = $this->businessDayRangeForOrder($order);

        return Interclub::query()
            ->where('start_date_time', '>=', $businessDayStart)
            ->where('start_date_time', '<', $businessDayEnd)
            ->whereHas('visitedTeam.club', fn ($query) => $query->where('is_own_club', true))
            ->whereHas('visitingTeam.club', fn ($query) => $query->where('is_own_club', false))
            ->with('visitingTeam.club')
            ->get()
            ->map(fn (Interclub $interclub): ?object => $interclub->visitingTeam?->club === null
                ? null
                : (object) ['name' => $interclub->visitingTeam->club->name])
            ->filter()
            ->unique('name')
            ->sortBy('name')
            ->values();
    }
}
