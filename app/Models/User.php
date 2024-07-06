<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'licence',
        'ranking',
        'force_index',
        'is_active',
        'is_competitor',
        'has_debt',
        'birthday',
        'phone_number',
        'street',
        'city',
        'city_code',
        'role_id',
        'team_id'
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
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'first_name' => 'string',
        'last_name' => 'string',
        'email' => 'string',
        'licence' => 'integer',
        'ranking' => 'string',
        'force_index' => 'integer',
        'is_active' => 'boolean',
        'is_competitor' => 'boolean',
        'has_debt' => 'boolean',
        'birthday' => 'date',
        'phone_number' => 'string',
        'street' => 'string',
        'city' => 'string',
        'city_code' => 'string',
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

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
