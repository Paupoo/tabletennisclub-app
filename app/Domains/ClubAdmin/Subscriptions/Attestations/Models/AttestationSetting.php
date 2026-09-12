<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How the club signs and stamps its attestations.
 *
 * A singleton: {@see current()} is the only way in, so no screen has to decide
 * what to do when the row is missing.
 *
 * @property int $id
 * @property int|null $signatory_user_id
 * @property string|null $seal_path
 * @property int $seal_width_mm
 * @property string|null $signature_path
 * @property int $signature_width_mm
 * @property string $federation_name
 * @property string $discipline
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $signatory
 *
 * @mixin \Eloquent
 */
class AttestationSetting extends Model
{
    use HasAuditLog;

    /**
     * Defaults the model knows about, not only the table.
     *
     * `firstOrCreate([])` inserts an empty row and hands back an instance that
     * has never seen the column defaults the database filled in — a screen
     * reading a width straight after creating the row would read null.
     */
    protected $attributes = [
        'seal_width_mm' => 30,
        'signature_width_mm' => 42,
        'federation_name' => 'AFTT',
        'discipline' => 'Tennis de table',
    ];

    protected $casts = [
        'seal_width_mm' => 'integer',
        'signature_width_mm' => 'integer',
    ];

    protected $fillable = [
        'signatory_user_id',
        'seal_path',
        'seal_width_mm',
        'signature_path',
        'signature_width_mm',
        'federation_name',
        'discipline',
    ];

    /** The one row, created on first read so callers never handle its absence. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * Everything the club must have provided before a document may be stamped.
     *
     * @return array<int, string>
     */
    public function missingRequirements(): array
    {
        $missing = [];

        if ($this->signatory_user_id === null) {
            $missing[] = __('the signing officer');
        }

        if ($this->seal_path === null) {
            $missing[] = __('the club seal');
        }

        if ($this->signature_path === null) {
            $missing[] = __('the signature');
        }

        return $missing;
    }

    public function signatory(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signatory_user_id');
    }
}
