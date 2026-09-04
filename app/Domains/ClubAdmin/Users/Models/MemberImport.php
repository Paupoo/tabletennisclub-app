<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Models;

use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One run of the federation affiliate listing import.
 *
 * `failed_rows` holds a line number and a reason, and nothing else. The source
 * file carries names, birthdates, postal addresses and phone numbers of minors;
 * this history outlives the file, which is deleted the moment the import is
 * committed, and none of that data is allowed to survive into it.
 *
 * @property int $id
 * @property int $user_id
 * @property int $new_count
 * @property int $updated_count
 * @property int $unchanged_count
 * @property int $skipped_count
 * @property int $error_count
 * @property array<int, array{line: int, reason: string}>|null $failed_rows
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, User> $members
 */
class MemberImport extends Model
{
    use HasAuditLog;

    protected $casts = [
        'failed_rows' => 'array',
    ];

    protected $fillable = [
        'user_id',
        'new_count',
        'updated_count',
        'unchanged_count',
        'skipped_count',
        'error_count',
        'failed_rows',
    ];

    /**
     * The members this run brought in. Provenance, not state: the link survives
     * for good and drives no status of its own.
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** The secretary who ran the import. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
