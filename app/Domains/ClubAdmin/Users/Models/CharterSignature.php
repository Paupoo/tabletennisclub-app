<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Models;

use App\Domains\Competitions\Interclub\Models\Season;
use App\Support\ClubCharter;
use Database\Factories\Domains\ClubAdmin\Users\Models\CharterSignatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A member's commitment to the club charter, for one season.
 *
 * Deliberately not a column on `subscriptions`: committee members who do not
 * play never affiliate, yet the charter gives them duties in almost every
 * chapter. Keyed on (member, season), so an affiliation cancelled and re-created
 * inside the same season does not ask for a second signature.
 *
 * @property int $id
 * @property int $user_id
 * @property int $season_id
 * @property int $signed_by_user_id
 * @property int $version
 * @property Carbon $signed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Season $season
 * @property-read User $signedBy
 * @property-read User $user
 *
 * @method static \Database\Factories\Domains\ClubAdmin\Users\Models\CharterSignatureFactory factory($count = null, $state = [])
 */
class CharterSignature extends Model
{
    /** @use HasFactory<CharterSignatureFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'season_id',
        'signed_by_user_id',
        'version',
        'signed_at',
    ];

    /**
     * Record a member's signature, unless they already have one this season.
     *
     * `$signedBy` is the account that actually ticked the box: it differs from
     * `$user` when a guardian signs for their family group, and the trail must
     * not pretend a member without an account signed for themselves.
     */
    public static function sign(User $user, Season $season, User $signedBy): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id, 'season_id' => $season->id],
            [
                'signed_by_user_id' => $signedBy->id,
                'version' => ClubCharter::VERSION,
                'signed_at' => now(),
            ],
        );
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
