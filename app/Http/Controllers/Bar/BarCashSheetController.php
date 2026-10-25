<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Domains\Bar\Services\CashSheetService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class BarCashSheetController extends Controller
{
    public function __construct(private readonly CashSheetService $cashSheetService)
    {
        $this->middleware('auth');
        $this->middleware('throttle:10,1')->only(['send']);
    }

    /**
     * Download the cash sheet as CSV for the selected date.
     */
    public function download(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date|before_or_equal:today',
        ]);

        $date = $validated['date'];
        [$summary, , $csv] = $this->cashSheetService->build($date);

        // buildCsv() always contains a CSV header, so test business data instead.
        if ((int) ($summary['orders_total'] ?? 0) === 0 && (int) ($summary['payments_total'] ?? 0) === 0) {
            return redirect()
                ->route('bar.cashSheet.index', ['date' => $date])
                ->with('error', 'Aucune donnée à télécharger pour cette date.');
        }

        // UTF-8 BOM improves compatibility when the CSV is opened in Excel.
        $csvWithBom = "\xEF\xBB\xBF" . $csv;
        $filename = "feuille-de-caisse-{$date}.csv";

        return response($csvWithBom, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date' => 'nullable|date|before_or_equal:today',
        ]);

        $date = Carbon::parse($validated['date'] ?? now()->toDateString())->toDateString();
        [$summary] = $this->cashSheetService->build($date);
        $activity = $this->cashSheetService->activity($date);

        return view('bar.cashSheet.index', [
            'date' => $date,
            'summary' => $summary,
            'activityDays' => $activity['days'],
            'previousActiveDay' => $activity['previous'],
            'nextActiveDay' => $activity['next'],
            'defaultTo' => $this->cashSheetService->getDefaultEmail(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date|before_or_equal:today',
            'to' => 'required|email',
            'save_default' => 'nullable|boolean',
        ]);

        $date = $validated['date'];
        $to = $validated['to'];
        $saveDefault = (bool) ($validated['save_default'] ?? false);

        [$summary, , $csv] = $this->cashSheetService->build($date);

        if ((int) ($summary['orders_total'] ?? 0) === 0 && (int) ($summary['payments_total'] ?? 0) === 0) {
            return back()->with('error', 'Aucune donnée à envoyer.');
        }

        $subject = "Feuille de caisse — {$date}";
        $body = "Bonjour,\n\nVeuillez trouver en pièce jointe la feuille de caisse du {$date}.\n\n";
        $body .= "Synthèse:\n";
        $body .= "- Commandes créées: {$summary['orders_total']} (payées {$summary['orders_paid']}, non payées {$summary['orders_unpaid']})\n";
        $body .= "- Règlements enregistrés: {$summary['payments_total']}\n";
        $body .= "- Articles vendus: {$summary['items_total']}\n";
        $body .= '- Total vendu: ' . euros((int) $summary['sold_total_cents']) . "\n";
        $body .= '- Total encaissé: ' . euros((int) $summary['received_total_cents']) . "\n";
        $body .= '- Total impayé: ' . euros((int) $summary['unpaid_total_cents']) . "\n";
        $body .= '- Cash: ' . euros((int) ($summary['by_method_cents']['cash'] ?? 0)) . "\n";
        $body .= '- QR: ' . euros((int) ($summary['by_method_cents']['qr'] ?? 0)) . "\n";
        $body .= '- Autre: ' . euros((int) ($summary['by_method_cents']['other'] ?? 0)) . "\n";
        $body .= '- Offert: ' . euros((int) ($summary['by_method_cents']['offered'] ?? 0)) . "\n\n";
        $body .= "Cordialement.\n";

        $ok = $this->cashSheetService->sendCsv($to, $subject, $body, $date, $csv);

        if ($ok && $saveDefault) {
            $this->cashSheetService->saveDefaultEmail($to);
        }

        return redirect()
            ->route('bar.cashSheet.index', ['date' => $date])
            ->with(
                $ok ? 'success' : 'error',
                $ok ? 'Email envoyé avec succès.' : 'Échec de l’envoi email.'
            );
    }
}
