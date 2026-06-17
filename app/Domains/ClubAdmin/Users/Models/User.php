<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Traits\HasAuditLog;
use App\Domains\Trainings\Models\Training;
use App\Http\Controllers\ClubAdmin\DashboardController;
use App\Observers\UserObserver;
use Carbon\Carbon;
use Database\Factories\Domains\ClubAdmin\Users\Models\UserFactory;
use Eloquent;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $is_admin
 * @property bool $is_committee_member
 * @property bool $is_selector
 * @property string $email
 * @property string|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string $first_name
 * @property string $last_name
 * @property string $sex
 * @property string|null $phone_number
 * @property string|null $iban
 * @property \Illuminate\Support\Carbon|null $birthdate
 * @property string|null $street
 * @property string|null $city_code
 * @property string|null $city_name
 * @property string $ranking
 * @property string|null $licence
 * @property int|null $force_list
 * @property int $club_id
 * @property-read Team|null $captainOf
 * @property-read Club|null $club
 * @property-read Collection<int, Interclub> $interclubs
 * @property-read int|null $interclubs_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Pool> $pools
 * @property-read int|null $pools_count
 * @property-read Collection<int, Team> $teams
 * @property-read int|null $teams_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read Collection<int, Tournament> $tournaments
 * @property-read int|null $tournaments_count
 * @property-read Collection<int, Training> $trainings
 * @property-read int|null $trainings_count
 *
 * @method static UserFactory factory($count = null, $state = [])
 * @method static EloquentBuilder<static>|User newModelQuery()
 * @method static EloquentBuilder<static>|User newQuery()
 * @method static EloquentBuilder<static>|User query()
 * @method static EloquentBuilder<static>|User search($value)
 * @method static EloquentBuilder<static>|User unregisteredUsers($tournament)
 * @method static EloquentBuilder<static>|User whereBirthdate($value)
 * @method static EloquentBuilder<static>|User whereCityCode($value)
 * @method static EloquentBuilder<static>|User whereCityName($value)
 * @method static EloquentBuilder<static>|User whereClubId($value)
 * @method static EloquentBuilder<static>|User whereCreatedAt($value)
 * @method static EloquentBuilder<static>|User whereEmail($value)
 * @method static EloquentBuilder<static>|User whereEmailVerifiedAt($value)
 * @method static EloquentBuilder<static>|User whereFirstName($value)
 * @method static EloquentBuilder<static>|User whereForceList($value)
 * @method static EloquentBuilder<static>|User whereHasDebt($value)
 * @method static EloquentBuilder<static>|User whereId($value)
 * @method static EloquentBuilder<static>|User whereIsAdmin($value)
 * @method static EloquentBuilder<static>|User whereIsCommitteeMember($value)
 * @method static EloquentBuilder<static>|User whereLastName($value)
 * @method static EloquentBuilder<static>|User whereLicence($value)
 * @method static EloquentBuilder<static>|User wherePassword($value)
 * @method static EloquentBuilder<static>|User wherePhoneNumber($value)
 * @method static EloquentBuilder<static>|User whereRanking($value)
 * @method static EloquentBuilder<static>|User whereRememberToken($value)
 * @method static EloquentBuilder<static>|User whereSex($value)
 * @method static EloquentBuilder<static>|User whereStreet($value)
 * @method static EloquentBuilder<static>|User whereUpdatedAt($value)
 *
 * @property string|null $avatar_url
 * @property Gender $gender
 * @property int $emails_notifications
 * @property string|null $family_id
 * @property int $is_family_owner
 * @property string|null $theme
 * @property string|null $guardian_phone_number
 * @property string|null $photo
 * @property CommitteeRolesEnum|null $committee_role
 * @property bool $is_coach
 * @property string|null $medical_certificate_path
 * @property string|null $parental_consent_path
 * @property-read Collection<int, NewsPost> $articles
 * @property-read int|null $articles_count
 * @property-read Collection<int, User> $dependents
 * @property-read int|null $dependents_count
 * @property-read string $full_name
 * @property-read Collection<int, User> $guardians
 * @property-read int|null $guardians_count
 * @property-read Collection<int, Meeting> $meetings
 * @property-read int|null $meetings_count
 * @property-read Collection<int, Season> $seasons
 * @property-read int|null $seasons_count
 * @property-read Collection<int, Subscription> $subscriptions
 * @property-read int|null $subscriptions_count
 *
 * @method static EloquentBuilder<static>|User affiliatedForCurrentSeason()
 * @method static EloquentBuilder<static>|User active()
 * @method static EloquentBuilder<static>|User paid()
 * @method static EloquentBuilder<static>|User unpaid()
 * @method static EloquentBuilder<static>|User competitor()
 * @method static EloquentBuilder<static>|User searchTerms(string $search)
 * @method static EloquentBuilder<static>|User whereAvatarUrl($value)
 * @method static EloquentBuilder<static>|User whereCommitteeRole($value)
 * @method static EloquentBuilder<static>|User whereEmailsNotifications($value)
 * @method static EloquentBuilder<static>|User whereFamilyId($value)
 * @method static EloquentBuilder<static>|User whereGender($value)
 * @method static EloquentBuilder<static>|User whereGuardianPhoneNumber($value)
 * @method static EloquentBuilder<static>|User whereIban($value)
 * @method static EloquentBuilder<static>|User whereIsCoach($value)
 * @method static EloquentBuilder<static>|User whereIsFamilyOwner($value)
 * @method static EloquentBuilder<static>|User whereMedicalCertificatePath($value)
 * @method static EloquentBuilder<static>|User whereParentalConsentPath($value)
 * @method static EloquentBuilder<static>|User wherePhoto($value)
 * @method static EloquentBuilder<static>|User whereTheme($value)
 *
 * @mixin Eloquent
 */
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasAuditLog, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_admin' => 'boolean',
        'is_committee_member' => 'boolean',
        'is_selector' => 'boolean',
        'is_coach' => 'boolean',
        'has_key' => 'boolean',
        'email' => 'string',
        'password' => 'hashed',
        'first_name' => 'string',
        'last_name' => 'string',
        'gender' => Gender::class,
        'phone_number' => 'string',
        'guardian_phone_number' => 'string',
        'photo' => 'string',
        'birthdate' => 'datetime:d-m-Y',
        'street' => 'string',
        'city_code' => 'string',
        'city_name' => 'string',
        'ranking' => 'string',
        'licence' => 'string',
        'force_list' => 'integer',
        'avatar_url' => 'string',
        'theme' => 'string',
        'committee_role' => CommitteeRolesEnum::class,
        'deleted_at' => 'datetime',
        'last_invited_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'gdpr_erasure_requested_at' => 'datetime',
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
        'is_admin',
        'is_committee_member',
        'is_selector',
        'last_name',
        'licence',
        'password',
        'phone_number',
        'iban',
        'guardian_phone_number',
        'photo',
        'ranking',
        'gender',
        'street',
        'avatar_url',
        'theme',
        'committee_role',
        'force_list',
        'is_coach',
        'has_key',
        'medical_certificate_path',
        'parental_consent_path',
        'updated_by',
        'last_invited_at',
        'gdpr_erasure_requested_at',
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
        return $this->hasMany(NewsPost::class);
    }

    /**
     * Whether the user belongs to the club-admin management group.
     *
     * Mirrors the `showSecretary` rule used in {@see DashboardController}:
     * full admins plus the secretary/president/vice-president committee roles may
     * manage club-admin operations (contact triage, templates, …). Other committee
     * members can only view.
     */
    public function canManageClubAdmin(): bool
    {
        return $this->is_admin || in_array($this->committee_role, [
            CommitteeRolesEnum::SECRETARY,
            CommitteeRolesEnum::PRESIDENT,
            CommitteeRolesEnum::VICE_PRESIDENT,
        ], true);
    }

    /**
     * Whether the user may read the club-wide audit log.
     *
     * Full platform admins plus the president, vice-president, secretary and
     * treasurer committee roles. The plain administrator committee role does
     * not grant access on its own.
     */
    public function canViewAuditLog(): bool
    {
        return $this->is_admin || in_array($this->committee_role, [
            CommitteeRolesEnum::PRESIDENT,
            CommitteeRolesEnum::VICE_PRESIDENT,
            CommitteeRolesEnum::SECRETARY,
            CommitteeRolesEnum::TREASURER,
        ], true);
    }

    public function captainOf(): HasOne
    {
        return $this->hasOne(Team::class, 'captain_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getHasPaidAttribute(): bool
    {
        if (array_key_exists('has_paid', $this->attributes)) {
            return (bool) $this->attributes['has_paid'];
        }

        $seasonId = Season::current()?->id;

        if ($this->relationLoaded('subscriptions')) {
            return $this->subscriptions
                ->where('season_id', $seasonId)
                ->where('status', 'paid')
                ->isNotEmpty();
        }

        return $this->subscriptions()
            ->where('season_id', $seasonId)
            ->where('status', 'paid')
            ->exists();
    }

    public function getIsCompetitorAttribute(): bool
    {
        // Use pre-loaded exists value (e.g. from ->withExists(['subscriptions as is_competitor' => ...])) if available
        if (array_key_exists('is_competitor', $this->attributes)) {
            return (bool) $this->attributes['is_competitor'];
        }

        $seasonId = Season::current()?->id;

        if ($this->relationLoaded('subscriptions')) {
            return $this->subscriptions
                ->where('season_id', $seasonId)
                ->where('is_competitive', true)
                ->isNotEmpty();
        }

        return $this->subscriptions()
            ->where('season_id', $seasonId)
            ->where('is_competitive', true)
            ->exists();
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_user');
    }

    public function hasGuardian(): bool
    {
        return $this->guardians()->exists();
    }

    public function heldCashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class, 'held_by_user_id');
    }

    public function interclubs(): BelongsToMany
    {
        return $this->belongsToMany(Interclub::class)
            ->withPivot('is_subscribed', 'is_selected', 'has_played')
            ->as('registration')
            ->withTimestamps();
    }

    public function invitationStatus(): string
    {
        if ($this->email_verified_at !== null) {
            return 'active';
        }

        if ($this->last_invited_at === null) {
            return 'not_invited';
        }

        return $this->last_invited_at->greaterThan(now()->subHours(48))
            ? 'pending'
            : 'expired';
    }

    public function isAffiliatedForCurrentSeason(): bool
    {
        $season = Season::current();
        if (! $season) {
            return false;
        }

        return $this->subscriptions()
            ->where('season_id', $season->id)
            ->whereIn('status', ['pending', 'confirmed', 'paid'])
            ->exists();
    }

    public function isMinor(): bool
    {
        return $this->birthdate !== null && Carbon::parse($this->birthdate)->age < 18;
    }

    public function meetings(): BelongsToMany
    {
        return $this->belongsToMany(Meeting::class)
            ->withPivot(['status', 'invitation_sent_at', 'response_at'])
            ->withTimestamps();
    }

    /**
     * Retrieve the contact this user was onboarded from, if any.
     *
     * Phase 2 uses this to recover the carry-over seed via
     * {@see Contact::subscriptionSeed()} when pre-filling the first subscription.
     */
    public function originatingContact(): ?Contact
    {
        return Contact::where('user_id', $this->id)->latest()->first();
    }

    public function pools(): BelongsToMany
    {
        return $this->belongsToMany(Pool::class, 'pool_user');
    }

    public function requiresGuardian(): bool
    {
        return $this->isMinor() && ! $this->hasGuardian();
    }

    public function scopeActive(EloquentBuilder $query): EloquentBuilder
    {
        $seasonId = Season::current()?->id;

        return $query->whereHas('subscriptions', fn (EloquentBuilder $q) => $q
            ->where('season_id', $seasonId)
            ->whereIn('status', ['confirmed', 'paid'])
        );
    }

    public function scopeAffiliatedForCurrentSeason(EloquentBuilder $query): EloquentBuilder
    {
        $seasonId = Season::current()?->id;

        return $query->whereHas('subscriptions', fn (EloquentBuilder $q) => $q
            ->where('season_id', $seasonId)
            ->whereIn('status', ['pending', 'confirmed', 'paid'])
        );
    }

    public function scopeCompetitor(EloquentBuilder $query): EloquentBuilder
    {
        $seasonId = Season::current()?->id;

        return $query->whereHas('subscriptions', fn (EloquentBuilder $q) => $q
            ->where('season_id', $seasonId)
            ->where('is_competitive', true)
        );
    }

    public function scopePaid(EloquentBuilder $query): EloquentBuilder
    {
        $seasonId = Season::current()?->id;

        return $query->whereHas('subscriptions', fn (EloquentBuilder $q) => $q
            ->where('season_id', $seasonId)
            ->where('status', 'paid')
        );
    }

    /** Scopes */

    /**
     * Scope search to search by last or first name
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where('last_name', 'like', '%' . $value . '%')
            ->orWhere('first_name', 'like', '%' . $value . '%');
    }

    /**
     * This scope allows searching for users by terms in their first or last name.
     *
     * @param  mixed  $query
     */
    public function scopeSearchTerms(Builder $query, string $search): void
    {
        $terms = collect(explode(' ', strtolower($search)))
            ->filter();

        foreach ($terms as $term) {
            $query->where(function (EloquentBuilder $subQuery) use ($term): void {
                $subQuery->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$term}%"]);
            });
        }
    }

    public function scopeUnpaid(EloquentBuilder $query): EloquentBuilder
    {
        $seasonId = Season::current()?->id;

        return $query->whereDoesntHave('subscriptions', fn (EloquentBuilder $q) => $q
            ->where('season_id', $seasonId)
            ->where('status', 'paid')
        );
    }

    public function scopeUnregisteredUsers(Builder $query, Tournament $tournament): Builder
    {
        return $query->whereDoesntHave('tournaments', function (Builder $query) use ($tournament): void {
            $query->where('tournaments.id', $tournament->id);
        })->orderBy('last_name')->orderBy('first_name');
    }

    public function scopeWithIncompleteProfile(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('phone_number')
            ->orWhereNull('street')
            ->orWhereNull('city_code')
            ->orWhereNull('city_name')
            ->orWhereNull('birthdate')
        );
    }

    public function seasons(): BelongsToMany
    {
        return $this->belongsToMany(Season::class, 'subscriptions')
            ->withPivot('amount_due', 'is_competitive')
            ->withTimestamps();
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
     */
    public function setFirstNameAttribute(string $value): string
    {
        $cleaned_name = mb_convert_case($value, MB_CASE_TITLE);

        return $this->attributes['first_name'] = $cleaned_name;
    }

    /**
     * Capitalize 1 first letter of each words
     */
    public function setLastNameAttribute(string $value): string
    {
        $cleaned_name = mb_convert_case($value, MB_CASE_TITLE);

        return $this->attributes['last_name'] = $cleaned_name;
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
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
        return $this->belongsToMany(Training::class)->withPivot('status')->withTimestamps();
    }
}
