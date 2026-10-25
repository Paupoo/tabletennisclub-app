<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Models;

use App\Domains\Shared\Traits\HasAuditLog;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * How often a player is expected to win against an opponent this many crans
 * away, the club's own readable table.
 *
 * `gap` is counted from our side, positive when the opponent is stronger; the
 * two extremes stand for everything beyond them. Changing a rate changes
 * every player's score at once, which is why each change is audited.
 *
 * @property int $id
 * @property int $gap
 * @property int $rate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|ExpectedWinRate newModelQuery()
 * @method static Builder<static>|ExpectedWinRate newQuery()
 * @method static Builder<static>|ExpectedWinRate query()
 *
 * @mixin Eloquent
 */
class ExpectedWinRate extends Model
{
    use HasAuditLog;

    public const int MAX_GAP = 4;

    protected $casts = [
        'gap' => 'integer',
        'rate' => 'integer',
    ];

    protected $fillable = [
        'gap',
        'rate',
    ];

    /**
     * The expected share of wins, between 0 and 1, for a gap of any size.
     *
     * @param  array<int, int>  $table  gap => rate in percent, as {@see self::table()} returns it
     */
    public static function probability(array $table, int $gap): float
    {
        $clamped = max(-self::MAX_GAP, min(self::MAX_GAP, $gap));

        return ($table[$clamped] ?? 50) / 100;
    }

    /**
     * @return array<int, int> gap => rate in percent
     */
    public static function table(): array
    {
        return self::query()->orderBy('gap')->pluck('rate', 'gap')->all();
    }
}
