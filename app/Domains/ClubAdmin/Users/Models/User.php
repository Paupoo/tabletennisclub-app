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
use App\Domains\Shared\Casts\IbanCast;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Support\AddressNormalizer;
use App\Domains\Shared\Support\IbanNormalizer;
use App\Domains\Shared\Traits\HasAuditLog;
use App\Domains\Trainings\Models\Training;
use App\Observers\UserObserver;
use Carbon\Carbon;
use Database\Factories\Domains\ClubAdmin\Users\Models\UserFactory;
use Eloquent;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
use Illuminate\Notifications\Notification;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $email Null for a managed account: see make_users_email_nullable
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
 * @property int|null $force_list_women
 * @property int|null $force_list_veterans
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
 * @method static EloquentBuilder<static>|User whereForceListWomen($value)
 * @method static EloquentBuilder<static>|User whereForceListVeterans($value)
 * @method static EloquentBuilder<static>|User whereHasDebt($value)
 * @method static EloquentBuilder<static>|User whereId($value)
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
 * @property string|null $theme
 * @property string|null $guardian_phone_number
 * @property string|null $photo
 * @property CommitteeRolesEnum|null $committee_role
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
 * @method static EloquentBuilder<static>|User whereGender($value)
 * @method static EloquentBuilder<static>|User whereGuardianPhoneNumber($value)
 * @method static EloquentBuilder<static>|User whereIban($value)
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
    use HasApiTokens, HasAuditLog, HasFactory, HasRoles, Notifiable, SoftDeletes;

    public const int INVITATION_LINK_VALIDITY_DAYS = 7;

    /**
     * Age from which a player belongs to the veterans' force list, reached at
     * the end of the season. Shared by the recalculation action and every
     * query that filters veterans so the rule can never diverge.
     */
    public const int VETERAN_AGE = 40;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'has_key' => 'boolean',
        'email' => 'string',
        'password' => 'hashed',
        'first_name' => 'string',
        'last_name' => 'string',
        'gender' => Gender::class,
        'phone_number' => 'string',
        'guardian_phone_number' => 'string',
        'iban' => IbanCast::class,
        'photo' => 'string',
        'birthdate' => 'datetime:d-m-Y',
        'street' => 'string',
        'city_code' => 'string',
        'city_name' => 'string',
        'ranking' => 'string',
        'licence' => 'string',
        'force_list' => 'integer',
        'force_list_women' => 'integer',
        'force_list_veterans' => 'integer',
        'avatar_url' => 'string',
        'theme' => 'string',
        'committee_role' => CommitteeRolesEnum::class,
        'deleted_at' => 'datetime',
        'last_invited_at' => 'datetime',
        'federation_synced_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'gdpr_erasure_requested_at' => 'datetime',
        'notification_preferences' => 'array',
        'contact_visibility' => 'array',
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
        'force_list_women',
        'force_list_veterans',
        'has_key',
        'medical_certificate_path',
        'parental_consent_path',
        'updated_by',
        'last_invited_at',
        'gdpr_erasure_requested_at',
        'notification_preferences',
        'contact_visibility',
        'federation_licence_type',
        'federation_synced_at',
        'member_import_id',
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
     * The users column holding the force-list position for a league category,
     * for use in query ordering (`orderBy(User::forceListColumn($category))`).
     */
    public static function forceListColumn(LeagueCategory|string|null $category): string
    {
        return match (self::resolveCategory($category)) {
            LeagueCategory::WOMEN => 'force_list_women',
            LeagueCategory::VETERANS => 'force_list_veterans',
            default => 'force_list',
        };
    }

    public function articles(): HasMany
    {
        return $this->hasMany(NewsPost::class);
    }

    /**
     * Whether the user belongs to the club-admin management group.
     *
     * Kept as a named shorthand for the contacts duty, which is what the two
     * remaining gates (`manage-contacts`, `manage-season`) actually ask about.
     * It used to read the statutory title; the title decides nothing now.
     */
    public function canManageClubAdmin(): bool
    {
        return $this->can(Permission::ContactsManage->value);
    }

    /*
     * canManageFinances() lived here. It was one of three divergent definitions of
     * "treasurer" — this one granted the president, the dashboard's did not, and
     * the cash register used a third. Callers now ask for the permission that
     * matches what they are about to do (fines.issue, payments.reconcile,
     * cash_register.holder.change), so the question has a single answer.
     */

    /**
     * Whether the user may read the club-wide audit log.
     *
     * The supervision duty. It used to be the statutory title — full admins plus
     * the president, vice-president, secretary and treasurer. The title decides
     * nothing now.
     */
    public function canViewAuditLog(): bool
    {
        return $this->can(Permission::AuditLogView->value);
    }

    public function captainOf(): HasOne
    {
        return $this->hasOne(Team::class, 'captain_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * The address to actually write to, which is not always the member's own.
     *
     * `email` identifies a login. It answers "who is connecting", and it is null
     * for every member who cannot connect on their own — a child too young to
     * own a mailbox, a sibling affiliated under one parent's address, an adult
     * who simply has no email. Those members are reached through their guardian.
     *
     * Every mail and every notification must resolve its recipient here rather
     * than reading `email` directly; `email` is reserved for the places that
     * *identify* someone — authentication, password reset, uniqueness.
     *
     * The day a managed member gets an address of their own, filling the column
     * is the whole migration: the fallback below stops applying by itself.
     */
    public function contactEmail(): ?string
    {
        if ($this->email !== null) {
            return $this->email;
        }

        return $this->guardians
            ->first(fn (Guardian $guardian): bool => $guardian->email !== null)
            ?->email;
    }

    /**
     * Central rule for whether $viewer may see this member's contact $field:
     * the member themself, any admin/committee member, or anyone once the
     * member has opted in. The captain exception lives in the selection screen,
     * intentionally outside this method.
     *
     * @param  'phone'|'email'|'address'  $field
     */
    public function contactVisibleTo(User $viewer, string $field): bool
    {
        return $viewer->is($this)
            || $viewer->can(Permission::UsersView->value)
            || $this->sharesContact($field);
    }

    /**
     * Whether the club's answer to "does this member play competition" and the
     * federation's disagree.
     *
     * They are allowed to: a member takes up competition, or stops, and the club
     * knows before the listing does. What must not happen is the difference
     * passing unseen by whoever accepts an affiliation — accepting it is what
     * registers the member with the federation for the season.
     *
     * Silent when the federation has said nothing about this member, and when it
     * said it without a date: an undated claim cannot be weighed against the
     * club's own, and a listing two days old is not the argument a listing ten
     * months old is.
     */
    public function contradictsFederationLicenceType(bool $isCompetitive): bool
    {
        if ($this->federation_licence_type === null || $this->federation_synced_at === null) {
            return false;
        }

        return $isCompetitive !== ($this->federation_licence_type === 'JO');
    }

    public function familyGroups(): BelongsToMany
    {
        return $this->belongsToMany(FamilyGroup::class, 'family_group_user');
    }

    /**
     * Other users sharing any of this user's family groups, excluding themself.
     *
     * @return Collection<int, User>
     */
    public function familyMembers(): Collection
    {
        $groupIds = $this->familyGroups()->pluck('family_groups.id');

        if ($groupIds->isEmpty()) {
            return new Collection;
        }

        return self::query()
            ->whereHas('familyGroups', fn (EloquentBuilder $query) => $query->whereIn('family_groups.id', $groupIds))
            ->whereKeyNot($this->id)
            ->distinct()
            ->get();
    }

    /**
     * The stored force-list position for a given league category: the women's
     * or veterans' sub-list, or the general list for MEN / unknown categories.
     */
    public function forceListFor(LeagueCategory|string|null $category): ?int
    {
        return match ($this->resolveCategory($category)) {
            LeagueCategory::WOMEN => $this->force_list_women,
            LeagueCategory::VETERANS => $this->force_list_veterans,
            default => $this->force_list,
        };
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

    public function getIbanFormattedAttribute(): ?string
    {
        return IbanNormalizer::format($this->iban);
    }

    public function getIsActiveAttribute(): bool
    {
        if (array_key_exists('is_active', $this->attributes)) {
            return (bool) $this->attributes['is_active'];
        }

        $seasonId = Season::current()?->id;

        if ($this->relationLoaded('subscriptions')) {
            return $this->subscriptions
                ->where('season_id', $seasonId)
                ->whereIn('status', ['confirmed', 'paid'])
                ->isNotEmpty();
        }

        return $this->subscriptions()
            ->where('season_id', $seasonId)
            ->whereIn('status', ['confirmed', 'paid'])
            ->exists();
    }

    public function getIsCompetitorAttribute(): bool
    {
        // Use pre-loaded exists value (e.g. from ->withExists(['subscriptions as is_competitor' => ...])) if available
        if (array_key_exists('is_competitor', $this->attributes)) {
            return (bool) $this->attributes['is_competitor'];
        }

        $seasonId = Season::current()?->id;

        // Un compétiteur est d'abord un membre actif : une inscription annulée
        // ou en attente ne fait de personne un compétiteur du club.
        if ($this->relationLoaded('subscriptions')) {
            return $this->subscriptions
                ->where('season_id', $seasonId)
                ->whereIn('status', ['confirmed', 'paid'])
                ->where('is_competitive', true)
                ->isNotEmpty();
        }

        return $this->subscriptions()
            ->where('season_id', $seasonId)
            ->active()
            ->where('is_competitive', true)
            ->exists();
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_user');
    }

    /**
     * Whether the member filled every profile field the club requires:
     * birthdate, phone and full address. Gender is NOT NULL in the schema
     * (invited users get a default the wizard asks them to confirm), so it
     * cannot signal incompleteness. Incomplete profiles are redirected to
     * the onboarding wizard by the profile.complete middleware.
     *
     * The phone number follows the email address: a member reached through a
     * guardian holds neither, and the guardian carries both. Requiring it here
     * would park every such member in the onboarding wizard for good, asking
     * for a number that is on file — under the guardian.
     */
    public function hasCompleteProfile(): bool
    {
        return $this->birthdate !== null
            && (filled($this->phone_number) || $this->hasGuardian())
            && filled($this->street)
            && filled($this->city_code)
            && filled($this->city_name)
            && ! $this->requiresGuardian();
    }

    public function hasGuardian(): bool
    {
        return $this->guardians()->exists();
    }

    /**
     * Whether the member still has a subscription awaiting payment.
     * Signals the committee to reconcile finances before anonymizing.
     */
    public function hasPendingPayments(): bool
    {
        return $this->subscriptions()->pendingPayment()->exists();
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

        return $this->last_invited_at->greaterThan(now()->subDays(self::INVITATION_LINK_VALIDITY_DAYS))
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

    /**
     * Whether the member qualifies for the veterans' force list, i.e. turns
     * {@see self::VETERAN_AGE} on or before the given season's end (defaults to
     * the current season). Mirrors {@see self::scopeVeteran()} for in-memory use.
     */
    public function isVeteran(?Season $season = null): bool
    {
        $season ??= Season::current();

        if ($season === null || $this->birthdate === null) {
            return false;
        }

        return $this->birthdate <= $season->end_at->copy()->subYears(self::VETERAN_AGE);
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

    /**
     * IDs of every user whose payments this member may see and settle: themself
     * plus the users they are the guardian of (i.e. users linked to a guardian
     * record whose account is this member). Hierarchical only — no symmetric
     * family-group sharing between adults.
     *
     * @return array<int, int>
     */
    public function payableUserIds(): array
    {
        return self::query()
            ->whereHas('guardians', fn (EloquentBuilder $query) => $query->where('guardians.user_id', $this->id))
            ->pluck('id')
            ->push($this->id)
            ->unique()
            ->values()
            ->all();
    }

    public function pools(): BelongsToMany
    {
        return $this->belongsToMany(Pool::class, 'pool_user');
    }

    public function requiresGuardian(): bool
    {
        return $this->isMinor() && ! $this->hasGuardian();
    }

    /**
     * Notifications are messages, so they follow the contact address rather than
     * the login. Overriding the routing here covers every notification the
     * application sends at once — Notifiable would otherwise read `email`
     * straight off the model and write to nobody for a managed account.
     *
     * Mailables sent through `Mail::to($user)` do *not* pass through here: they
     * read the `email` attribute directly, so those call sites resolve their
     * recipient with {@see self::contactEmail()} themselves.
     */
    public function routeNotificationForMail(?Notification $notification = null): ?string
    {
        return $this->contactEmail();
    }

    public function scopeActive(EloquentBuilder $query): EloquentBuilder
    {
        $seasonId = Season::current()?->id;

        return $query->whereHas('subscriptions', fn (EloquentBuilder $q) => $q
            ->where('season_id', $seasonId)
            ->whereIn('status', ['confirmed', 'paid'])
        );
    }

    /**
     * Grown members the club cannot hand a login to.
     *
     * A child reached through a parent is the arrangement working as intended;
     * an adult in the same position is a loose end. Nobody chose it for them —
     * the federation listed them under a family address, or they were recorded
     * by hand — and nothing happens on their eighteenth birthday. Closing it
     * means asking them for an address, which only a human can do, so this is
     * what makes them findable.
     *
     * A member of unknown age counts as an adult: several are on the roster
     * without a birthdate, and treating them as children would hide the very
     * accounts nobody is looking after.
     */
    public function scopeAdultWithoutOwnAddress(Builder $query): Builder
    {
        return $query
            ->whereNull('email')
            ->where(fn (Builder $unknownOrGrown): Builder => $unknownOrGrown
                ->whereNull('birthdate')
                ->orWhereDate('birthdate', '<=', now()->subYears(18))
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

    /**
     * Compétiteurs de la saison en cours.
     *
     * Doit rester aligné sur getIsCompetitorAttribute() : UserObserver lit
     * l'attribut, RecalculateForceListAction lit ce scope. Les faire diverger
     * laisse des force_list à null.
     */
    public function scopeCompetitor(EloquentBuilder $query): EloquentBuilder
    {
        $seasonId = Season::current()?->id;

        return $query->whereHas('subscriptions', fn (EloquentBuilder $q) => $q
            ->where('season_id', $seasonId)
            ->whereIn('status', ['confirmed', 'paid'])
            ->where('is_competitive', true)
        );
    }

    /**
     * Members eligible for interclub team building: those holding a validated
     * (confirmed) or paid *competitive* licence for the current season — via
     * {@see self::scopeCompetitor()} — and carrying an actual ranking. NA
     * (unranked) players are out of scope for the force list and for teams.
     */
    public function scopeInterclubEligible(EloquentBuilder $query): EloquentBuilder
    {
        return $query->competitor()->where('ranking', '!=', 'NA');
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
     * Reusable member search: splits the query on spaces and hyphens, and
     * requires every word to appear in the first name, last name or email
     * (case-insensitive). This handles compound names in either order —
     * "Jean Pierre" finds "Jean-Pierre", "Jean Van" finds "Jean-Pierre
     * Van Oudenhove" — while staying database-agnostic (per-column LIKE,
     * no CONCAT). Shared by the admin directory and the member directory.
     */
    public function scopeSearchName(EloquentBuilder $query, string $search): void
    {
        $terms = preg_split('/[\s-]+/', mb_strtolower(trim($search)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($terms as $term) {
            $like = '%' . $term . '%';

            $query->where(function (EloquentBuilder $subQuery) use ($like): void {
                $subQuery->whereRaw('LOWER(first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            });
        }
    }

    /**
     * This scope allows searching for users by terms in their first or last name.
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

    /**
     * Members eligible for the veterans' force list: born early enough to turn
     * {@see self::VETERAN_AGE} by the given season's end (defaults to the
     * current season). Query-side counterpart of {@see self::isVeteran()}.
     */
    public function scopeVeteran(EloquentBuilder $query, ?Season $season = null): EloquentBuilder
    {
        $season ??= Season::current();

        if ($season === null) {
            return $query->whereRaw('1 = 0');
        }

        $cutoff = $season->end_at->copy()->subYears(self::VETERAN_AGE);

        return $query->whereNotNull('birthdate')->where('birthdate', '<=', $cutoff->toDateString());
    }

    /**
     * SQL counterpart of {@see self::hasCompleteProfile()}, including the
     * exemption a guardian grants on the phone number. The two are one rule
     * expressed twice and are tested against each other.
     */
    public function scopeWithIncompleteProfile(EloquentBuilder $query): EloquentBuilder
    {
        return $query->where(fn (EloquentBuilder $q) => $q
            ->where(fn (EloquentBuilder $missingPhone) => $missingPhone
                ->whereNull('phone_number')
                ->whereDoesntHave('guardians')
            )
            ->orWhereNull('street')
            ->orWhereNull('city_code')
            ->orWhereNull('city_name')
            ->orWhereNull('birthdate')
        );
    }

    /**
     * Members standing at a given point of {@see self::invitationStatus()}.
     *
     * The two are one rule expressed twice — once per member for the badge, once
     * in SQL for the list — so they are tested against each other. Nothing new is
     * stored: "imported but never written to" is simply `not_invited`, which is
     * how the secretary finds the members an import brought in.
     *
     * @param  'not_invited'|'pending'|'expired'|'active'  $state
     */
    public function scopeWithInvitationState(Builder $query, string $state): Builder
    {
        $cutoff = now()->subDays(self::INVITATION_LINK_VALIDITY_DAYS);

        return match ($state) {
            'active' => $query->whereNotNull('email_verified_at'),
            'not_invited' => $query->whereNull('email_verified_at')->whereNull('last_invited_at'),
            'pending' => $query->whereNull('email_verified_at')
                ->whereNotNull('last_invited_at')
                ->where('last_invited_at', '>', $cutoff),
            'expired' => $query->whereNull('email_verified_at')
                ->whereNotNull('last_invited_at')
                ->where('last_invited_at', '<=', $cutoff),
            default => $query,
        };
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
     * The federation exports localities in capitals, and a locality is written
     * once and read for years. See {@see AddressNormalizer}.
     */
    public function setCityNameAttribute(?string $value): ?string
    {
        return $this->attributes['city_name'] = AddressNormalizer::titleCase($value);
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

    /**
     * Same rule as the locality: a street is built out of locality names as
     * often as not. See {@see AddressNormalizer}.
     */
    public function setStreetAttribute(?string $value): ?string
    {
        return $this->attributes['street'] = AddressNormalizer::titleCase($value);
    }

    /**
     * Whether this member has opted in to sharing a contact field with the wider
     * membership. Opt-in model: a missing key means hidden.
     *
     * @param  'phone'|'email'|'address'  $field
     */
    public function sharesContact(string $field): bool
    {
        return (bool) ($this->contact_visibility[$field] ?? false);
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

    /**
     * Whether the member accepts the given optional notification family.
     * Opt-out model: no stored preference (or unknown key) means enabled.
     * Transactional notifications (payments, deadlines, GDPR…) never
     * consult this and are always delivered.
     */
    public function wantsNotification(string $preference): bool
    {
        return (bool) ($this->notification_preferences[$preference] ?? true);
    }

    /**
     * Normalize a category given as an enum or as its raw case-name (as stored
     * on leagues and used by the team builder) to a {@see LeagueCategory}.
     */
    private static function resolveCategory(LeagueCategory|string|null $category): ?LeagueCategory
    {
        return $category instanceof LeagueCategory
            ? $category
            : LeagueCategory::fromName($category);
    }
}
