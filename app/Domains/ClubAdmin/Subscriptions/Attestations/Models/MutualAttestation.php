<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Models;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Mutuality;
use App\Domains\Shared\Traits\HasAuditLog;
use Database\Factories\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A certificate the club has issued, and everything it stated.
 *
 * @property int $id
 * @property int $user_id
 * @property int $subscription_id
 * @property int $season_id
 * @property Mutuality $mutuality
 * @property string $reference
 * @property string $token
 * @property string|null $path
 * @property float $amount_certified
 * @property Carbon $period_from
 * @property Carbon $period_to
 * @property string $signatory_name
 * @property string $discipline
 * @property int|null $issued_by_user_id
 * @property Carbon $issued_at
 * @property Carbon|null $purged_at
 * @property Carbon|null $revoked_at
 * @property string|null $revocation_reason
 * @property-read User $user
 * @property-read Season $season
 * @property-read Subscription $subscription
 *
 * @method static Builder<static>|MutualAttestation live()
 * @method static \Database\Factories\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestationFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class MutualAttestation extends Model
{
    use HasAuditLog;

    /** @use HasFactory<MutualAttestationFactory> */
    use HasFactory;

    protected $casts = [
        'mutuality' => Mutuality::class,
        'period_from' => 'date',
        'period_to' => 'date',
        'issued_at' => 'datetime',
        'purged_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'subscription_id',
        'season_id',
        'mutuality',
        'reference',
        'token',
        'path',
        'amount_certified',
        'period_from',
        'period_to',
        'signatory_name',
        'discipline',
        'issued_by_user_id',
        'issued_at',
        'purged_at',
        'revoked_at',
        'revocation_reason',
    ];

    /** Whether the file is still on disk, or the retention window has closed. */
    public function isDownloadable(): bool
    {
        return $this->path !== null && $this->revoked_at === null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    /** The ones that still stand: a revoked certificate frees the season again. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Stored in cents, handled in euros, like every other amount here. */
    protected function amountCertified(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0) / 100, 2),
            set: fn (int|float $value): int => (int) round($value * 100),
        );
    }
}
