<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class TreasurySeeder extends Seeder
{
    /** @var array<class-string, list<string>> */
    private array $morphWith = [
        Subscription::class => ['user'],
        TournamentRegistration::class => ['user'],
        MeetingUser::class => ['user'],
    ];

    public function run(): void
    {
        $treasurer = User::where('email', 'gilles.herpigny@test.com')->firstOrFail();
        $season = Season::find(1) ?? Season::factory()->create();

        $cashRegister = CashRegister::create([
            'name' => 'Caisse du club',
            'balance' => 15000,
            'notes' => 'Caisse principale saison 2024-2025.',
        ]);

        CashRegisterEntry::create([
            'cash_register_id' => $cashRegister->id,
            'amount' => 15000,
            'reason' => "Solde d'ouverture — report saison 2024-2025",
            'recorded_by_id' => $treasurer->id,
        ]);

        $this->seedSubscriptionPayments($season, $treasurer, $cashRegister);
        $this->seedTournamentPayments($treasurer, $cashRegister);
        $this->seedMeetingPayments($treasurer, $cashRegister);
        $this->seedOrphanedAndOutgoingTransactions();
        $this->seedMiscCashEntries($cashRegister, $treasurer);

        $inCsvRefunds = $this->seedRefunds();
        $this->writeBankImportCsv($inCsvRefunds);
    }

    private function fakeBelgianIban(): string
    {
        $check = str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT);
        $account = str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);

        return 'BE' . $check . $account;
    }

    private function generateRef(): string
    {
        return (new GeneratePaymentReference)();
    }

    /**
     * committee1 meeting (has_meal + meal_price_cents = 1200):
     *   all attendees → meal_reserved = true
     *   n-1 → cash paid + cash register entry
     *   last → forgotten (pending)
     */
    private function seedMeetingPayments(User $treasurer, CashRegister $cashRegister): void
    {
        $meeting = Meeting::where('has_meal', true)->where('meal_price_cents', '>', 0)->first();

        if (! $meeting) {
            return;
        }

        $mealPriceEuros = (int) round($meeting->meal_price_cents / 100);

        $attendees = $meeting->users()->get();
        $lastIndex = $attendees->count() - 1;

        foreach ($attendees as $i => $user) {
            $meeting->users()->updateExistingPivot($user->id, [
                'meal_reserved' => true,
                'meal_responded_at' => $meeting->scheduled_at?->subDays(5),
            ]);

            $meetingUser = $user->registration;
            $ref = $this->generateRef();

            if ($i < $lastIndex) {
                // Cash paid on the meeting day
                $meetingUser->payment()->create([
                    'reference' => $ref,
                    'amount_due' => $mealPriceEuros,
                    'amount_paid' => $mealPriceEuros,
                    'status' => 'paid',
                    'payment_method' => 'Cash',
                ]);

                CashRegisterEntry::create([
                    'cash_register_id' => $cashRegister->id,
                    'amount' => $meeting->meal_price_cents,
                    'reason' => 'Repas réunion comité — ' . $user->full_name,
                    'payable_type' => $meetingUser->getMorphClass(),
                    'payable_id' => $meetingUser->id,
                    'recorded_by_id' => $treasurer->id,
                ]);
            } else {
                // Last attendee forgot to pay
                $meetingUser->payment()->create([
                    'reference' => $ref,
                    'amount_due' => $mealPriceEuros,
                    'amount_paid' => 0,
                    'status' => 'pending',
                    'payment_method' => 'Wire',
                ]);
            }
        }
    }

    /**
     * Miscellaneous cash entries not linked to a specific payable.
     */
    private function seedMiscCashEntries(CashRegister $cashRegister, User $treasurer): void
    {
        foreach ([
            [500,   'Vente de t-shirts du club (5 × 10 €)'],
            [200,   'Tombola — fête annuelle du club'],
            [-2500, 'Achat raquette démo pour prêt aux membres'],
            [-800,  'Impression affiches tournoi interne'],
            [-350,  'Achat sangles de filets de rechange'],
        ] as [$amount, $reason]) {
            CashRegisterEntry::create([
                'cash_register_id' => $cashRegister->id,
                'amount' => $amount,
                'reason' => $reason,
                'recorded_by_id' => $treasurer->id,
            ]);
        }
    }

    /**
     * 3 orphaned incoming transactions (unknown source) + 3 outgoing club expenses.
     */
    private function seedOrphanedAndOutgoingTransactions(): void
    {
        $base = Carbon::parse('2024-10-01');

        foreach ([
            ['Remboursement AFTT — licence excédentaire', 'AFTT Province BW', 45.00,  0],
            ['Don anonyme — kermesse de la commune',       null,               100.00, 15],
            ['Vente matériel usagé (raquettes)',           'Achat privé',      30.00,  30],
        ] as [$desc, $counterparty, $amount, $offset]) {
            Transaction::create([
                'date' => $base->copy()->addDays($offset)->toDateString(),
                'description' => $desc,
                'amount' => $amount,
                'counterparty_name' => $counterparty,
                'free_reference' => 'À rapprocher',
            ]);
        }

        foreach ([
            ['Achat balles ITTF (×144)',                  'Tibhar Belgium',        -89.40,  7],
            ["Frais d'arbitrage — ronde 3 interclubs",    'AFTT Province BW',      -25.00, 19],
            ['Réparation filet table n°3',                'Sport Réparation SA',   -18.50, 31],
        ] as [$desc, $counterparty, $amount, $offset]) {
            Transaction::create([
                'date' => $base->copy()->addDays($offset)->toDateString(),
                'description' => $desc,
                'amount' => $amount,
                'counterparty_name' => $counterparty,
            ]);
        }
    }

    /**
     * 8 refund scenarios seeded across 3 groups:
     *   3 × to_refund — pending (club has not sent the money yet)
     *   3 × to_refund — club already wired the money; outgoing appears only in the CSV stub
     *   2 × refunded  — outgoing Transaction already in DB, auto-matched
     *
     * Returns the 3 "in CSV" to_refund payments so writeBankImportCsv() can generate the
     * matching outgoing rows in the import stub.
     */
    private function seedRefunds(): Collection
    {
        $paidPayments = Payment::where('status', 'paid')
            ->orderBy('id')
            ->with(['payable' => fn (MorphTo $m) => $m->morphWith($this->morphWith)])
            ->get()
            ->filter(fn (Payment $p) => $p->payable?->user !== null)
            ->values()
            ->take(8);

        // Ensure every refund user has an IBAN (required for auto-match)
        foreach ($paidPayments as $p) {
            if (! $p->payable->user->iban) {
                $p->payable->user->update(['iban' => $this->fakeBelgianIban()]);
            }
        }

        $reasons = [
            'Désistement tournoi — remboursement inscription',
            'Trop-perçu cotisation — remboursement solde',
            'Annulation repas réunion comité — remboursement',
            'Remboursement double paiement',
            'Remboursement erreur de montant',
            'Remboursement suite annulation événement',
            null, // reconciled — 1
            null, // reconciled — 2
        ];

        // 3 pending to_refund
        foreach ($paidPayments->take(3) as $i => $p) {
            $p->update(['status' => 'to_refund']);
        }

        // 3 "in CSV" to_refund (outgoing will appear only in the CSV file)
        $inCsvRefunds = $paidPayments->slice(3, 3)->values();
        foreach ($inCsvRefunds as $p) {
            $p->update(['status' => 'to_refund']);
        }

        // 2 already reconciled refunds — outgoing Transaction in DB + linked
        foreach ($paidPayments->slice(6, 2) as $p) {
            $user = $p->payable->user;
            $refundTx = Transaction::create([
                'date' => Carbon::now()->subDays(random_int(2, 14))->toDateString(),
                'description' => 'Remboursement — ' . $user->full_name,
                'amount' => -(float) $p->amount_paid,
                'counterparty_name' => $user->full_name,
                'counterparty_bank_account' => $user->iban,
            ]);
            $p->update(['status' => 'refunded', 'refund_transaction_id' => $refundTx->id]);
        }

        // Return with freshly loaded user IBANs
        return $inCsvRefunds->map(
            fn (Payment $p) => $p->refresh()->load(['payable' => fn (MorphTo $m) => $m->morphWith($this->morphWith)])
        );
    }

    /**
     * 22 subscriptions with varied payment statuses:
     *   0-7   → wire paid + reconciled bank transaction  (8 reconciled)
     *   8-9   → cash paid + cash register entry          (2 cash)
     *  10-13  → pending + unlinked bank transaction      (4 manual-match)
     *  14-15  → pending + wrong-amount linked transaction (2 wrong amount)
     *  16-21  → forgotten / no transaction               (6 pending)
     */
    private function seedSubscriptionPayments(Season $season, User $treasurer, CashRegister $cashRegister): void
    {
        $fee = 120; // €120
        $users = User::inRandomOrder()->take(22)->get();

        foreach ($users as $i => $user) {
            $ref = $this->generateRef();
            $date = Carbon::parse('2024-09-01')->addDays($i * 2);

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'season_id' => $season->id,
                'status' => 'pending',
                'is_competitive' => ! empty($user->licence),
                'has_other_family_members' => false,
                'trainings_count' => 0,
                'subscription_price' => $fee,
                'training_unit_price' => 0,
                'amount_due' => $fee,
                'amount_paid' => 0,
            ]);

            if ($i < 8) {
                // Wire paid — reconciled
                $transaction = Transaction::create([
                    'date' => $date->toDateString(),
                    'description' => 'Cotisation TT 2024-2025 — ' . $user->full_name,
                    'amount' => (float) $fee,
                    'counterparty_name' => $user->full_name,
                    'counterparty_bank_account' => $this->fakeBelgianIban(),
                    'structured_reference' => $ref,
                ]);

                $subscription->payments()->create([
                    'reference' => $ref,
                    'amount_due' => $fee,
                    'amount_paid' => $fee,
                    'status' => 'paid',
                    'payment_method' => 'Wire',
                    'transaction_id' => $transaction->id,
                ]);

                $subscription->update(['status' => 'paid', 'amount_paid' => $fee]);

            } elseif ($i < 10) {
                // Cash paid
                $subscription->payments()->create([
                    'reference' => $ref,
                    'amount_due' => $fee,
                    'amount_paid' => $fee,
                    'status' => 'paid',
                    'payment_method' => 'Cash',
                ]);

                CashRegisterEntry::create([
                    'cash_register_id' => $cashRegister->id,
                    'amount' => $fee * 100,
                    'reason' => 'Cotisation 2024-2025 — ' . $user->full_name,
                    'payable_type' => Subscription::class,
                    'payable_id' => $subscription->id,
                    'recorded_by_id' => $treasurer->id,
                ]);

                $subscription->update(['status' => 'paid', 'amount_paid' => $fee]);

            } elseif ($i < 14) {
                // Manual-match: payment pending, transaction exists with matching ref but NOT linked
                Transaction::create([
                    'date' => $date->toDateString(),
                    'description' => 'Cotisation TT — ' . $user->full_name,
                    'amount' => (float) $fee,
                    'counterparty_name' => $user->full_name,
                    'counterparty_bank_account' => $this->fakeBelgianIban(),
                    'structured_reference' => $ref,
                ]);

                $subscription->payments()->create([
                    'reference' => $ref,
                    'amount_due' => $fee,
                    'amount_paid' => 0,
                    'status' => 'pending',
                    'payment_method' => 'Wire',
                ]);

            } elseif ($i < 16) {
                // Wrong amount: linked transaction but paid less than owed
                $paidAmount = $fee - 20; // member paid €100 instead of €120

                $transaction = Transaction::create([
                    'date' => $date->toDateString(),
                    'description' => 'Cotisation TT — ' . $user->full_name,
                    'amount' => (float) $paidAmount,
                    'counterparty_name' => $user->full_name,
                    'counterparty_bank_account' => $this->fakeBelgianIban(),
                    'structured_reference' => $ref,
                ]);

                $subscription->payments()->create([
                    'reference' => $ref,
                    'amount_due' => $fee,
                    'amount_paid' => $paidAmount,
                    'status' => 'pending',
                    'payment_method' => 'Wire',
                    'transaction_id' => $transaction->id,
                ]);

                $subscription->update(['amount_paid' => $paidAmount]);

            } else {
                // Forgotten — pending, no bank transaction
                $subscription->payments()->create([
                    'reference' => $ref,
                    'amount_due' => $fee,
                    'amount_paid' => 0,
                    'status' => 'pending',
                    'payment_method' => 'Wire',
                ]);
            }
        }
    }

    /**
     * For each confirmed registration in paid tournaments:
     *   first registration → wire reconciled (1 bank transaction per tournament)
     *   remaining 70% paid → cash + cash register entry
     *   30% → pending
     */
    private function seedTournamentPayments(User $treasurer, CashRegister $cashRegister): void
    {
        $paidTournaments = Tournament::where('price', '>', 0)->get();

        foreach ($paidTournaments as $tournament) {
            $amountDue = (int) $tournament->price; // e.g. 10 (€10)

            $registrations = TournamentRegistration::where('tournament_id', $tournament->id)
                ->where('registration_status', 'confirmed')
                ->with('user')
                ->get();

            $total = $registrations->count();
            $paidCount = (int) round($total * 0.7);

            foreach ($registrations as $i => $registration) {
                $ref = $this->generateRef();
                $startDate = $tournament->start_date ?? now();
                $date = Carbon::parse($startDate)->subDays(random_int(3, 21));

                if ($i >= $paidCount) {
                    // 30% pending
                    $registration->payment()->create([
                        'reference' => $ref,
                        'amount_due' => $amountDue,
                        'amount_paid' => 0,
                        'status' => 'pending',
                        'payment_method' => 'Wire',
                    ]);

                    continue;
                }

                if ($i === 0) {
                    // First paid slot → wire reconciled (one per tournament)
                    $transaction = Transaction::create([
                        'date' => $date->toDateString(),
                        'description' => 'Inscr. tournoi — ' . $tournament->name,
                        'amount' => (float) $amountDue,
                        'counterparty_name' => $registration->user?->full_name,
                        'counterparty_bank_account' => $this->fakeBelgianIban(),
                        'structured_reference' => $ref,
                    ]);

                    $payment = $registration->payment()->create([
                        'reference' => $ref,
                        'amount_due' => $amountDue,
                        'amount_paid' => $amountDue,
                        'status' => 'paid',
                        'payment_method' => 'Wire',
                        'transaction_id' => $transaction->id,
                    ]);
                } else {
                    // Rest → cash on tournament day
                    $payment = $registration->payment()->create([
                        'reference' => $ref,
                        'amount_due' => $amountDue,
                        'amount_paid' => $amountDue,
                        'status' => 'paid',
                        'payment_method' => 'Cash',
                    ]);

                    CashRegisterEntry::create([
                        'cash_register_id' => $cashRegister->id,
                        'amount' => $amountDue * 100,
                        'reason' => 'Tournoi — ' . $tournament->name . ' — ' . ($registration->user?->full_name ?? ''),
                        'payable_type' => TournamentRegistration::class,
                        'payable_id' => $registration->id,
                        'recorded_by_id' => $treasurer->id,
                    ]);
                }

                $registration->update(['has_paid' => true, 'payment_id' => $payment->id]);
            }
        }
    }

    /**
     * Generates storage/app/seeders/bank_import_demo_2026-05.csv (Latin-1, semicolon)
     *
     * ~30 rows:
     *   15 — perfect pending payments: exact structured ref + exact amount
     *    3 — free-text only: right amount, no structured ref (member name as free comm.)
     *    2 — wrong amount: right structured ref, member paid €5 less
     *    5 — third-party: commune subsidy, sponsor, federation costs, supplier, fine
     *    3 — outgoing refunds matching to_refund payments (auto-matchable by IBAN + amount)
     *    2 — orphaned outgoing (no matching to_refund in DB)
     */
    private function writeBankImportCsv(Collection $inCsvRefunds): void
    {
        // Pending payments with no linked incoming transaction and a resolvable user
        $available = Payment::where('status', 'pending')
            ->whereNull('transaction_id')
            ->with(['payable' => fn (MorphTo $m) => $m->morphWith($this->morphWith)])
            ->get()
            ->filter(fn (Payment $p) => $p->payable?->user !== null)
            ->values();

        $perfectSource = $available->take(15);       // rows 1-15: perfect match
        $freeTextSource = $available->slice(15, 3);   // rows 16-18: free text only
        $wrongAmtSource = $available->slice(18, 2);   // rows 19-20: wrong amount

        $rows = [];
        $baseDate = Carbon::parse('2026-05-02');
        $dayOffset = 0;
        $txnSeq = 1;
        $internalRef = function () use (&$txnSeq): string {
            return sprintf('%03d/0526/%05d', random_int(10, 999), $txnSeq++);
        };

        // ── 15 perfect pending payments ───────────────────────────────────────
        foreach ($perfectSource as $p) {
            $rows[] = [
                'date' => $baseDate->copy()->addDays($dayOffset)->format('d/m/Y'),
                'description' => $internalRef(),
                'amount' => $p->amount_due,
                'counterparty' => $p->payable->user->full_name,
                'counterparty_ac' => $this->fakeBelgianIban(),
                'structured_ref' => $p->reference,
                'free_ref' => '',
            ];
            if ((++$dayOffset % 4) === 0) {
                $dayOffset++; // skip a day occasionally for realism
            }
        }

        // ── 3 free-text incorrect (right amount, no structured ref) ───────────
        foreach ($freeTextSource as $p) {
            $rows[] = [
                'date' => $baseDate->copy()->addDays($dayOffset++)->format('d/m/Y'),
                'description' => $internalRef(),
                'amount' => $p->amount_due,
                'counterparty' => $p->payable->user->full_name,
                'counterparty_ac' => $this->fakeBelgianIban(),
                'structured_ref' => '',
                'free_ref' => $p->payable->user->full_name . ' — cotisation club',
            ];
        }

        // ── 2 wrong-amount incorrect (right ref, paid €5 short) ──────────────
        foreach ($wrongAmtSource as $p) {
            $rows[] = [
                'date' => $baseDate->copy()->addDays($dayOffset++)->format('d/m/Y'),
                'description' => $internalRef(),
                'amount' => max(0.01, $p->amount_due - 5),
                'counterparty' => $p->payable->user->full_name,
                'counterparty_ac' => $this->fakeBelgianIban(),
                'structured_ref' => $p->reference,
                'free_ref' => '',
            ];
        }

        // ── 5 third-party operational transactions ────────────────────────────
        foreach ([
            [600.00, 'SUBSIDE ANNUEL 2026',            'Commune Ottignies-LLN',          'BE14 0000 1000 0042', ''],
            [1500.00, 'PARTENARIAT MAILLOTS SAISON',     'Décathlète Sport SA',             'BE32 0000 2000 0099', ''],
            [-1432.00, 'COTISATION FRBTT 2026-2027',      'AFTT — FRBTT Fédération',         'BE22 0000 3000 0017', ''],
            [-25.00, 'AMENDE ARBITRAGE NON EFFECTUE',   'AFTT Province BW',                'BE50 0000 4000 0055', ''],
            [-148.75, 'FACT 2026-0512 MAINTENANCE',      'Sport Pro Belgium SPRL',           'BE88 0000 5000 0031', ''],
        ] as [$amount, $description, $counterparty, $account, $ref]) {
            $rows[] = [
                'date' => $baseDate->copy()->addDays($dayOffset++)->format('d/m/Y'),
                'description' => $internalRef(),
                'amount' => $amount,
                'counterparty' => $counterparty,
                'counterparty_ac' => $account,
                'structured_ref' => $ref,
                'free_ref' => $description,
            ];
        }

        // ── 3 outgoing refunds matching to_refund payments ────────────────────
        foreach ($inCsvRefunds as $p) {
            $user = $p->payable->user;
            $rows[] = [
                'date' => $baseDate->copy()->addDays($dayOffset++)->format('d/m/Y'),
                'description' => $internalRef(),
                'amount' => -(float) $p->amount_paid,
                'counterparty' => $user->full_name,
                'counterparty_ac' => $user->iban ?? $this->fakeBelgianIban(),
                'structured_ref' => '',
                'free_ref' => 'Remboursement — ' . $user->full_name,
            ];
        }

        // ── 2 orphaned outgoing (wrong IBAN, cannot auto-match) ───────────────
        foreach ([
            [-50.00,  'Inconnu Dupont A. — réf. incorrecte', 'BE99 0000 9999 0001'],
            [-120.00, 'Inconnu Martin B. — IBAN inconnu',    'BE77 0000 9999 0002'],
        ] as [$amount, $counterparty, $account]) {
            $rows[] = [
                'date' => $baseDate->copy()->addDays($dayOffset++)->format('d/m/Y'),
                'description' => $internalRef(),
                'amount' => $amount,
                'counterparty' => $counterparty,
                'counterparty_ac' => $account,
                'structured_ref' => '',
                'free_ref' => '',
            ];
        }

        // ── Write file ────────────────────────────────────────────────────────
        $dir = storage_path('app/seeders');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $headers = [
            'Numéro de compte', 'Nom de la rubrique', 'Nom', 'Devise',
            "Numéro de l'extrait", 'Date', 'Description', 'Valeur',
            'Montant', 'Solde', 'crédit', 'débit',
            'numéro de compte contrepartie', 'BIC contrepartie',
            'Nom contrepartie', 'Adresse contrepartie',
            'communication structurée', 'Communication libre',
        ];

        $clubAccount = 'BE11 0000 0000 0001';
        $clubName = 'Club Tennis de Table Ottignies-Blocry';
        $extract = '2026/05';
        $balance = 2000.00;

        $lines = [implode(';', $headers)];

        foreach ($rows as $row) {
            $amount = (float) $row['amount'];
            $balance = round($balance + $amount, 2);
            $credit = $amount > 0 ? number_format($amount, 2, '.', '') : '';
            $debit = $amount < 0 ? number_format(abs($amount), 2, '.', '') : '';
            $dateStr = $row['date'];

            $lines[] = implode(';', [
                $clubAccount,
                'Extrait',
                $clubName,
                'EUR',
                $extract,
                $dateStr,
                $row['description'],
                $dateStr,
                number_format($amount, 2, '.', ''),
                number_format($balance, 2, '.', ''),
                $credit,
                $debit,
                $row['counterparty_ac'],
                'BBRUBEBB',
                $row['counterparty'],
                '',
                $row['structured_ref'],
                $row['free_ref'],
            ]);
        }

        $content = implode("\r\n", $lines) . "\r\n";
        $latin1 = mb_convert_encoding($content, 'ISO-8859-1', 'UTF-8');

        $path = $dir . '/bank_import_demo_2026-05.csv';
        file_put_contents($path, $latin1);
        $this->command?->info("Bank import CSV written to: {$path}");
    }
}
