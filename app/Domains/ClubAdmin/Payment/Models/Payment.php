<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use App\Contracts\DescribesPayment;
use App\Domains\ClubAdmin\Payment\Services\TransactionMatch;
use App\Domains\ClubAdmin\Payment\Support\PaymentCovers;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionDiscount;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $reference
 * @property string|null $transaction_id
 * @property float $amount_due
 * @property float $amount_paid
 * @property string $status
 * @property string $payable_type
 * @property int $payable_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $invitation_counter
 * @property Carbon|null $last_reminded_at
 * @property int|null $refund_transaction_id
 * @property string $payment_method
 * @property string|null $refund_iban
 * @property array<string, mixed>|null $covers what a subscription payment bills, see PaymentCovers
 * @property TransactionMatch|null $match Verdict de rapprochement, posé à la volée — jamais persisté.
 * @property-read Model|\Eloquent $payable
 * @property-read Transaction|null $refundTransaction
 * @property-read Collection<int, SubscriptionDiscount> $discounts
 *
 * @method static \Database\Factories\Domains\ClubAdmin\Payment\Models\PaymentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereAmountDue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereAmountPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereInvitationCounter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePayableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePayableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereRefundTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Payment extends Model
{
    use HasAuditLog;
    use HasFactory;

    /**
     * Les statuts d'une ligne de remboursement dont l'argent est parti ou promis.
     *
     * Une demande ouverte compte déjà : la rejouer créerait un doublon. Une
     * demande annulée (`cancelled`) ne compte pas, et son trop-perçu
     * réapparaît — l'argent est toujours dû au membre.
     */
    public const array REFUND_COMMITTED_STATUSES = ['to_refund', 'refunded'];

    protected $casts = [
        'amount_due' => 'integer',   // stocké en centimes
        'amount_paid' => 'integer',  // stocké en centimes
        'last_reminded_at' => 'datetime',
        'refund_wired_at' => 'datetime',
        'covers' => 'array',
    ];

    protected $fillable = [
        'reference',
        'amount_due',
        'amount_paid',
        'status',
        'payment_method',
        'refund_iban',
        'transaction_id',
        'refund_transaction_id',
        'covers',
    ];

    /**
     * Ce que la communication aurait réclamé sans les remises qui l'ont allégée.
     *
     * Le prix « normal » se reconstitue plutôt qu'il ne se stocke : une remise
     * est gelée en euros et liée à la communication qu'elle a réduite, donc
     * les additionner suffit.
     */
    public function amountBeforeDiscounts(): float
    {
        return round($this->amount_due + $this->discounts->sum('amount'), 2);
    }

    /**
     * Les montants tolèrent l'absence, comme ceux de {@see Subscription}.
     *
     * Un `Payment` n'est pas toujours une ligne en base : le bar en construit
     * un transitoire, `amount_due` et une référence, pour afficher un QR au
     * client. `amount_paid` y est nul, et un type strict fait tomber la page
     * sur une valeur qui n'a jamais eu à exister.
     */
    public function amountDue(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0) / 100, 2),
            set: fn (int|float $value): int => (int) round($value * 100),
        );
    }

    public function amountPaid(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0) / 100, 2),
            set: fn (int|float $value): int => (int) round($value * 100),
        );
    }

    /**
     * Ce qu'il reste à payer sur cette ligne, en euros.
     *
     * La seule chose qu'un membre ou un trésorier veut lire. `amount_due` est
     * ce qui a été réclamé au départ : depuis qu'un paiement peut être crédité
     * en plusieurs fois, les deux divergent, et afficher le premier revient à
     * réclamer une somme déjà reçue.
     *
     * Jamais négatif : un trop-perçu n'est pas une dette négative, c'est de
     * l'argent à rendre — et ça se dit ailleurs.
     */
    public function balance(): float
    {
        return max(0.0, round((float) $this->amount_due - (float) $this->amount_paid, 2));
    }

    /**
     * Les sommes encaissées sur ce paiement.
     *
     * @return HasMany<PaymentCredit, $this>
     */
    public function credits(): HasMany
    {
        return $this->hasMany(PaymentCredit::class);
    }

    /**
     * Les remises d'affiliation que cette communication a absorbées.
     *
     * @return HasMany<SubscriptionDiscount, $this>
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(SubscriptionDiscount::class);
    }

    /**
     * Une créance d'affiliation, par opposition à son remboursement ou à tout
     * autre payable.
     */
    public function isAffiliationClaim(): bool
    {
        return $this->payable_type === Subscription::class && $this->payment_method !== 'refund';
    }

    public function isOverpaid(): bool
    {
        return $this->overpayment() > 0.0;
    }

    /** Une ligne partiellement créditée : de l'argent est entré, il en manque. */
    public function isPartiallyPaid(): bool
    {
        return (float) $this->amount_paid > 0.0 && $this->balance() > 0.0;
    }

    /**
     * What this payment is for, as the member reads it: "Affiliation
     * 2026-2027 + Mini-ping", or only the pack added afterwards.
     *
     * Falls back on the payable's own label when the payment does not say
     * what it covers (another kind of payable, or an old payment the backfill
     * could not place).
     *
     * @return array{type: string, name: string}|null
     */
    public function label(): ?array
    {
        $payable = $this->payable;

        if ($payable instanceof Subscription && is_array($this->covers)) {
            return PaymentCovers::label($this->covers, $payable);
        }

        return $payable instanceof DescribesPayment ? $payable->getPaymentLabel() : null;
    }

    /**
     * Le membre que vise cette ligne, lu sur sa ligne payable déjà chargée.
     *
     * Une ligne de stage ne porte pas de `user_id` : elle nomme son membre par
     * l'affiliation. Même règle que {@see scopeForMembers()}, lue en PHP.
     */
    public function memberId(): ?int
    {
        $payable = $this->payable;

        $memberId = $payable instanceof SubscriptionTrainingPack
            ? $payable->subscription?->user_id
            : $payable?->getAttribute('user_id');

        return $memberId === null ? null : (int) $memberId;
    }

    /**
     * Ce que le club détient en trop sur cette ligne, en euros.
     *
     * Le pendant de {@see balance()} : ce que les crédits dépassent du montant
     * dû, là où le solde est ce qu'il leur manque. Rien n'est stocké — un
     * trop-perçu est une position, pas un objet.
     *
     * Cet argent n'appartient plus au club. Il revient au **compte qui l'a
     * versé**, pas au membre : c'est celui-là qu'on rembourse.
     *
     * Une affiliation se compte entière : voir {@see affiliationOverpayment()}.
     *
     * Une ligne lue par {@see scopeWithOverpayment()} porte déjà son chiffre,
     * calculé par la base : une liste ne paie plus quatre requêtes par ligne.
     */
    public function overpayment(): float
    {
        if (array_key_exists('overpayment_cents', $this->attributes)) {
            return round((int) $this->attributes['overpayment_cents'] / 100, 2);
        }

        if ($this->isAffiliationClaim()) {
            return $this->affiliationOverpayment();
        }

        return max(0.0, round(
            (float) $this->amount_paid - (float) $this->amount_due - $this->refundsCommitted(),
            2,
        ));
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Le compte d'où vient l'argent reçu sur cette ligne.
     *
     * Le dernier crédit adossé à une transaction entrante : c'est ce versement
     * qui a fait basculer la ligne en trop-perçu, et c'est là qu'il faut rendre.
     * Rien quand l'argent n'est venu d'aucun virement — espèces, historique
     * repris sans relevé.
     */
    public function payingAccount(): ?string
    {
        return $this->credits()
            ->whereHas('transaction', fn (Builder $q): Builder => $q->where('amount', '>', 0))
            ->with('transaction')
            ->latest('id')
            ->first()?->transaction?->counterparty_bank_account;
    }

    /**
     * Ce que le club s'est déjà engagé à rendre sur la même chose payée.
     *
     * Un remboursement est une ligne à part : sans cette soustraction, rendre
     * l'argent ne diminuait jamais le trop-perçu, et l'écran réclamait
     * indéfiniment une somme déjà partie.
     *
     * Les versements effectués comptent, et les demandes ouvertes aussi : entre
     * l'ouverture et le virement l'argent est déjà promis, et l'oublier ferait
     * rouvrir une seconde demande pour la même somme. Une demande annulée, elle,
     * ne compte pas : voir {@see self::REFUND_COMMITTED_STATUSES}.
     */
    public function refundsCommitted(): float
    {
        $committed = (int) static::query()
            ->where('payable_type', $this->payable_type)
            ->where('payable_id', $this->payable_id)
            ->where('payment_method', 'refund')
            ->whereIn('status', self::REFUND_COMMITTED_STATUSES)
            ->sum('amount_due');

        return round($committed / 100, 2);
    }

    public function refundTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'refund_transaction_id');
    }

    /**
     * Les lignes de ces membres, parmi les types payables demandés.
     *
     * Une ligne de stage ne porte pas de `user_id` : elle nomme son membre par
     * l'affiliation. Une requête qui l'oublie lève une erreur sous MySQL et
     * rend zéro ligne, sans un mot, sous SQLite — c'est ainsi que « Mes
     * paiements » est parti en erreur pour tout le monde avec une suite verte.
     * Les types restent à la charge de l'appelant : chaque écran a sa liste.
     *
     * @param  Builder<self>  $query
     * @param  int|array<int, int|null>|Arrayable<int, int|null>  $memberIds
     * @param  list<class-string<Model>>  $payableTypes
     * @return Builder<self>
     */
    public function scopeForMembers(Builder $query, int|array|Arrayable $memberIds, array $payableTypes): Builder
    {
        $memberIds = is_int($memberIds) ? [$memberIds] : $memberIds;

        return $query->whereHasMorph('payable', $payableTypes, fn (Builder $payable, string $type): Builder => $type === SubscriptionTrainingPack::class
            ? $payable->whereHas('subscription', fn (Builder $subscription): Builder => $subscription->whereIn('user_id', $memberIds))
            : $payable->whereIn('user_id', $memberIds));
    }

    /**
     * Les lignes dont le club détient de l'argent en trop — jamais une ligne
     * de remboursement, qui est la sortie, pas l'excédent.
     *
     * Même règle que {@see overpayment()}, lue par la base.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOverpaid(Builder $query): Builder
    {
        [$sql, $bindings] = $this->overpaymentInCents();

        return $query
            ->where(fn (Builder $q): Builder => $q->where('payments.payment_method', '!=', 'refund')->orWhereNull('payments.payment_method'))
            ->whereRaw("({$sql}) > 0", $bindings);
    }

    /**
     * Ajoute à chaque ligne son trop-perçu, calculé par la base : c'est ce que
     * {@see overpayment()} lit ensuite, sans requête de plus.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithOverpayment(Builder $query): Builder
    {
        [$sql, $bindings] = $this->overpaymentInCents();

        if ($query->getQuery()->columns === null) {
            $query->select('payments.*');
        }

        return $query->selectRaw("({$sql}) as overpayment_cents", $bindings);
    }

    /**
     * Un paiement marqué « à rembourser » dit qu'il en est un.
     *
     * Deux formes ont coexisté sous ce statut : la ligne dédiée, où
     * `amount_paid` compte ce qui est **sorti**, et un encaissement dont on
     * basculait le statut, où il compte ce qui est **entré**. Sous un même mot,
     * deux sens opposés — aucun écran ne pouvait afficher un chiffre juste pour
     * les deux, et la seconde forme ne s'exécutait pas : son solde valait zéro,
     * et l'affectation du débit était refusée faute de quoi que ce soit à
     * affecter.
     *
     * Le seeder est réparé et l'action a toujours posé la bonne méthode ; cette
     * garde ferme la route pour la suite.
     */
    protected static function booted(): void
    {
        static::saving(function (self $payment): void {
            if ($payment->status === 'to_refund' && $payment->payment_method !== 'refund') {
                throw new \DomainException(
                    'Un paiement « à rembourser » doit porter la méthode `refund` : '
                    . 'basculer le statut d\'un encaissement lui donnerait deux sens à la fois.'
                );
            }
        });
    }

    /**
     * Le trop-perçu d'une affiliation, porté par sa dernière ligne créditée.
     *
     * Un prix qui baisse après paiement laisse de l'argent en trop sans
     * qu'aucune ligne ne le porte : chacune a encaissé ce qu'elle réclamait.
     * L'excédent se calcule donc sur l'ensemble — reçu, moins dû, moins
     * remboursements engagés, comme {@see Subscription::netAmountPaid()} — et
     * s'affiche sur la dernière ligne créditée, celle d'où partirait le
     * remboursement. Les autres lignes n'en portent aucun : le même euro ne
     * se compte qu'une fois.
     *
     * Conséquence assumée : une ligne payée au-delà de son dû, alors qu'une
     * autre attend encore, n'est plus un trop-perçu — c'est de l'argent qui
     * reste dû sur l'affiliation.
     *
     * En centimes bruts, sans passer par les mutateurs, comme le filtre SQL
     * de l'onglet qui doit dire la même chose.
     */
    private function affiliationOverpayment(): float
    {
        $claims = static::query()
            ->where('payable_type', $this->payable_type)
            ->where('payable_id', $this->payable_id)
            ->where(fn (Builder $q): Builder => $q->where('payment_method', '!=', 'refund')->orWhereNull('payment_method'))
            ->where('status', '!=', 'cancelled');

        if ((int) (clone $claims)->where('amount_paid', '>', 0)->max('id') !== $this->id) {
            return 0.0;
        }

        $received = (int) (clone $claims)->sum('amount_paid');

        $committed = (int) static::query()
            ->where('payable_type', $this->payable_type)
            ->where('payable_id', $this->payable_id)
            ->where('payment_method', 'refund')
            ->whereIn('status', self::REFUND_COMMITTED_STATUSES)
            ->sum('amount_due');

        $due = (int) DB::table('subscriptions')->where('id', $this->payable_id)->value('amount_due');

        return max(0.0, round(($received - $committed - $due) / 100, 2));
    }

    /**
     * Le trop-perçu d'une ligne en centimes, en SQL : les deux branches de
     * {@see overpayment()}, sur la ligne `payments` de la requête englobante.
     *
     * Jamais de soustraction qui pourrait passer sous zéro : les montants sont
     * `unsigned`, et MySQL refuse l'underflow même dans une branche qu'un CASE
     * écarterait ensuite. On compare d'abord, on soustrait seulement quand le
     * résultat est positif — vrai aussi sur SQLite, où la suite tourne.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function overpaymentInCents(): array
    {
        $refunds = static fn (string $alias): string => "(select coalesce(sum({$alias}.amount_due), 0) from payments as {$alias}"
            . " where {$alias}.payable_type = payments.payable_type and {$alias}.payable_id = payments.payable_id"
            . " and {$alias}.payment_method = 'refund' and {$alias}.status in (?, ?))";
        $claims = static fn (string $alias): string => "from payments as {$alias}"
            . " where {$alias}.payable_type = payments.payable_type and {$alias}.payable_id = payments.payable_id"
            . " and ({$alias}.payment_method is null or {$alias}.payment_method <> 'refund') and {$alias}.status <> 'cancelled'";

        $lastCredited = '(select max(last_credited.id) ' . $claims('last_credited') . ' and last_credited.amount_paid > 0)';
        $received = '(select coalesce(sum(received.amount_paid), 0) ' . $claims('received') . ')';
        $due = '(select coalesce(max(subscriptions.amount_due), 0) from subscriptions where subscriptions.id = payments.payable_id)';
        $committed = $refunds('committed');
        $lineCommitted = $refunds('line_committed');

        $sql = 'case'
            . " when payments.payable_type = ? and (payments.payment_method is null or payments.payment_method <> 'refund') then"
            . "   case when payments.id = {$lastCredited} and {$received} > {$due} + {$committed}"
            . "     then {$received} - {$due} - {$committed} else 0 end"
            . ' else'
            . "   case when payments.amount_paid > payments.amount_due + {$lineCommitted}"
            . "     then payments.amount_paid - payments.amount_due - {$lineCommitted} else 0 end"
            . ' end';

        $statuses = self::REFUND_COMMITTED_STATUSES;

        // Dans l'ordre des `?` : le type, puis chaque sous-requête de remboursement.
        return [$sql, [Subscription::class, ...$statuses, ...$statuses, ...$statuses, ...$statuses]];
    }
}
