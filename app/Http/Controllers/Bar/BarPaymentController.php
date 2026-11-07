<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Actions\Bar\RecordBarOrderPayment;
use App\Actions\Bar\SettleBarOrder;
use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Services\ClubRounds;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Permission;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BarPaymentController extends Controller
{
    public function pay(Request $request, BarOrder $order, SettleBarOrder $settle, ClubRounds $clubRounds): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::BarOrdersPay->value), 403);

        $validated = $request->validate([
            'method' => 'required|in:cash,offered,qr',
            'reason' => 'nullable|string|max:255',
            'manual_reason' => 'nullable|string|max:255',
        ]);

        if ($order->is_paid) {
            return back()->with('error', 'Commande déjà payée.');
        }

        $selectedReason = trim((string) ($validated['reason'] ?? ''));
        $manualReason = trim((string) ($validated['manual_reason'] ?? ''));
        $offeredReason = $manualReason !== '' ? $manualReason : $selectedReason;

        if ($validated['method'] === 'offered') {
            $refusal = $clubRounds->refusal(
                (int) $order->items()->sum('quantity'),
                $offeredReason,
                $manualReason !== '',
                Carbon::parse($order->created_at),
                now(),
            );

            if ($refusal !== null) {
                return back()->with('error', $refusal)->withInput();
            }
        }

        if (! $settle($order, $validated['method'], $offeredReason)) {
            return back()->with('error', 'Commande déjà payée.');
        }

        return redirect()
            ->route('bar.orders.index')
            ->with('success', 'Paiement enregistré.');
    }

    public function show(Request $request, BarOrder $order, GeneratePaymentQR $generatePaymentQR, RecordBarOrderPayment $recordPayment, ClubRounds $clubRounds): Response
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

        // Les équipes à qui ce verre peut encore aller, chacune sous son plafond.
        $articles = (int) $order->items->sum('quantity');
        $visitingTeams = $clubRounds->teamsFor(Carbon::parse($order->created_at), now())
            ->filter(fn (array $team): bool => $team['served_at'] === '')
            ->values();
        $offeredOrderLimitReached = $articles > ClubRounds::DEFAULT_CAP;

        // Get selected payment method
        $method = $request->input('method');
        $qrCode = null;
        $payment = null;
        $ownClub = null;
        if ($method === 'qr') {
            // Pour le virement manuel : certaines applis bancaires ne lisent pas
            // l'EPC, et le client recopie alors ce que l'écran lui montre.
            // `$ownClub` et non `$club` : la vue boucle déjà sur les clubs visiteurs
            // sous ce nom-là, et la boucle l'écrasait.
            $ownClub = Club::ourClub()->first();
            $payment = $recordPayment->reserve($order);

            if ($payment === null) {
                return redirect()
                    ->route('bar.orders.index')
                    ->with('error', 'Commande déjà payée.');
            }

            $qrCode = $generatePaymentQR($payment);
        }

        return response()
            ->view('bar.payments.index', compact(
                'order',
                'method',
                'qrCode',
                'payment',
                'ownClub',
                'visitingTeams',
                'articles',
                'offeredOrderLimitReached',
            ))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
