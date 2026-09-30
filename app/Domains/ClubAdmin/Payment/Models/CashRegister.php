<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $balance
 * @property string|null $notes
 * @property int|null $held_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, CashRegisterEntry> $entries
 * @property-read int|null $entries_count
 * @property-read User|null $heldBy
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class CashRegister extends Model
{
    use HasAuditLog;
    use HasFactory;
    use SoftDeletes;

    protected $casts = [
        'balance' => 'integer',
    ];

    protected $fillable = [
        'name',
        'balance',
        'notes',
        'held_by_user_id',
    ];

    /**
     * Every till's name as a list shows it: its own name, and who holds it
     * when another till — retired ones included — bears the same name.
     *
     * @return array<int, string> keyed by id
     */
    public static function displayNames(): array
    {
        $registers = self::withTrashed()->with('heldBy')->orderBy('id')->get();
        $counts = $registers->countBy('name');

        return $registers->mapWithKeys(fn (self $register): array => [
            $register->id => $counts[$register->name] > 1 && $register->heldBy !== null
                ? $register->name . ' (' . $register->heldBy->full_name . ')'
                : $register->name,
        ])->all();
    }

    public function currentBalance(): int
    {
        return (int) $this->entries()->sum('amount');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CashRegisterEntry::class);
    }

    public function heldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by_user_id');
    }
}
