<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'licence',
        'street',
        'city_code',
        'city_name',
    ];

    protected $casts = [
        'name' => 'string',
        'licence' => 'string',
        'street' => 'string',
        'city_code' => 'string',
        'city_name' => 'string',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class);
    }

    public function scopeOurClub(Builder $query): void
    {
        $query->where('licence', '=', config('app.club_licence'));
    }

    public function scopeOtherClubs(Builder $query): void
    {
        $query->whereNot('licence', '=', config('app.club_licence'));
    }
}
