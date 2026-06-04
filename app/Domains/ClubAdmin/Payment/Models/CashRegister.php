<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $balance
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry> $entries
 * @property-read int|null $entries_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CashRegister extends Model
{
    use HasFactory;

    protected $casts = [
        'balance' => 'integer',
    ];

    protected $fillable = [
        'name',
        'balance',
        'notes',
    ];

    public function currentBalance(): int
    {
        return $this->entries()->sum('amount');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CashRegisterEntry::class);
    }
}
