<?php

declare(strict_types=1);

namespace App\Support\Treasury;

use App\Contracts\DescribesPayment;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\MeetingUser;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Un relevé bancaire de démonstration, accordé à la base du moment.
 *
 * **Lecture seule, et c'est ce qui le rend inoffensif.** Il ne crée rien : les
 * situations qu'il met en scène doivent exister en base, et c'est le rôle des
 * seeders. Un cas introuvable est annoncé plutôt que fabriqué — on ne veut pas
 * d'un outil de test qui invente des membres au milieu des vrais.
 *
 * Les références du CSV sont celles de **vrais paiements en attente** : le
 * rapprochement tranche sur la communication structurée que le club a émise, et
 * un fichier aux références inventées n'apparierait jamais rien.
 */
final class BankStatementFixture
{
    /**
     * Le catalogue. C'est lui qui produit les lignes **et** le manifeste, donc
     * les deux ne peuvent pas se contredire.
     *
     * @var array<string, string>
     */
    public const array CASES = [
        'exact' => 'Référence structurée et montant exacts',
        'free_text' => 'Communication libre seule, sans référence',
        'third_party' => 'Tiers : subside, sponsor, fédération, fournisseur',
        'refund_match' => 'Remboursement sortant appariable',
        'refund_orphan' => 'Sortant orphelin, sans remboursement en attente',
        'duplicate' => 'Doublon strict d\'une ligne du même fichier',
        'partial' => 'Virement inférieur au solde — versement partiel',
        'completes_partial' => 'Le complément d\'une créance déjà partiellement réglée',
        'same_reference_twice' => 'Deux virements portant la même référence',
        'family_transfer' => 'Un virement pour deux enfants, IBAN du tuteur',
        'rounded_up' => 'Virement arrondi au-dessus — reliquat',
        'grouped_refund' => 'Un sortant pour deux remboursements du même foyer',
        'guardian_name_only' => 'Tuteur non affilié, nom seul, sans référence',
        'member_iban_only' => 'Membre payant de son compte, reconnaissable au seul IBAN',
        'unknown_reference' => 'Référence inconnue — la ligne qu\'on ne sait pas classer',
    ];

    /** @var list<string> */
    private array $covered = [];

    /** @var list<array{case: string, lines: string, text: string}> */
    private array $notes = [];

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** @var array<string, string> */
    private array $skipped = [];

    /**
     * @param  string|null  $endDate  Fige la fenêtre, pour rejouer un relevé à l'identique.
     */
    public function build(?string $endDate = null): BankStatementResult
    {
        $this->rows = [];
        $this->covered = [];
        $this->skipped = [];
        $this->notes = [];

        $end = $endDate !== null ? Carbon::parse($endDate) : Carbon::today();

        $pending = $this->pendingPayments();
        $refunds = $this->openRefunds();

        // Les cas distinctifs d'abord. Chacun ne consomme qu'une créance, et
        // c'est pour eux que le relevé existe ; les lignes « parfaites » se
        // ressemblent toutes et prennent ce qui reste. L'ordre inverse a coûté
        // un cas entier sur la vraie base, à une créance près.
        $this->caseCompletesPartial();
        $this->casePartial($pending->shift());
        $this->caseSameReferenceTwice($pending->shift());
        $this->caseRoundedUp($pending->shift());
        $this->caseMemberIbanOnly($pending);
        $this->caseFreeText($pending->splice(0, 2));
        $this->caseFamilyTransfer();
        $this->caseGuardianNameOnly();
        $this->caseExact($pending->splice(0, 12));
        $this->caseThirdParty();
        $this->caseRefundMatch($refunds->shift());
        $this->caseGroupedRefund($refunds);
        $this->caseRefundOrphan();
        $this->caseUnknownReference();
        $this->caseDuplicate();

        return new BankStatementResult(
            csv: $this->renderCsv($end),
            manifest: $this->renderManifest($end),
            covered: $this->covered,
            skipped: $this->skipped,
            rowCount: count($this->rows),
        );
    }

