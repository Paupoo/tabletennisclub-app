<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Club\Models;

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TableStateEnum;
use App\Domains\Shared\Traits\HasAuditLog;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use Database\Factories\Domains\ClubAdmin\Club\Models\RoomFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
 * @property int $capacity_for_tournaments
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Club> $clubs
 * @property-read int|null $clubs_count
 * @property-read Collection<int, Interclub> $interclubs
 * @property-read int|null $interclubs_count
 * @property-read Collection<int, Table> $tables
 * @property-read int|null $tables_count
 * @property-read Collection<int, Tournament> $tournaments
 * @property-read int|null $tournaments_count
 * @property-read Collection<int, Training> $training
 * @property-read int|null $training_count
 *
 * @method static RoomFactory factory($count = null, $state = [])
 * @method static Builder<static>|Room newModelQuery()
 * @method static Builder<static>|Room newQuery()
 * @method static Builder<static>|Room query()
 * @method static Builder<static>|Room whereAccessDescription($value)
 * @method static Builder<static>|Room whereBuildingName($value)
 * @method static Builder<static>|Room whereCapacityForInterclubs($value)
 * @method static Builder<static>|Room whereCapacityForTrainings($value)
 * @method static Builder<static>|Room whereCityCode($value)
 * @method static Builder<static>|Room whereCityName($value)
 * @method static Builder<static>|Room whereCreatedAt($value)
 * @method static Builder<static>|Room whereFloor($value)
 * @method static Builder<static>|Room whereId($value)
 * @method static Builder<static>|Room whereName($value)
 * @method static Builder<static>|Room whereStreet($value)
 * @method static Builder<static>|Room whereTotalPlayableTables($value)
 * @method static Builder<static>|Room whereTotalTables($value)
 * @method static Builder<static>|Room whereUpdatedAt($value)
 *
 * @property-read string $address
 * @property-read Collection<int, TrainingPack> $trainingPacks
 * @property-read int|null $training_packs_count
 * @property-read Collection<int, Training> $trainings
 * @property-read int|null $trainings_count
 *
 * @method static Builder<static>|Room search(string $value)
 *
 * @mixin Eloquent
 */
class Room extends Model
{
    use HasAuditLog;
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
        'capacity_for_tournaments',
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

    /**
     * The tables of the room that can be played on: all but those out of service.
     *
     * Read from the tables themselves, so it can never drift from what the
     * rooms screen shows. A table that needs repair still plays.
     */
    public function playableTablesCount(): int
    {
        return $this->relationLoaded('tables')
            ? $this->tables->filter(fn (Table $table): bool => $table->state->isPlayable())->count()
            : $this->tables()->whereNot('state', TableStateEnum::OUT_OF_SERVICE)->count();
    }

    public function scopeSearch(Builder $query, string $value): void
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

    public function trainingPacks(): HasMany
    {
        return $this->hasMany(TrainingPack::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    /**
     * The players a training pack takes when it sets no cap of its own: two
     * per table the room offers for a training.
     *
     * A room with no table recorded has nothing to cross the room typed in
     * with: that one is trusted as it stands.
     */
    public function trainingSeats(): int
    {
        $this->loadMissing('tables:id,room_id,state');

        $tables = $this->tables->isEmpty() ? $this->capacity_for_trainings : $this->usableTrainingCapacity();

        return 2 * $tables;
    }

    /**
     * What the room really offers for an official match.
     */
    public function usableMatchCapacity(): int
    {
        return min($this->capacity_for_interclubs, $this->playableTablesCount());
    }

    /**
     * What the room really offers for a tournament.
     */
    public function usableTournamentCapacity(): int
    {
        return min($this->capacity_for_tournaments, $this->playableTablesCount());
    }

    /**
     * What the room really offers for a training: the room typed in, or fewer
     * when tables are out of service.
     */
    public function usableTrainingCapacity(): int
    {
        return min($this->capacity_for_trainings, $this->playableTablesCount());
    }
}
