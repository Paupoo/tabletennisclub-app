<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_admin' => 'boolean',
        'is_comittee_member' => 'boolean',
        'is_competitor' => 'boolean',
        'has_debt' => 'boolean',
        'email' => 'string',
        'password' => 'string',
        'first_name' => 'string',
        'last_name' => 'string',
        'sex' => 'string',
        'phone_number' => 'string',
        'birthdate' => 'datetime:d-m-Y',
        'street' => 'string',
        'city_code' => 'string',
        'city_name' => 'string',
        'ranking' => 'string',
        'licence' => 'string',
        'force_list' => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'birthdate',
        'city_code',
        'city_name',
        'email',
        'first_name',
        'is_active',
        'is_admin',
        'is_comittee_member',
        'is_competitor',
        'last_name',
        'licence',
        'password',
        'phone_number',
        'ranking',
        'sex',
        'street',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function captainOf(): HasOne
    {
        return $this->hasOne(Team::class, 'captain_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function interclubs(): BelongsToMany
    {
        return $this->belongsToMany(Interclub::class)
            ->withPivot('is_subscribed', 'is_selected', 'has_played')
            ->as('registration')
            ->withTimestamps();
    }

    public function pools(): BelongsToMany
    {
        return $this->belongsToMany(Pool::class, 'pool_user');
    }

    /** Scopes */

    /**
     * Scope search to search by last or first name
     *
     * @param [type] $query
     * @param [type] $value
     * @return void
     */
    public function scopeSearch($query, $value)
    {
        $query->where('last_name', 'like', '%' . $value . '%')
            ->orWhere('first_name', 'like', '%' . $value . '%');
    }

    public function scopeUnregisteredUsers($query, $tournament)
    {
        return $query->whereDoesntHave('tournaments', function ($query) use ($tournament): void {
            $query->where('tournaments.id', $tournament->id);
        })->orderBy('last_name')->orderBy('first_name');
    }

    /**
     * Calculate user's age and store it into ->age attribute.
     */
    public function setAge(): self
    {
        if ($this->birthdate !== null) {
            $this->setAttribute('age', Carbon::parse($this->birthdate)->age);
        } else {
            $this->setAttribute('age', 'Unknown');
        }

        return $this;
    }

    /**
     * Capitalize 1 first letter of each words
     *
     * @param [type] $value
     * @return void
     */
    public function setFirstNameAttribute($value)
    {
        $cleaned_name = mb_convert_case($value, MB_CASE_TITLE);

        return $this->attributes['first_name'] = $cleaned_name;
    }

    /**
     * Capitalize 1 first letter of each words
     *
     * @param [type] $value
     * @return void
     */
    public function setLastNameAttribute($value)
    {
        $cleaned_name = mb_convert_case($value, MB_CASE_TITLE);

        return $this->attributes['last_name'] = $cleaned_name;
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    public function tournaments(): BelongsToMany
    {
        return $this->belongsToMany(Tournament::class, 'tournament_user');
    }

    public function trainings(): BelongsToMany
    {
        return $this->belongsToMany(Training::class);
    }
}
