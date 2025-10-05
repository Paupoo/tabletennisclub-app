<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\Sex;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $is_active
 * @property bool $is_admin
 * @property bool $is_committee_member
 * @property bool $is_competitor
 * @property bool $has_paid
 * @property string $email
 * @property string|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string $first_name
 * @property string $last_name
 * @property string $sex
 * @property string|null $phone_number
 * @property \Illuminate\Support\Carbon|null $birthdate
 * @property string|null $street
 * @property string|null $city_code
 * @property string|null $city_name
 * @property string $ranking
 * @property string|null $licence
 * @property int|null $force_list
 * @property int $club_id
 * @property-read \App\Models\Team|null $captainOf
 * @property-read \App\Models\Club|null $club
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Interclub> $interclubs
 * @property-read int|null $interclubs_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Pool> $pools
 * @property-read int|null $pools_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $teams
 * @property-read int|null $teams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tournament> $tournaments
 * @property-read int|null $tournaments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Training> $trainings
 * @property-read int|null $trainings_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User search($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User unregisteredUsers($tournament)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBirthdate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCityCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCityName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereForceList($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereHasDebt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsCommitteeMember($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsCompetitor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLicence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRanking($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSex($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStreet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable implements MustVerifyEmail
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
        'is_committee_member' => 'boolean',
        'is_competitor' => 'boolean',
        'has_paid' => 'boolean',
        'email' => 'string',
        'password' => 'hashed',
        'first_name' => 'string',
        'last_name' => 'string',
        'sex' => Sex::class,
        'phone_number' => 'string',
        'birthdate' => 'datetime:d-m-Y',
        'street' => 'string',
        'city_code' => 'string',
        'city_name' => 'string',
        'ranking' => 'string',
        'licence' => 'string',
        'force_list' => 'integer',
        'avatar_url' => 'string',
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
        'has_paid',
        'is_active',
        'is_admin',
        'is_committee_member',
        'is_competitor',
        'last_name',
        'licence',
        'password',
        'phone_number',
        'ranking',
        'sex',
        'street',
        'avatar_url',
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

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function captainOf(): HasOne
    {
        return $this->hasOne(Team::class, 'captain_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_guardian',
            'guardian_id',
            'user_id'
        );
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_guardian',
            'user_id',
            'guardian_id'
        );
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

    public function scopeHasPaid($query): Builder
    {
        return $query->where('has_paid', true);
    }

    public function scopeIsActive($query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeIsCompetitor($query): Builder
    {
        return $query->where('is_competitor', true);
    }

    /** Scopes */

    /**
     * Scope search to search by last or first name
     *
     * @param [type] $query
     * @param [type] $value
     */
    public function scopeSearch($query, $value): void
    {
        $query->where('last_name', 'like', '%' . $value . '%')
            ->orWhere('first_name', 'like', '%' . $value . '%');
    }

    /**
     * This scope allows searching for users by terms in their first or last name.
     *
     * @param  mixed  $query
     */
    public function scopeSearchTerms($query, string $search): void
    {
        $terms = collect(explode(' ', strtolower($search)))
            ->filter();

        foreach ($terms as $term) {
            $query->where(function ($subQuery) use ($term): void {
                $subQuery->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$term}%"]);
            });
        }
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
    public function setFirstNameAttribute($value): string
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
    public function setLastNameAttribute($value): string
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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class); 
    }
}
