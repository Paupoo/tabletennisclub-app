<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\HelpOfferStatus;
use App\Domains\Shared\Enums\HelpRhythm;
use App\Domains\Shared\Traits\HasAuditLog;
use Database\Factories\Domains\ClubAdmin\Feedback\Models\HelpOfferFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A member offering to give the club a hand — always in their own name, even
 * when the feedback sent alongside stays anonymous.
 *
 * @property int $id
 * @property int $user_id
 * @property HelpRhythm $rhythm
 * @property string|null $message
 * @property HelpOfferStatus $status
 * @property int|null $handled_by_id
 * @property Carbon|null $handled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $volunteer
 * @property-read User|null $handledBy
 * @property-read Collection<int, HelpTask> $tasks
 *
 * @method static HelpOfferFactory factory($count = null, $state = [])
 * @method static Builder<static>|HelpOffer open()
 */
class HelpOffer extends Model
{
    use HasAuditLog;

    /** @use HasFactory<HelpOfferFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'to_contact',
    ];

    protected $casts = [
        'rhythm' => HelpRhythm::class,
        'status' => HelpOfferStatus::class,
        'handled_at' => 'datetime',
    ];

    protected $fillable = ['user_id', 'rhythm', 'message', 'status', 'handled_by_id', 'handled_at'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }

    /**
     * The offers the club still owes an answer to.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', HelpOfferStatus::ToContact->value);
    }

    /**
     * @return BelongsToMany<HelpTask, $this>
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(HelpTask::class, 'help_offer_task')->orderBy('position');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
