<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Contact\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\AgeCategoryEnum;
use App\Domains\Shared\Enums\ContactReasonEnum;
use App\Domains\Shared\Enums\PlayerExperienceEnum;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property ContactReasonEnum $interest
 * @property string $message
 * @property int|null $membership_family_members
 * @property int|null $membership_competitors
 * @property int|null $membership_training_sessions
 * @property int|null $membership_total_cost
 * @property string $status
 * @property int|null $owner_id
 * @property AgeCategoryEnum|null $age_category
 * @property PlayerExperienceEnum|null $experience
 * @property bool|null $wants_competition
 * @property array<int, string>|null $preferred_days
 * @property bool|null $family_can_drive
 * @property int|null $user_id
 * @property-read User|null $user
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|Contact byStatus(string $status)
 * @method static \Database\Factories\Domains\ClubAdmin\Contact\Models\ContactFactory factory($count = null, $state = [])
 * @method static Builder<static>|Contact newModelQuery()
 * @method static Builder<static>|Contact newQuery()
 * @method static Builder<static>|Contact query()
 * @method static Builder<static>|Contact search(string $value)
 * @method static Builder<static>|Contact whereCreatedAt($value)
 * @method static Builder<static>|Contact whereEmail($value)
 * @method static Builder<static>|Contact whereFirstName($value)
 * @method static Builder<static>|Contact whereId($value)
 * @method static Builder<static>|Contact whereInterest($value)
 * @method static Builder<static>|Contact whereLastName($value)
 * @method static Builder<static>|Contact whereMembershipCompetitors($value)
 * @method static Builder<static>|Contact whereMembershipFamilyMembers($value)
 * @method static Builder<static>|Contact whereMembershipTotalCost($value)
 * @method static Builder<static>|Contact whereMembershipTrainingSessions($value)
 * @method static Builder<static>|Contact whereMessage($value)
 * @method static Builder<static>|Contact whereOwnerId($value)
 * @method static Builder<static>|Contact wherePhone($value)
 * @method static Builder<static>|Contact whereStatus($value)
 * @method static Builder<static>|Contact whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Contact extends Model
{
    use HasAuditLog;
    use HasFactory;

    /**
     * The statuses a contact may hold, mirroring the `contacts.status` enum
     * column. The single origin of the admin filter, of the status select and
     * of what `updateStatus()` accepts — a status reaching the model from
     * anywhere else is a bug, not a new state.
     *
     * @var list<string>
     */
    public const STATUSES = ['new', 'processed', 'rejected'];

    protected $casts = [
        'interest' => ContactReasonEnum::class,
        'age_category' => AgeCategoryEnum::class,
        'experience' => PlayerExperienceEnum::class,
        'wants_competition' => 'boolean',
        'preferred_days' => 'array',
        'family_can_drive' => 'boolean',
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'interest',
        'message',
        'membership_family_members',
        'membership_competitors',
        'membership_training_sessions',
        'membership_total_cost',
        'status',
        'age_category',
        'experience',
        'wants_competition',
        'preferred_days',
        'family_can_drive',
        'user_id',
    ];

    public static function getStatusStats(): array
    {
        return self::selectRaw("
        SUM(status = 'new') as totalNew,
        SUM(status = 'processed') as totalProcessed,
        SUM(status = 'rejected') as totalRejected")->first()->toArray();
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where('first_name', 'like', '%' . $value . '%')
            ->orWhere('last_name', 'like', '%' . $value . '%')
            ->orWhere('email', 'like', '%' . $value . '%')
            ->orWhere('message', 'like', '%' . $value . '%');
    }

    /**
     * Build the carry-over seed mapping the triage profile fields to the
     * target season subscription attribute names, for Phase 2 consumption
     * (pre-filling a member's first subscription).
     *
     * Only filled-in values are returned. The `is_competitive` and `can_drive`
     * keys are the destination subscription column names, not yet created.
     *
     * @return array{
     *     is_competitive?: bool,
     *     can_drive?: bool,
     *     experience?: string,
     *     age_category?: string,
     *     preferred_days?: array<int, string>
     * }
     */
    public function subscriptionSeed(): array
    {
        $seed = [];

        if ($this->wants_competition !== null) {
            $seed['is_competitive'] = $this->wants_competition;
        }

        if ($this->family_can_drive !== null) {
            $seed['can_drive'] = $this->family_can_drive;
        }

        if ($this->experience !== null) {
            $seed['experience'] = $this->experience->value;
        }

        if ($this->age_category !== null) {
            $seed['age_category'] = $this->age_category->value;
        }

        if ($this->preferred_days !== null) {
            $seed['preferred_days'] = $this->preferred_days;
        }

        return $seed;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
