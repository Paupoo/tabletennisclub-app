<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One count of the till by the treasurer: what the books said (`expected`)
 * and what the drawer held (`counted`), in cents. Its date is `created_at`,
 * and it bounds the period the next count's difference is blamed on.
 *
 * @property int $id
 * @property int $cash_register_id
 * @property int $expected
 * @property int $counted
 * @property int|null $counted_by_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CashRegisterCount extends Model
{
    use HasAuditLog;
    use HasFactory;

    protected $casts = [
        'counted' => 'integer',
        'expected' => 'integer',
    ];

    protected $fillable = [
        'cash_register_id',
        'expected',
        'counted',
        'counted_by_id',
        'notes',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by_id');
    }

    /**
     * What the drawer held beyond the books, in cents: negative when money
     * was missing.
     */
    public function difference(): int
    {
        return $this->counted - $this->expected;
    }
}
