<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Subscriptions\RequestSubscriptionRefundAction;
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

        $this->seedRefunds();
    }

    /**
     * Le virement sortant, puis son affectation.
     *
     * Passe par l'action de ventilation comme le ferait l'écran : c'est elle
     * qui écrit la ligne de crédit et fait basculer le statut.
     */
    private function executeRefund(Payment $refund, User $member): void
    {
        $outgoing = Transaction::create([
            'date' => Carbon::now()->subDays(random_int(2, 14))->toDateString(),
            'description' => 'Remboursement — ' . $member->full_name,
            'amount' => -(float) $refund->amount_due,
            'counterparty_name' => $member->full_name,
            'counterparty_bank_account' => $member->iban,
        ]);

        (new AllocateTransactionAction)($outgoing, [$refund->id => (float) $refund->amount_due]);
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
     * La ligne que le trésorier verra dans l'onglet « À rembourser ».
     *
     * Même forme que {@see RequestSubscriptionRefundAction},
     * sans la notification : un seeder n'envoie pas de courrier, et passer par
     * l'action le rendrait dépendant des permissions Spatie.
     */
    private function openRefund(Payment $encashment, string $reason): Payment
    {
        return $encashment->payable->payments()->create([
            'reference' => (new GeneratePaymentReference)(),
            'amount_due' => $encashment->amount_paid,
            'amount_paid' => 0,
            'status' => 'to_refund',
            'payment_method' => 'refund',
            'refund_iban' => $encashment->payable->user->iban,
        ]);
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
     * 8 remboursements, dans la seule forme qu'un remboursement peut prendre.
     *
     * Une ligne dédiée par remboursement : `amount_due` porte l'engagement de
     * rendre, `amount_paid` ne bouge qu'au débit rapproché, et le paiement
     * encaissé reste `paid` — il a bel et bien été reçu.
     *
     * Le seeder basculait autrefois le statut d'un paiement déjà encaissé.
     * `amount_paid` y comptait alors ce qui était **entré**, là où une ligne
     * dédiée compte ce qui est **sorti** : deux sens opposés sous un même
     * statut, qu'aucun écran ne peut afficher juste pour les deux. Pire, le
     * solde valait zéro, et exécuter le remboursement se terminait sur « il ne
     * reste rien à affecter ». Voir tests/Feature/ClubAdmin/Payment/RefundShapeTest.php.
     *
     *   6 × to_refund — le club n'a pas encore viré
     *   2 × refunded  — le virement sortant est en base, et affecté
     */
    private function seedRefunds(): void
    {
        $encashed = Payment::where('status', 'paid')
            ->orderBy('id')
            ->with(['payable' => fn (MorphTo $m) => $m->morphWith($this->morphWith)])
            ->get()
            ->filter(fn (Payment $p) => $p->payable?->user !== null)
            ->values()
            ->take(8);

        // L'appariement du virement sortant se fait sur le compte visé : sans
        // IBAN, ces lignes ne seraient jamais reconnues.
        foreach ($encashed as $p) {
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
            'Remboursement — virement déjà exécuté',
            'Remboursement — virement déjà exécuté',
        ];

        foreach ($encashed as $i => $encashment) {
            $refund = $this->openRefund($encashment, $reasons[$i]);

            // Les deux derniers sont déjà partis : le débit existe en banque,
            // et c'est son affectation — pas une écriture de statut — qui fait
            // passer la ligne en `refunded`.
            if ($i >= 6) {
                $this->executeRefund($refund, $encashment->payable->user);
            }
        }
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
}
