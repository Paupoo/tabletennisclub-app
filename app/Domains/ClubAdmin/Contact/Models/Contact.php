<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Contact\Models;

use App\Domains\Shared\Enums\ContactReasonEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
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
 * @mixin \Eloquent
 */
class Contact extends Model
{
    use HasFactory;

    protected $casts = [
        'interest' => ContactReasonEnum::class,
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
    ];

    public static function getStatusStats(): array
    {
        return self::selectRaw("
        SUM(status = 'new') as totalNew,
        SUM(status = 'pending') as totalPending,
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
}
