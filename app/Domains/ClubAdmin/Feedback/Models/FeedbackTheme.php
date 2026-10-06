<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Models;

use App\Domains\Shared\Traits\HasAuditLog;
use Database\Factories\Domains\ClubAdmin\Feedback\Models\FeedbackThemeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * What a piece of feedback is about — trainings, the bar, interclubs… — in the
 * club's own words, edited by the committee.
 *
 * Hidden rather than deleted: a theme dropped from the forms still names the
 * feedback filed under it.
 *
 * @property int $id
 * @property string $name
 * @property int $position
 * @property bool $is_permanent
 * @property Carbon|null $hidden_at
 *
 * @method static FeedbackThemeFactory factory($count = null, $state = [])
 * @method static Builder<static>|FeedbackTheme offered()
 */
class FeedbackTheme extends Model
{
    use HasAuditLog;

    /** @use HasFactory<FeedbackThemeFactory> */
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
     * @return HasMany<FeedbackEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(FeedbackEntry::class);
    }

    /**
     * The themes the forms offer, in the committee's order.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOffered(Builder $query): Builder
    {
        return $query->whereNull('hidden_at')->orderBy('position')->orderBy('id');
    }
}
