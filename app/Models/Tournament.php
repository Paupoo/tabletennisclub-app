<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Tournament extends Model
{
    /** @use HasFactory<\Database\Factories\TournamentFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'price',
        'total_users',
        'max_users',
        'status',
        'has_handicap_points',
    ];

    protected $casts = [
        'name',
        'start_date' => 'datetime:Y-m-d\TH:i',
        'end_date' => 'datetime:Y-m-d\TH:i',
        'price' => MoneyCast::class,
        'total_users' => 'integer',
        'max_users' => 'integer',
        'status' => 'string',
        'has_handicap_points' => 'boolean',
        ];

    /* Relations */

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['has_paid', 'matches_won', 'sets_won', 'points_won'])
            ->withTimestamps();
    }

    public function pools(): HasMany
    {
        return $this->hasMany(Pool::class);
    }

    public function rooms(): BelongsToMany
    {
        return $this->BelongsToMany(Room::class);
    }

    public function tables(): BelongsToMany
    {
        return $this->belongsToMany(Table::class)
            ->withPivot([
                'is_table_free',
                'tournament_match_id',
                'match_started_at',
            ])
            ->using(TableTournament::class)
            ->withTimestamps();
            
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);            
    }

    /** Scopes */

    /**
     * Scope search to search by last or first name
     *
     * @param [type] $query
     * @param [type] $value
     * @return void
     */
    public function scopeSearch($query, $value) {
        $query->where('name', 'like', '%' . $value . '%')
            ->orWhere('price', 'like', '%' . $value . '%');
    }

}