    /**
     * Pose les deux fichiers côte à côte.
     *
     * Le CSV part en Latin-1, comme les relevés que les banques belges
     * exportent réellement ; le manifeste reste en UTF-8, il se lit dans un
     * éditeur. La conversion est ici plutôt que dans le rendu : ce qui circule
     * en mémoire reste de l'UTF-8, et seul l'octet écrit sur disque change.
     *
     * @return array{csv: string, manifest: string} Les chemins écrits.
     */
    public function write(BankStatementResult $result, string $directory): array
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        $csvPath = rtrim($directory, '/') . '/bank_import_demo.csv';
        $manifestPath = rtrim($directory, '/') . '/bank_import_demo.md';

        file_put_contents($csvPath, mb_convert_encoding($result->csv, 'ISO-8859-1', 'UTF-8'));
        file_put_contents($manifestPath, $result->manifest);

        return ['csv' => $csvPath, 'manifest' => $manifestPath];
    }

    // ==================== Les cas ====================

    /**
     * Le complément d'une créance déjà partiellement réglée.
     *
     * Le cas réel le plus fréquent : le membre paie en septembre, puis en
     * novembre, et les deux relevés arrivent à deux mois d'écart. Il ne se
     * confond ni avec le versement partiel — qui n'apporte jamais la suite —
     * ni avec les deux virements de même référence, qui arrivent ensemble sur
     * une créance intacte.
     *
     * Cherché plutôt que fabriqué : la créance doit **déjà** porter un premier
     * versement, et c'est au semis de la poser.
     */
    private function caseCompletesPartial(): void
    {
        $partial = Payment::where('status', 'pending')
            ->where('payment_method', '!=', 'refund')
            ->where('amount_paid', '>', 0)
            ->with(['payable' => fn (Relation $q): mixed => $q instanceof MorphTo
                ? $q->morphWith([
                    Subscription::class => ['user'],
                    TournamentRegistration::class => ['user'],
                    MeetingUser::class => ['user'],
                ])
                : $q])
            ->orderBy('id')
            ->get()
            ->first(fn (Payment $p): bool => $p->balance() > 0.0);

        if (! $partial instanceof Payment) {
            $this->skip('completes_partial', 'aucune créance déjà partiellement réglée — lancez le semis de trésorerie');

            return;
        }

        $this->pushForPayment('completes_partial', $partial, $partial->balance());

        $this->note('completes_partial', sprintf(
            '%s € pour solder une créance de %s € dont %s € étaient déjà reçus. Attendu : le masse le propose, la ligne passe `paid`, et l\'historique du paiement montre les deux versements.',
            number_format($partial->balance(), 2, ',', ' '),
            number_format((float) $partial->amount_due, 2, ',', ' '),
            number_format((float) $partial->amount_paid, 2, ',', ' '),
        ));
    }

    private function caseDuplicate(): void
    {
        $source = $this->rows[0] ?? null;

        if ($source === null) {
            $this->skip('duplicate', 'aucune ligne à dupliquer');

            return;
        }

        // Même date que sa source : l'empreinte d'import porte sur la date, le
        // montant, l'IBAN du tiers, les deux communications et le libellé — pas
        // sur le solde. Sans cette date commune, la copie serait une ligne neuve.
        $source['date_index'] = 0;
        $this->push('duplicate', $source);
        $this->note('duplicate', 'Copie stricte de la première ligne. L\'import doit la compter comme doublon et ne pas la créer.');
    }

    private function caseExact(Collection $payments): void
    {
        if ($payments->isEmpty()) {
            $this->skip('exact', 'aucun paiement en attente');

            return;
        }

        foreach ($payments as $payment) {
            $this->pushForPayment('exact', $payment, (float) $payment->amount_due);
        }

        $this->note('exact', 'Le rapprochement en masse doit toutes les proposer, et les solder d\'un clic.');
    }

    private function caseFamilyTransfer(): void
    {
        $family = $this->familyWithOpenClaims(2);

        if ($family === null) {
            $this->skip('family_transfer', 'aucun tuteur avec deux pupilles ayant une créance ouverte — lancez FamilySeeder');

            return;
        }

        [$guardian, $claims] = $family;
        $total = $claims->sum(fn (Payment $p): float => (float) $p->amount_due);

        $this->push('family_transfer', [
            'amount' => $total,
            'counterparty' => $guardian->full_name,
            'counterparty_ac' => $guardian->iban,
            'structured_ref' => '',
            'free_ref' => 'Cotisations ' . $guardian->last_name,
            'description' => 'VIREMENT EN VOTRE FAVEUR',
        ]);

        $this->note('family_transfer', sprintf(
            'Un seul virement de %s € pour %s. Attendu : le tiroir d\'affectation propose les deux enfants (IBAN du tuteur), et la transaction se dit soldée une fois les deux servis.',
            number_format($total, 2, ',', ' '),
            $claims->map(fn (Payment $p): string => $this->payerName($p))->implode(' et '),
        ));
    }

    private function caseFreeText(Collection $payments): void
    {
        if ($payments->isEmpty()) {
            $this->skip('free_text', 'pas assez de paiements en attente');

            return;
        }

        foreach ($payments as $payment) {
            $this->push('free_text', [
                'amount' => (float) $payment->amount_due,
                'counterparty' => $this->payerName($payment),
                'counterparty_ac' => $this->ibanOf($payment),
                'structured_ref' => '',
                'free_ref' => 'Cotisation ' . $this->payerName($payment),
                'description' => 'VIREMENT EN VOTRE FAVEUR',
            ]);
        }

        $this->note('free_text', 'Sans référence, le masse ne les propose pas. Elles doivent remonter dans la modale manuelle, notées par le nom.');
    }

    private function caseGroupedRefund(Collection $refunds): void
    {
        $two = $refunds->take(2);

        if ($two->count() < 2) {
            $this->skip('grouped_refund', 'moins de deux remboursements engagés');

            return;
        }

        $total = $two->sum(fn (Payment $p): float => (float) $p->amount_due);

        $this->push('grouped_refund', [
            'amount' => -$total,
            'counterparty' => $this->payerName($two->first()),
            'counterparty_ac' => $this->ibanOf($two->first()),
            'structured_ref' => '',
            'free_ref' => 'Remboursements',
            'description' => 'VIREMENT EN FAVEUR DE TIERS',
        ]);

        $this->note('grouped_refund', sprintf(
            'Un seul virement sortant de %s € pour deux remboursements. Attendu : les deux lignes passent `refunded`, la transaction est soldée.',
            number_format($total, 2, ',', ' '),
        ));
    }

    private function caseGuardianNameOnly(): void
    {
        $family = $this->familyWithOpenClaims(1);

        if ($family === null) {
            $this->skip('guardian_name_only', 'aucun tuteur avec un pupille ayant une créance ouverte');

            return;
        }

        [$guardian, $claims] = $family;
        $claim = $claims->first();

        $this->push('guardian_name_only', [
            'amount' => (float) $claim->amount_due,
            'counterparty' => $guardian->full_name,
            'counterparty_ac' => '',
            'structured_ref' => '',
            'free_ref' => '',
            'description' => 'VIREMENT EN VOTRE FAVEUR',
        ]);

        $this->note('guardian_name_only', sprintf(
            'Ni référence ni IBAN — seulement « %s ». Attendu : le barème reconnaît le tuteur et propose %s.',
            $guardian->full_name,
            $this->payerName($claim),
        ));
    }

    /**
     * Ni référence ni nom lisible : seul l'IBAN désigne le membre.
     *
     * Le tiers porte un libellé que la banque du payeur a fabriqué — souvent
     * le nom du titulaire du compte en majuscules, parfois autre chose — et le
     * barème doit s'en sortir avec le numéro de compte seul.
     */
    private function caseMemberIbanOnly(Collection $pending): void
    {
        // On cherche, on ne prend pas le suivant : les premières créances sont
        // souvent celles de pupilles, et un mineur n'a pas de compte en banque.
        $index = $pending->search(fn (Payment $p): bool => $this->ibanOf($p) !== '');

        if ($index === false) {
            $this->skip('member_iban_only', 'aucun membre avec une créance ouverte ne porte d\'IBAN en fiche');

            return;
        }

        $payment = $pending->pull($index);
        $iban = $this->ibanOf($payment);

        $this->push('member_iban_only', [
            'amount' => (float) $payment->amount_due,
            'counterparty' => 'TITULAIRE DU COMPTE',
            'counterparty_ac' => $iban,
            'structured_ref' => '',
            'free_ref' => '',
            'description' => 'VIREMENT EN VOTRE FAVEUR',
        ]);

        $this->note('member_iban_only', sprintf(
            'Ni référence ni nom exploitable — seulement le compte %s. Attendu : le barème reconnaît %s par son IBAN et le propose en tête des candidats.',
            $iban,
            $this->payerName($payment),
        ));
    }

    private function casePartial(?Payment $payment): void
    {
        if (! $payment instanceof Payment) {
            $this->skip('partial', 'pas assez de paiements en attente');

            return;
        }

        $amount = round((float) $payment->amount_due * 0.55, 2);

        $this->pushForPayment('partial', $payment, $amount);

        $this->note('partial', sprintf(
            '%s € sur %s € dus. Attendu : la ligne reste `pending`, le solde affiche le reste, et les relances continuent de partir.',
            number_format($amount, 2, ',', ' '),
            number_format((float) $payment->amount_due, 2, ',', ' '),
        ));
    }

    private function caseRefundMatch(?Payment $refund): void
    {
        if (! $refund instanceof Payment) {
            $this->skip('refund_match', 'aucun remboursement engagé');

            return;
        }

        $this->push('refund_match', [
            'amount' => -(float) $refund->amount_due,
            'counterparty' => $this->payerName($refund),
            'counterparty_ac' => $this->ibanOf($refund),
            'structured_ref' => '',
            'free_ref' => 'Remboursement ' . $this->payerName($refund),
            'description' => 'VIREMENT EN FAVEUR DE TIERS',
        ]);

        $this->note('refund_match', 'Appariable par IBAN et montant. Attendu : la ligne passe `refunded`.');
    }

    private function caseRefundOrphan(): void
    {
        $this->push('refund_orphan', [
            'amount' => -47.30,
            'counterparty' => 'Fournisseur Balles & Raquettes',
            'counterparty_ac' => 'BE29000000000058',
            'structured_ref' => '',
            'free_ref' => 'Facture 2026-0417',
            'description' => 'VIREMENT EN FAVEUR DE TIERS',
        ]);

        $this->note('refund_orphan', 'Aucun remboursement ne l\'attend. Attendu : elle reste « non rapprochée » et ne doit pas disparaître de la liste.');
    }

    private function caseRoundedUp(?Payment $payment): void
    {
        if (! $payment instanceof Payment) {
            $this->skip('rounded_up', 'pas assez de paiements en attente');

            return;
        }

        $amount = round((float) $payment->amount_due + 5, 2);

        $this->pushForPayment('rounded_up', $payment, $amount);

        $this->note('rounded_up', sprintf(
            '%s € pour %s € dus. Attendu : la transaction devient « partiellement affectée », 5,00 € à placer. Trois sorties — affecter ailleurs, solder avec motif, rembourser.',
            number_format($amount, 2, ',', ' '),
            number_format((float) $payment->amount_due, 2, ',', ' '),
        ));
    }

    private function caseSameReferenceTwice(?Payment $payment): void
    {
        if (! $payment instanceof Payment) {
            $this->skip('same_reference_twice', 'pas assez de paiements en attente');

            return;
        }

        $due = (float) $payment->amount_due;
        $first = round($due * 0.6, 2);

        $this->pushForPayment('same_reference_twice', $payment, $first);
        $this->pushForPayment('same_reference_twice', $payment, round($due - $first, 2));

        $this->note('same_reference_twice', sprintf(
            'Deux virements portant %s. Attendu : le masse propose **les deux** — un index par clé unique n\'en garderait qu\'un — et la ligne se solde.',
            $payment->reference,
        ));
    }

    private function caseThirdParty(): void
    {
        $thirdParties = [
            [820.00, 'Commune d\'Ottignies-LLN', 'BE68000000000001', 'Subside fonctionnement 2026'],
            [1500.00, 'Assurances Delvaux SPRL', 'BE68000000000002', 'Sponsoring maillots'],
            [-240.00, 'AFTT ASBL', 'BE68000000000003', 'Affiliations fédérales'],
            [-89.90, 'Energie Wallonie', 'BE68000000000004', 'Electricité avril'],
        ];

        foreach ($thirdParties as [$amount, $name, $iban, $label]) {
            $this->push('third_party', [
                'amount' => $amount,
                'counterparty' => $name,
                'counterparty_ac' => $iban,
                'structured_ref' => '',
                'free_ref' => $label,
                'description' => $amount > 0 ? 'VIREMENT EN VOTRE FAVEUR' : 'VIREMENT EN FAVEUR DE TIERS',
            ]);
        }

        $this->note('third_party', 'Du bruit légitime. Attendu : rien ne s\'apparie, et ces lignes n\'encombrent pas les candidates proposées au trésorier.');
    }

    private function caseUnknownReference(): void
    {
        $this->push('unknown_reference', [
            'amount' => 95.00,
            'counterparty' => 'DUBOIS Jean-Pierre',
            'counterparty_ac' => 'BE86000000000099',
            'structured_ref' => '999/9999/99999',
            'free_ref' => '',
            'description' => 'VIREMENT EN VOTRE FAVEUR',
        ]);

        $this->note('unknown_reference', 'Une référence qui n\'existe pas, un nom qu\'on ne connaît pas. Attendu : rien ne s\'apparie, et l\'écran doit laisser le trésorier avancer sans savoir.');
    }

    // ==================== Lecture de la base ====================

    /**
     * Un tuteur dont au moins `$wards` pupilles ont une créance ouverte.
     *
     * @return array{0: Guardian, 1: Collection<int, Payment>}|null
     */
    private function familyWithOpenClaims(int $wards): ?array
    {
        foreach (Guardian::has('users')->with('users')->get() as $guardian) {
            $claims = collect();

            foreach ($guardian->users as $ward) {
                $subscriptionIds = Subscription::where('user_id', $ward->id)->pluck('id');

                $claim = Payment::where('payable_type', Subscription::class)
                    ->whereIn('payable_id', $subscriptionIds)
                    ->where('status', 'pending')
                    ->orderBy('id')
                    ->first();

                if ($claim instanceof Payment) {
                    $claims->push($claim);
                }
            }

            if ($claims->count() >= $wards) {
                return [$guardian, $claims->take($wards)];
            }
        }

        return null;
    }

    private function ibanOf(Payment $payment): string
    {
        $payable = $payment->payable;
        $user = $payable instanceof Subscription ? $payable->user : null;

        return (string) ($user?->iban ?? '');
    }

    // ==================== Écriture ====================

    private function note(string $case, string $text): void
    {
        $lines = [];

        foreach ($this->rows as $index => $row) {
            if ($row['case'] === $case) {
                $lines[] = $index + 2; // +1 pour l'en-tête, +1 pour compter depuis 1
            }
        }

        $this->notes[] = [
            'case' => $case,
            'lines' => $lines === [] ? '—' : implode(', ', $lines),
            'text' => $text,
        ];
    }

    /** @return Collection<int, Payment> */
    private function openRefunds(): Collection
    {
        return Payment::where('status', 'to_refund')
            ->with(['payable' => fn (Relation $q): mixed => $q instanceof MorphTo
                ? $q->morphWith([
                    Subscription::class => ['user'],
                    TournamentRegistration::class => ['user'],
                    MeetingUser::class => ['user'],
                ])
                : $q])
            ->orderBy('id')
            ->get();
    }

    private function payerName(Payment $payment): string
    {
        $payable = $payment->payable;

        return $payable instanceof DescribesPayment ? $payable->getPayerName() : '—';
    }

    /**
     * Les créances ouvertes, dans un ordre stable.
     *
     * `orderBy('id')` : deux générations sur la même base doivent produire le
     * même fichier, sinon comparer deux passes ne veut rien dire.
     *
     * @return Collection<int, Payment>
     */
    private function pendingPayments(): Collection
    {
        return Payment::where('status', 'pending')
            ->where('payment_method', '!=', 'refund')
            ->with(['payable' => fn (Relation $q): mixed => $q instanceof MorphTo
                ? $q->morphWith([
                    Subscription::class => ['user'],
                    TournamentRegistration::class => ['user'],
                    MeetingUser::class => ['user'],
                ])
                : $q])
            ->orderBy('id')
            ->get()
            // Le membre n'est pas testé ici : la relation le promet non nul, et
            // les deux lectures qui en dépendent — nom du payeur, IBAN — sont
            // déjà tolérantes à son absence.
            ->filter(fn (Payment $p): bool => $p->payable instanceof Subscription)
            ->values();
    }

    /** @param array<string, mixed> $row */
    private function push(string $case, array $row): void
    {
        $row['case'] = $case;
        $this->rows[] = $row;

        if (! in_array($case, $this->covered, true)) {
            $this->covered[] = $case;
        }
    }

    private function pushForPayment(string $case, Payment $payment, float $amount): void
    {
        $this->push($case, [
            'amount' => $amount,
            'counterparty' => $this->payerName($payment),
            'counterparty_ac' => $this->ibanOf($payment),
            'structured_ref' => $payment->reference,
            'free_ref' => '',
            'description' => 'VIREMENT EN VOTRE FAVEUR',
        ]);
    }

    private function renderCsv(Carbon $end): string
    {
        $headers = [
            'Numéro de compte', 'Nom de la rubrique', 'Nom', 'Devise',
            "Numéro de l'extrait", 'Date', 'Description', 'Valeur',
            'Montant', 'Solde', 'crédit', 'débit',
            'numéro de compte contrepartie', 'BIC contrepartie',
            'Nom contrepartie', 'Adresse contrepartie',
            'communication structurée', 'Communication libre',
        ];

        $lines = [implode(';', $headers)];

        $balance = 2000.00;

        // Le rang de chaque ligne dans la fenêtre. Une copie emprunte celui de
        // sa source, donc les rangs ne suivent pas l'ordre des lignes — et la
        // fenêtre se cale sur le plus grand d'entre eux, sinon le dernier jour
        // (aujourd'hui) n'est porté par aucune ligne.
        $offsets = [];

        foreach ($this->rows as $index => $row) {
            $offsets[$index] = $row['date_index'] ?? $index;
        }

        // Elle se termine aujourd'hui : un relevé vieux de quatre mois tombe
        // hors de toute plage que le trésorier pense à regarder, et on croit
        // l'import cassé.
        $start = $end->copy()->subDays($offsets === [] ? 0 : max($offsets));

        foreach ($this->rows as $index => $row) {
            $amount = (float) $row['amount'];
            $balance = round($balance + $amount, 2);
            $date = $start->copy()->addDays($offsets[$index])->format('d/m/Y');

            $lines[] = implode(';', [
                'BE11 0000 0000 0001',
                'Extrait',
                'Club Tennis de Table Ottignies-Blocry',
                'EUR',
                $end->format('Y/m'),
                $date,
                $row['description'],
                $date,
                number_format($amount, 2, '.', ''),
                number_format($balance, 2, '.', ''),
                $amount > 0 ? number_format($amount, 2, '.', '') : '',
                $amount < 0 ? number_format(abs($amount), 2, '.', '') : '',
                $row['counterparty_ac'],
                'BBRUBEBB',
                $row['counterparty'],
                '',
                $row['structured_ref'],
                $row['free_ref'],
            ]);
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    private function renderManifest(Carbon $end): string
    {
        $out = [
            '# Relevé de démonstration — ' . $end->format('d/m/Y'),
            '',
            sprintf('%d lignes, %d cas sur %d.', count($this->rows), count($this->covered), count(self::CASES)),
            '',
            'Importez le CSV depuis **Trésorerie → Transactions → Importer un relevé**,',
            'puis vérifiez chaque cas ci-dessous.',
            '',
        ];

        foreach ($this->notes as $note) {
            $out[] = '## ' . self::CASES[$note['case']];
            $out[] = '';
            $out[] = '*Ligne(s) ' . $note['lines'] . '*';
            $out[] = '';
            $out[] = $note['text'];
            $out[] = '';
        }

        if ($this->skipped !== []) {
            $out[] = '## Cas non produits';
            $out[] = '';

            foreach ($this->skipped as $case => $why) {
                $out[] = sprintf('- **%s** — %s', self::CASES[$case], $why);
            }

            $out[] = '';
        }

        return implode("\n", $out);
    }

    private function skip(string $case, string $why): void
    {
        $this->skipped[$case] = $why;
    }
}
