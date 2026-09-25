<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A printable or archivable file of expense reports, built in the background.
 *
 * Only its requester may fetch it, and only for a week: it gathers receipts
 * of many members, and a link that outlives its purpose is a leak waiting for
 * a forwarded mail.
 *
 * @property int $id
 * @property int $requested_by
 * @property string $format
 * @property list<int> $report_ids
 * @property string $status
 * @property string|null $path
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $requester
 */
class ExpenseReportExport extends Model
{
    /** How long a finished file stays on the disk. */
    public const int KEPT_FOR_DAYS = 7;

    protected $casts = [
        'report_ids' => 'array',
        'expires_at' => 'datetime',
    ];

    protected $fillable = [
        'requested_by',
        'format',
        'report_ids',
        'status',
        'path',
        'expires_at',
    ];

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    public function isZip(): bool
    {
        return $this->format === 'zip';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
