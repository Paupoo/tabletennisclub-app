<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Models;

use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\DepartureReason;
use Database\Factories\Domains\ClubAdmin\Users\Models\MemberDepartureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A member who told the club they are leaving, for one season.
 *
 * It outweighs every affiliation of that season in the member's status — see
 * `MembershipStatus::Left` — and says nothing any more once the season is over:
 * a member who comes back is back, one who does not is a former member.
 *
 * Not a cancellation: the affiliation, the payments and the history stay as
 * they are.
 *
 * @property int $id
 * @property int $user_id
 * @property int $season_id
 * @property Carbon $left_on
 * @property DepartureReason $reason
 * @property string|null $note
 * @property int|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $recordedBy
 * @property-read Season $season
 * @property-read User $user
 *
 * @method static \Database\Factories\Domains\ClubAdmin\Users\Models\MemberDepartureFactory factory($count = null, $state = [])
 */
class MemberDeparture extends Model
{
    /** @use HasFactory<MemberDepartureFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'season_id',
        'left_on',
        'reason',
        'note',
        'recorded_by',
    ];

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
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
            'left_on' => 'date',
            'reason' => DepartureReason::class,
        ];
    }
}
