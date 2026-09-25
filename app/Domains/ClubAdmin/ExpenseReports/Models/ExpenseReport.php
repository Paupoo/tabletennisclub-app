<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Models;

use App\Contracts\DescribesPayment;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Casts\IbanCast;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\ExpenseReportDisplayStatus;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use App\Domains\Shared\Traits\HasAuditLog;
use Carbon\CarbonInterface;
use Database\Factories\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * Money a member advanced for the club and asks back.
 *
 * A payable like a fine or a subscription, the other way round: accepting it
 * opens a refund {@see Payment}, and the bank reconciliation that settles
 * that refund is what makes the report paid. Nothing here says "paid" —
 * {@see displayStatus()} reads it off the refund.
 *
 * @property int $id
 * @property int $user_id
 * @property ExpenseCategory $category
 * @property string $description
 * @property float $amount
 * @property float|null $accepted_amount
 * @property Carbon $spent_on
 * @property string $refund_iban
 * @property ExpenseReportStatus $status
 * @property string|null $decision_reason
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property int|null $resumed_from_id
 * @property Carbon|null $archived_at
 * @property Carbon|null $files_purged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read User|null $decider
 * @property-read Payment|null $refund
 * @property-read Collection<int, ExpenseReportFile> $files
 * @property-read Collection<int, Payment> $payments
 *
 * @method static ExpenseReportFactory factory($count = null, $state = [])
 */
class ExpenseReport extends Model implements DescribesPayment
{
    use HasAuditLog;

    /** @use HasFactory<ExpenseReportFactory> */
    use HasFactory;

    protected $casts = [
        'category' => ExpenseCategory::class,
        'status' => ExpenseReportStatus::class,
        'spent_on' => 'date',
        'refund_iban' => IbanCast::class,
        'decided_at' => 'datetime',
        'archived_at' => 'datetime',
        'files_purged_at' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'category',
        'description',
        'amount',
        'accepted_amount',
        'spent_on',
        'refund_iban',
        'status',
        'decision_reason',
        'decided_by',
        'decided_at',
        'resumed_from_id',
        'archived_at',
        'files_purged_at',
    ];

    /**
     * Reports of the same member that look like this one: same amount, spent
     * within three days of it, and still standing.
     *
     * A warning, never a block — two full tanks on the same weekend exist.
     *
     * @return Collection<int, self>
     */
    public static function possibleDuplicatesOf(int $memberId, float $amount, CarbonInterface $spentOn, ?int $exceptId = null): Collection
    {
        return self::query()
            ->where('user_id', $memberId)
            ->where('amount', (int) round($amount * 100))
            ->whereBetween('spent_on', [$spentOn->copy()->subDays(3)->toDateString(), $spentOn->copy()->addDays(3)->toDateString()])
            ->whereIn('status', [ExpenseReportStatus::Submitted, ExpenseReportStatus::Accepted])
            ->when($exceptId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptId))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * What the member reads: "paid" once the refund has been reconciled with
     * the bank debit, the stored status otherwise.
     */
    public function displayStatus(): ExpenseReportDisplayStatus
    {
        if ($this->status === ExpenseReportStatus::Accepted && $this->refund?->status === 'refunded') {
            return ExpenseReportDisplayStatus::Paid;
        }

        return ExpenseReportDisplayStatus::from($this->status->value);
    }

    /**
     * @return HasMany<ExpenseReportFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ExpenseReportFile::class);
    }

    public function getPayerName(): string
    {
        return $this->user->full_name;
    }

    /**
     * @return array{type: string, name: string}
     */
    public function getPaymentLabel(): array
    {
        return [
            'type' => __('Expense report'),
            'name' => $this->category->label() . ' — ' . $this->description,
        ];
    }

    /**
     * Every refund this report ever opened, cancelled ones included.
     *
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * @return Collection<int, self>
     */
    public function possibleDuplicates(): Collection
    {
        return self::possibleDuplicatesOf($this->user_id, $this->amount, $this->spent_on, $this->id);
    }

    /**
     * The refund standing for this report — the live one, never a refund
     * whose acceptance was undone.
     *
     * @return MorphOne<Payment, $this>
     */
    public function refund(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable')
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('status', '!=', 'cancelled'));
    }

    /**
     * Other accepted reports, from any member, carrying one of this report's
     * very files — the same photo sent twice, byte for byte.
     *
     * @return Collection<int, self>
     */
    public function reportsSharingAProof(): Collection
    {
        $fingerprints = $this->files()->pluck('sha256');

        if ($fingerprints->isEmpty()) {
            return new Collection;
        }

        return self::query()
            ->whereKeyNot($this->id)
            ->where('status', ExpenseReportStatus::Accepted)
            ->whereHas('files', fn (Builder $query): Builder => $query->whereIn('sha256', $fingerprints))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function resumedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resumed_from_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Amount in euros, stored in cents (mirrors {@see Payment}).
     */
    protected function acceptedAmount(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): ?float => $value === null ? null : round($value / 100, 2),
            set: fn (int|float|null $value): ?int => $value === null ? null : (int) round($value * 100),
        );
    }

    /**
     * Amount in euros, stored in cents (mirrors {@see Payment}).
     */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn (int $value): float => round($value / 100, 2),
            set: fn (int|float $value): int => (int) round($value * 100),
        );
    }
}
