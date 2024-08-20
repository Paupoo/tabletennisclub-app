<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\Ranking;
use App\Enums\Sex;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

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

    /**
     * Calculate user's age and store it into ->age attribute.
     *
     * @return self
     */
    public function setAge(): self
    {
        if ($this->birthdate !== null)
        {
            $this->setAttribute('age', Carbon::parse($this->birthdate)->age);
        } else {
            $this->setAttribute('age', 'Unknown');
        }

        return $this;
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    public function captainOf(): HasOne
    {
        return $this->hasOne(Team::class, 'captain_id');
    }

    public function interclubs(): BelongsToMany
    {
        return $this->belongsToMany(Interclub::class)
            ->withPivot('is_subscribed', 'is_selected', 'has_played')
            ->as('registration')
            ->withTimestamps();
    }

    public function trainings(): BelongsToMany
    {
        return $this->belongsToMany(Training::class);
    }
}
