<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A proof attached to an expense report: a receipt, a bank statement line.
 *
 * Kept on the private `local` disk, never public: a receipt can carry a card
 * number's last digits, an address, a name. The fingerprint spots the same
 * file sent twice, whoever sends it.
 *
 * @property int $id
 * @property int $expense_report_id
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string $sha256
 * @property-read ExpenseReport $expenseReport
 */
class ExpenseReportFile extends Model
{
    protected $fillable = [
        'path',
        'original_name',
        'mime_type',
        'size',
        'sha256',
    ];

    /**
     * @return BelongsTo<ExpenseReport, $this>
     */
    public function expenseReport(): BelongsTo
    {
        return $this->belongsTo(ExpenseReport::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
