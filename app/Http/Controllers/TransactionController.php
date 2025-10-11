<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class TransactionController extends Controller
{
    public function add(): View
    {
        return view('admin.transactions.upload');
    }

    public function index(Request $request)
    {
        $query = Transaction::query()->orderBy('date', 'desc');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('description', 'LIKE', "%{$search}%")
                    ->orWhere('counterparty_name', 'LIKE', "%{$search}%")
                    ->orWhere('counterparty_bank_account', 'LIKE', "%{$search}%");
            });
        }

        $transactions = $query->paginate(50);

        return view('admin.transactions.index', compact('transactions'));
    }

    public function markSubscriptionPaid(Payment $payment): void
    {
        $subscription = Subscription::find($payment->payable_id);
        $subscription->markAsPaid();
    }

    public function recalculateSubscriptionPaidAmount(Payment $payment)
    {
        $subscription = Subscription::find($payment->payable_id);
        $subscription->update([
            'amount_paid' => $payment->amount_paid,
        ]);
    }

    public function reconcile()
    {
        // Transactions non réconciliées = transactions qui n'ont pas de paiement lié
        $unreconciled_transactions = Transaction::whereDoesntHave('payment')
            ->where('amount', '>', 0) // Seulement les crédits
            ->orderBy('date', 'desc')
            ->get();

        $pending_payments = Payment::where('status', 'pending')
            ->whereNull('transaction_id')
            ->with('payable')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.transactions.reconcile', compact('unreconciled_transactions', 'pending_payments'));
    }

    public function reconcileStore(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'payment_id' => 'required|exists:payments,id',
        ]);

        $transaction = Transaction::findOrFail($request->transaction_id);
        $payment = Payment::findOrFail($request->payment_id);

        // Lier la transaction au paiement
        $payment->update([
            'transaction_id' => $transaction->id,
            'amount_paid' => (int) $transaction->amount,
            'status' => 'paid',
        ]);

        $this->recalculateSubscriptionPaidAmount($payment);
        $this->markSubscriptionPaid($payment);

        return redirect()->route('admin.transactions.reconcile')
            ->with('success', 'Paiement réconcilié avec succès !');
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'ods_file' => 'required|file|mimes:ods,xlsx,xls,csv,txt',
        ]);

        $path = $request->file('ods_file')->getRealPath();

        try {
            // PhpSpreadsheet détecte automatiquement le format (ODS, XLSX, CSV, etc.)
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            // Lire toutes les lignes
            $rows = $sheet->toArray(null, true, true, true);
            if (empty($rows)) {
                return back()->withErrors('Fichier vide ou invalide.');
            }

            // Première ligne = en-tête
            $headerRow = array_shift($rows);

            // Normaliser l'en-tête : trim + lowercase + supprimer accents
            $header = array_map(function ($h) {
                $h = strtolower(trim($h ?? ''));
                // Supprimer les accents
                $h = $this->removeAccents($h);

                return $h;
            }, $headerRow);

            Log::info('En-tête détectée (' . count($header) . ' colonnes) : ' . implode('|', $header));

            $importedCount = 0;
            $lineNumber = 1;

            foreach ($rows as $row) {
                $lineNumber++;

                // Nettoyer les valeurs
                $row = array_map(function ($v) {
                    if ($v === null) {
                        return null;
                    }
                    $v = trim($v);

                    return ($v === '') ? null : $v;
                }, $row);

                // S'assurer qu'on a le bon nombre de colonnes
                if (count($row) < count($header)) {
                    $row = array_pad($row, count($header), null);
                } elseif (count($row) > count($header)) {
                    $row = array_slice($row, 0, count($header));
                }

                $rowAssoc = array_combine($header, $row);

                if ($rowAssoc === false) {
                    Log::error("Impossible de combiner header et row pour la ligne {$lineNumber}");

                    continue;
                }

                Log::info('Ligne ' . $lineNumber . ' importée : ' . json_encode($rowAssoc));

                // Parser la date
                $transactionDate = $this->parseDate($rowAssoc['date'] ?? null, $lineNumber);

                // Parser le montant
                $amount = $this->parseAmount($rowAssoc['montant'] ?? null);

                // Créer la transaction
                try {
                    Transaction::create([
                        'date' => $transactionDate,
                        'description' => $rowAssoc['description'] ?? null,
                        'amount' => $amount,
                        'counterparty_name' => $rowAssoc['nom contrepartie'] ?? null,
                        'counterparty_bank_account' => $rowAssoc['numero de compte contrepartie'] ?? null,
                        'structured_reference' => $rowAssoc['communication structuree'] ?? null,
                        'free_reference' => $rowAssoc['communication libre'] ?? null,
                    ]);

                    $importedCount++;

                } catch (\Exception $e) {
                    Log::error("Erreur insertion ligne {$lineNumber} : " . $e->getMessage());
                    Log::error('Data : ' . json_encode([
                        'date' => $transactionDate,
                        'description' => $rowAssoc['description'] ?? null,
                        'amount' => $amount,
                    ]));
                }
            }

            return back()->with('success', "Fichier importé avec succès. {$importedCount} transactions importées.");

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'import : ' . $e->getMessage());

            return back()->withErrors('Erreur lors de la lecture du fichier : ' . $e->getMessage());
        }
    }

    /**
     * Parse un montant (gère virgules, points, etc.)
     */
    private function parseAmount($amountValue): float
    {
        if (empty($amountValue)) {
            return 0;
        }

        // Si c'est déjà un nombre
        if (is_numeric($amountValue)) {
            return (float) $amountValue;
        }

        // Nettoyer : supprimer espaces, remplacer virgule par point
        $cleaned = str_replace([' ', ','], ['', '.'], $amountValue);

        return (float) $cleaned;
    }

    /**
     * Parse une date dans différents formats
     */
    private function parseDate($dateValue, int $lineNumber): ?string
    {
        if (empty($dateValue)) {
            return null;
        }

        // Si c'est un nombre (date Excel/ODS sérialisée)
        if (is_numeric($dateValue)) {
            try {
                $date = ExcelDate::excelToDateTimeObject($dateValue);

                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                Log::warning("Date Excel invalide ligne {$lineNumber}: {$dateValue}");

                return null;
            }
        }

        // Si c'est une chaîne, essayer différents formats
        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateValue);
                if ($date) {
                    return $date->format('Y-m-d');
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        Log::warning("Date invalide ligne {$lineNumber}: {$dateValue}");

        return null;
    }

    /**
     * Supprime les accents d'une chaîne
     */
    private function removeAccents(string $str): string
    {
        $unwanted = [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
            'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ç' => 'c',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Á' => 'A',
            'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ú' => 'U',
            'Ô' => 'O', 'Ö' => 'O', 'Ó' => 'O',
            'Î' => 'I', 'Ï' => 'I', 'Í' => 'I',
            'Ç' => 'C',
        ];

        return str_replace(array_keys($unwanted), array_values($unwanted), $str);
    }
}
