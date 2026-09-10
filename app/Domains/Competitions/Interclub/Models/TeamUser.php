<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * A player's place in a team, promoted to a full model so the audit log can see
 * it: attach/detach/sync fire no Eloquent event on a plain pivot, which left the
 * one change a captain makes most often with no trace of author or content.
 *
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamUser whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamUser whereUserId($value)
 *
 * @mixin \Eloquent
 */
class TeamUser extends Pivot
{
    use HasAuditLog;

    public $incrementing = true;

    /**
     * Both keys, spelled out rather than left to Pivot's empty $guarded: the
     * audit trait logs fillable attributes, and a pivot has none by default —
     * which logs nothing at all, in silence.
     *
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'user_id',
    ];

    protected $table = 'team_user';

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
