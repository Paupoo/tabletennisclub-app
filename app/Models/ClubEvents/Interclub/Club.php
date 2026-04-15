<?php

declare(strict_types=1);

namespace App\Models\ClubEvents\Interclub;

use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $is_active
 * @property string $licence
 * @property string|null $street
 * @property string|null $city_code
 * @property string|null $city_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClubAdmin\Club\Room> $rooms
 * @property-read int|null $rooms_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClubEvents\Interclub\Team> $teams
 * @property-read int|null $teams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClubAdmin\Users\User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\ClubEvents\Interclub\ClubFactory factory($count = null, $state = [])
 * @method static Builder<static>|Club newModelQuery()
 * @method static Builder<static>|Club newQuery()
 * @method static Builder<static>|Club otherClubs()
 * @method static Builder<static>|Club ourClub()
 * @method static Builder<static>|Club query()
 * @method static Builder<static>|Club whereCityCode($value)
 * @method static Builder<static>|Club whereCityName($value)
 * @method static Builder<static>|Club whereCreatedAt($value)
 * @method static Builder<static>|Club whereId($value)
 * @method static Builder<static>|Club whereIsActive($value)
 * @method static Builder<static>|Club whereLicence($value)
 * @method static Builder<static>|Club whereName($value)
 * @method static Builder<static>|Club whereStreet($value)
 * @method static Builder<static>|Club whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Club extends Model
{
    use HasFactory;

    protected $casts = [
        'name' => 'string',
        'licence' => 'string',
        'street' => 'string',
        'city_code' => 'string',
        'city_name' => 'string',
        'building_name' => 'string',
        'latitude' => 'float',
        'longitude' => 'float',
        'email_contact' => 'string',
        'phone_contact' => 'string',
        'bank_account' => 'string',
        'website_url' => 'string',
        'enterprise_number' => 'string'
    ];

    protected $fillable = [
        'name',
        'licence',
        'street',
        'city_code',
        'city_name',
        'building_name',
        'latitude',
        'longitude',
        'phone_contact',
        'email_contact',
        'bank_account',
        'website_url',
        'enterprise_number'
    ];

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class);
    }

    public function scopeOtherClubs(Builder $query): void
    {
        $query->whereNot('licence', '=', config('app.club_licence'));
    }

    public function scopeOurClub(Builder $query): void
    {
        $query->where('licence', '=', config('app.club_licence'));
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
