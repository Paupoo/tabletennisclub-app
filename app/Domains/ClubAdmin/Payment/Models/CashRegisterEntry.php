<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cash_register_id
 * @property int $amount
 * @property string $reason
 * @property string|null $payable_type
 * @property int|null $payable_id
 * @property int $recorded_by_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CashRegister $cashRegister
 * @property-read Model|\Eloquent|null $payable
 * @property-read User $recordedBy
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereCashRegisterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry wherePayableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry wherePayableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereRecordedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class CashRegisterEntry extends Model
{
    use HasFactory;

    protected $casts = [
        'amount' => 'integer',
    ];

    protected $fillable = [
        'cash_register_id',
        'amount',
        'reason',
        'payable_type',
        'payable_id',
        'recorded_by_id',
        'notes',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
