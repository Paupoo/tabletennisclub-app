<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use Database\Factories\Domains\Competitions\Interclub\Models\InterclubIndividualMatchFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a federation match sheet, written from our side.
 *
 * @property int $id
 * @property int $interclub_id
 * @property int $position
 * @property bool $is_double
 * @property int|null $user_id
 * @property string|null $our_player_name
 * @property string|null $our_player_licence
 * @property string|null $opponent_name
 * @property string|null $opponent_licence
 * @property string|null $opponent_ranking
 * @property int|null $our_sets
 * @property int|null $their_sets
 * @property bool $is_forfeit
 * @property bool $we_won
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Interclub $interclub
 * @property-read User|null $user
 *
 * @method static InterclubIndividualMatchFactory factory($count = null, $state = [])
 * @method static Builder<static>|InterclubIndividualMatch newModelQuery()
 * @method static Builder<static>|InterclubIndividualMatch newQuery()
 * @method static Builder<static>|InterclubIndividualMatch query()
 *
 * @mixin Eloquent
 */
class InterclubIndividualMatch extends Model
{
    use HasFactory;

    protected $casts = [
        'is_double' => 'boolean',
        'is_forfeit' => 'boolean',
        'we_won' => 'boolean',
    ];

    protected $fillable = [
        'interclub_id',
        'is_double',
        'is_forfeit',
        'opponent_licence',
        'opponent_name',
        'opponent_ranking',
        'our_player_licence',
        'our_player_name',
        'our_sets',
        'position',
        'their_sets',
        'user_id',
        'we_won',
    ];

    protected $table = 'interclub_individual_matches';

    public function interclub(): BelongsTo
    {
        return $this->belongsTo(Interclub::class);
    }

    /**
     * The set score, our side first, or null for a line nobody played.
     */
    public function setScore(): ?string
    {
        if ($this->our_sets === null || $this->their_sets === null) {
            return null;
        }

        return $this->our_sets . '-' . $this->their_sets;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
