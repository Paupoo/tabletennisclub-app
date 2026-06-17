<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $new_count
 * @property int $duplicate_count
 * @property int $error_count
 * @property array<int, array<string, mixed>>|null $failed_rows
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Transaction> $transactions
 */
class BankImport extends Model
{
    use HasAuditLog;

    protected $casts = [
        'failed_rows' => 'array',
    ];

    protected $fillable = [
        'user_id',
        'new_count',
        'duplicate_count',
        'error_count',
        'failed_rows',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
