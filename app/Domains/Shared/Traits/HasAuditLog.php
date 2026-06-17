<?php

declare(strict_types=1);

namespace App\Domains\Shared\Traits;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Records create/update/delete activity for audited club models.
 *
 * Logs only fillable attributes that actually changed, skipping empty diffs.
 * Technical attributes (password, remember_token) are excluded globally via
 * config/activitylog.php. Models needing extra exclusions may override
 * {@see getActivitylogOptions()}.
 */
trait HasAuditLog
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
