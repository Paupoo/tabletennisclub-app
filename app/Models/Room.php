<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $building_name
 * @property string $street
 * @property string $city_code
 * @property string $city_name
 * @property string|null $floor
 * @property string|null $access_description
 * @property int $capacity_for_trainings
 * @property int $capacity_for_interclubs
 * @property int $total_tables
 * @property int $total_playable_tables
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Club> $clubs
 * @property-read int|null $clubs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Interclub> $interclubs
 * @property-read int|null $interclubs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Table> $tables
 * @property-read int|null $tables_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tournament> $tournaments
 * @property-read int|null $tournaments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Training> $training
 * @property-read int|null $training_count
 *
 * @method static \Database\Factories\RoomFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereAccessDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereBuildingName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereCapacityForInterclubs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereCapacityForTrainings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereCityCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereCityName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereFloor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereStreet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereTotalPlayableTables($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereTotalTables($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Room extends Model
{
    use HasFactory;

    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'building_name' => 'string',
        'street' => 'string',
        'city_code' => 'string',
        'city_name' => 'string',
        'floor' => 'string',
        'access_description' => 'string',
        'capacity_for_trainings' => 'integer',
        'capacity_for_interclubs' => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'building_name',
        'street',
        'city_code',
        'city_name',
        'floor',
        'access_description',
        'capacity_for_trainings',
        'capacity_for_interclubs',
    ];

    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class);
    }

    public function getAddressAttribute(): string
    {
        return $this->street . ', ' . $this->city_code . ' ' . $this->city_name;
    }

    public function interclubs(): HasMany
    {
        return $this->hasMany(Interclub::class);
    }

    public function scopeSearch($query, $value): void
    {
        $query->where('name', 'like', '%' . $value . '%')
            ->orWhere('building_name', 'like', '%' . $value . '%')
            ->orWhere('street', 'like', '%' . $value . '%')
            ->orWhere('city_code', 'like', '%' . $value . '%')
            ->orWhere('city_name', 'like', '%' . $value . '%');
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }

    public function tournaments(): BelongsToMany
    {
        return $this->belongsToMany(Tournament::class);
    }

    public function training(): HasMany
    {
        return $this->hasMany(Training::class);
    }
}
