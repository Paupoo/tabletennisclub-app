<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Finance\Models;

use App\Domains\ClubAdmin\Finance\Services\FinancialReport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FinancialExportScope;
use App\Domains\Shared\ValueObjects\FiscalYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The export of a financial year, built in the background: a PDF to read at
 * the general assembly, or a ZIP of the originals to keep with the accounts.
 *
 * Only its requester may fetch it, and only for a week: it gathers receipts
 * of many members, and a link that outlives its purpose is a leak waiting for
 * a forwarded mail.
 *
 * `report_ids` are the expense reports the file really holds, written by the
 * job once built: the ZIP download archives exactly those. An export made
 * before the financial report existed names no year; it only lives out its
 * week.
 *
 * @property int $id
 * @property int $requested_by
 * @property string $format
 * @property int|null $fiscal_year
 * @property string|null $poste
 * @property FinancialExportScope $scope
 * @property bool $include_report
 * @property list<int> $report_ids
 * @property string $status
 * @property string|null $path
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $requester
 */
class FinancialExport extends Model
{
    /** Where the files are written on the private disk, one folder per export. */
    public const string DIRECTORY = 'financial-exports';

    /** How long a finished file stays on the disk. */
    public const int KEPT_FOR_DAYS = 7;

    protected $casts = [
        'fiscal_year' => 'integer',
        'scope' => FinancialExportScope::class,
        'include_report' => 'boolean',
        'report_ids' => 'array',
        'expires_at' => 'datetime',
    ];

    protected $fillable = [
        'requested_by',
        'format',
        'fiscal_year',
        'poste',
        'scope',
        'include_report',
        'report_ids',
        'status',
        'path',
        'expires_at',
    ];

    /**
     * The name the file is downloaded under: the year it covers, so that two
     * years side by side in a folder never get mixed up, and whether the
     * report opens it or only the pieces are in.
     */
    public function downloadName(): string
    {
        $year = $this->year();
        $base = match (true) {
            $year === null => 'notes-de-frais',
            $this->include_report => 'rapport-financier-' . $year->label(),
            default => 'pieces-' . $year->label(),
        };

        return $base . '.' . $this->format;
    }

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

    /**
     * What the export holds, in a few words: « 2026 · Salle · Supporting
     * documents only ».
     */
    public function summary(): string
    {
        $year = $this->year();

        if ($year === null) {
            return trans_choice(':count report|:count reports', count($this->report_ids));
        }

        return implode(' · ', array_filter([
            __('Financial year :year', ['year' => $year->label()]),
            $this->poste === null ? null : FinancialReport::posteLabel($this->poste),
            $this->scope === FinancialExportScope::All ? null : $this->scope->label(),
            $this->include_report ? __('With the report') : null,
        ]));
    }

    public function year(): ?FiscalYear
    {
        return $this->fiscal_year === null ? null : FiscalYear::startingIn($this->fiscal_year);
    }
}
