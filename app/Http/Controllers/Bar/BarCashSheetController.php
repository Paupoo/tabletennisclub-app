<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Domains\Bar\Services\CashSheetService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BarCashSheetController extends Controller
{
    private CashSheetService $cashSheetService;

    public function __construct(CashSheetService $cashSheetService)
    {
        $this->middleware('auth');
        $this->cashSheetService = $cashSheetService;
    }

    public function index(Request $request)
    {
        // 1. Get selected date (default = today)
        $validated = $request->validate([
            'date' => 'nullable|date|before_or_equal:today',
        ]);
        $date = $validated['date'] ?? now()->toDateString();

        [$summary, $rows, $csv] = $this->cashSheetService->build($date);

        return view('bar.cashSheet.index', [
            'date' => $date,
            'summary' => $summary,
            'rows' => $rows,
            'defaultTo' => $this->cashSheetService->getDefaultEmail(),
        ]);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'to' => 'required|email',
            'save_default' => 'nullable|boolean',
        ]);

        $date = $validated['date'];
        $to = $validated['to'];
        $saveDefault = (bool) ($validated['save_default'] ?? false);

        if ($saveDefault) {
            $this->cashSheetService->saveDefaultEmail($to);
        }

        [$summary, $rows, $csv] = $this->cashSheetService->build($date);

        $subject = "Feuille de caisse — {$date}";
        $body = "Bonjour,\n\nVeuillez trouver en pièce jointe la feuille de caisse du {$date}.\n\n";
        $body .= "Synthèse:\n";
        $body .= "- Commandes: {$summary['orders_total']} (payées {$summary['orders_paid']}, non payées {$summary['orders_unpaid']})\n";
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

        // return redirect()
        //     ->route('bar.cashSheet.index', ['date' => $date])
        //     ->with(
        //         $ok ? 'success' : 'warning',
        //         $ok ? 'Email envoyé avec succès.' : 'Échec de l’envoi email.'
        //     );
    }
}
