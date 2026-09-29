<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use App\Domains\Shared\Casts\IbanCast;
use App\Domains\Shared\Enums\BankAccountType;
use App\Domains\Shared\Support\IbanNormalizer;
use App\Domains\Shared\Traits\HasAuditLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One of the club's bank accounts.
 *
 * Registered by the treasurer the first time a statement of that account is
 * imported — never guessed: a statement of a personal account lying in the
 * same downloads folder must not enter the club's books.
 *
 * @property int $id
 * @property string $iban Normalised: no spaces, upper case.
 * @property string $name
 * @property BankAccountType $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Transaction> $transactions
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount current()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount query()
 *
 * @mixin \Eloquent
 */
class BankAccount extends Model
{
    use HasAuditLog;
    use HasFactory;

    protected $casts = [
        'iban' => IbanCast::class,
        'type' => BankAccountType::class,
    ];

    protected $fillable = [
        'iban',
        'name',
        'type',
    ];

    /**
     * The registered account carrying this IBAN, however the bank spaced it.
     */
    public static function findByIban(?string $iban): ?self
    {
        $normalized = IbanNormalizer::normalize($iban);

        return $normalized === null ? null : self::where('iban', $normalized)->first();
    }

    /**
     * The account's balance at the end of this day, in euros, as the bank
     * printed it on the last line up to that day. Null when no line of that
     * account carrying a balance is that old.
     */
    public function balanceAt(CarbonInterface $date): ?float
    {
        return $this->balanceLineAt($date)?->balance_after;
    }

    /**
     * The line whose balance is the account's balance at the end of this day.
     *
     * Its date says how fresh that balance is. Within a day the bank lists
     * its lines oldest first, and the import keeps that order: the latest
     * statement number, then the latest line entered, is the last movement.
     */
    public function balanceLineAt(CarbonInterface $date): ?Transaction
    {
        return $this->transactions()
            ->whereNotNull('balance_after')
            ->whereDate('date', '<=', $date->toDateString())
            ->orderByDesc('date')
            ->orderByDesc('statement_number')
            ->orderByDesc('transactions.id')
            ->first();
    }

    /**
     * The account's name and its IBAN as printed, for a select or a caption.
     */
    public function label(): string
    {
        return $this->name . ' · ' . IbanNormalizer::format($this->iban);
    }

    /**
     * @param  Builder<BankAccount>  $query
     * @return Builder<BankAccount>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('type', BankAccountType::Current->value);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
