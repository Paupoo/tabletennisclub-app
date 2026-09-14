<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Domains\Bar\Services\CashSheetService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarCashSheetController extends Controller
{
    public function __construct(private readonly CashSheetService $cashSheetService)
    {
        $this->middleware('auth');
        $this->middleware('throttle:10,1')->only(['send']);
    }

    public function index(Request $request): View
    {
        // 1. Get selected date (default = today)
        $validated = $request->validate([
            'date' => 'nullable|date|before_or_equal:today',
        ]);
        $date = $validated['date'] ?? now()->toDateString();

        [$summary] = $this->cashSheetService->build($date);

        return view('bar.cashSheet.index', [
            'date' => $date,
            'summary' => $summary,
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

        if ($saveDefault) {
            $this->cashSheetService->saveDefaultEmail($to);
        }

        [$summary, , $csv] = $this->cashSheetService->build($date);

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

        // Le garde portait sur le CSV, qui n'est jamais vide : buildCsv() écrit
        // toujours sa ligne d'en-tête. Une journée sans vente partait donc au
        // trésorier sous la forme d'un fichier à trois colonnes et zéro ligne,
        // annoncé « Email envoyé avec succès ». C'est le nombre de commandes qui
        // dit s'il y a quelque chose à envoyer.
        if ((int) $summary['orders_total'] === 0) {
            return back()->with('error', 'Aucune donnée à envoyer.');
        }

        $ok = $this->cashSheetService->sendCsv($to, $subject, $body, $date, $csv);

        return redirect()
            ->route('bar.cashSheet.index', ['date' => $date])
            ->with(
                $ok ? 'success' : 'error',
                $ok ? 'Email envoyé avec succès.' : 'Échec de l’envoi email.'
            );
    }
}
