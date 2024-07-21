<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\Ranking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'is_active',
        'is_competitor',
        'has_debt',
        'email',
        'password',
        'first_name',
        'last_name',
        'phone_number',
        'birthday',
        'street',
        'city_code',
        'city_name',
        'ranking',
        'licence',
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
        'phone_number' => 'string',
        'birthday' => 'datetime:d-m-Y',
        'street' => 'string',
        'city_code' => 'string',
        'city_name' => 'string',
        'ranking' => Ranking::class,
        'licence' => 'string',
        'force_index' => 'integer',
    ];

    /**
     * Capitalize 1 first letter of each words
     *
     * @param [type] $value
     * @return void
     */
    public function setFirstNameAttribute($value)
    {
        return $this->attributes['first_name'] = mb_convert_case($value, MB_CASE_TITLE);
    }

    /**
     * Capitalize 1 first letter of each words
     *
     * @param [type] $value
     * @return void
     */
    public function setLastNameAttribute($value)
    {
        return $this->attributes['last_name'] = mb_convert_case($value, MB_CASE_TITLE);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function competitions(): BelongsToMany
    {
        return $this->belongsToMany(Interclub::class)
            ->withPivot('is_subscribed','is_selected','has_played')
            ->withTimestamps();
    }

    public function trainings(): BelongsToMany
    {
        return $this->belongsToMany(Training::class);
    }
}
