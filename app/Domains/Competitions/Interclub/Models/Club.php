<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Models;

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $name
 * @property int $is_active
 * @property bool $is_own_club
 * @property string $licence
 * @property string|null $street
 * @property string|null $city_code
 * @property string|null $city_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Room> $rooms
 * @property-read int|null $rooms_count
 * @property-read Collection<int, Team> $teams
 * @property-read int|null $teams_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\Domains\Competitions\Interclub\Models\ClubFactory factory($count = null, $state = [])
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
 * @property string|null $building_name
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $email_contact
 * @property string|null $phone_contact
 * @property string|null $bank_account
 * @property string|null $website_url
 * @property string|null $enterprise_number
 *
 * @method static Builder<static>|Club whereBankAccount($value)
 * @method static Builder<static>|Club whereBuildingName($value)
 * @method static Builder<static>|Club whereEmailContact($value)
 * @method static Builder<static>|Club whereEnterpriseNumber($value)
 * @method static Builder<static>|Club whereLatitude($value)
 * @method static Builder<static>|Club whereLongitude($value)
 * @method static Builder<static>|Club wherePhoneContact($value)
 * @method static Builder<static>|Club whereWebsiteUrl($value)
 *
 * @mixin \Eloquent
 */
class Club extends Model
{
    use HasAuditLog;
    use HasFactory;

    protected $casts = [
        'name' => 'string',
        'licence' => 'string',
        'is_own_club' => 'boolean',
        'street' => 'string',
        'city_code' => 'string',
        'city_name' => 'string',
        'building_name' => 'string',
        'latitude' => 'float',
        'longitude' => 'float',
        'email_contact' => 'string',
        'phone_contact' => 'string',
        'bic' => 'string',
        'bank_account' => 'string',
        'website_url' => 'string',
        'enterprise_number' => 'string',
    ];

    protected $fillable = [
        'name',
        'licence',
        'is_own_club',
        'street',
        'city_code',
        'city_name',
        'building_name',
        'latitude',
        'longitude',
        'phone_contact',
        'email_contact',
        'bic',
        'bank_account',
        'website_url',
        'enterprise_number',
    ];

    public static function forgetOwnClub(): void
    {
        Cache::forget('own_club');
    }

    public static function own(): ?self
    {
        return Cache::rememberForever('own_club', fn () => self::where('is_own_club', true)->first());
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class);
    }

    public function scopeOtherClubs(Builder $query): void
    {
        $query->where('is_own_club', false);
    }

    public function scopeOurClub(Builder $query): void
    {
        $query->where('is_own_club', true);
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
