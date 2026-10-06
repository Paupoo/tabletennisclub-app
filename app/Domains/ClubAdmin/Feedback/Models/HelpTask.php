<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Models;

use App\Domains\Shared\Traits\HasAuditLog;
use Database\Factories\Domains\ClubAdmin\Feedback\Models\HelpTaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Something a member may offer to do for the club: tend the bar, referee,
 * coach the youth in interclub… Edited by the committee, hidden rather than
 * deleted so that past offers keep naming what they offered.
 *
 * @property int $id
 * @property string $name
 * @property int $position
 * @property bool $is_permanent
 * @property Carbon|null $hidden_at
 *
 * @method static HelpTaskFactory factory($count = null, $state = [])
 * @method static Builder<static>|HelpTask offered()
 */
class HelpTask extends Model
{
    use HasAuditLog;

    /** @use HasFactory<HelpTaskFactory> */
    use HasFactory;

    protected $attributes = [
        'is_permanent' => false,
    ];

    protected $casts = [
        'is_permanent' => 'boolean',
        'hidden_at' => 'datetime',
    ];

    protected $fillable = ['name', 'position', 'is_permanent', 'hidden_at'];

    /**
     * @return BelongsToMany<HelpOffer, $this>
     */
    public function offers(): BelongsToMany
    {
        return $this->belongsToMany(HelpOffer::class, 'help_offer_task');
    }

    /**
     * The tasks the forms offer, in the committee's order.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOffered(Builder $query): Builder
    {
        return $query->whereNull('hidden_at')->orderBy('position')->orderBy('id');
    }
}
