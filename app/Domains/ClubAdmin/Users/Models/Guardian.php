<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Models;

use App\Domains\Shared\Casts\IbanCast;
use App\Domains\Shared\Support\IbanNormalizer;
use App\Domains\Shared\Traits\HasAuditLog;
use Database\Factories\Domains\ClubAdmin\Users\Models\GuardianFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guardian extends Model
{
    use HasAuditLog;

    /** @use HasFactory<GuardianFactory> */
    use HasFactory;

    protected $casts = [
        'iban' => IbanCast::class,
        'last_invited_at' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'iban',
        'last_invited_at',
    ];

    /**
     * A guardian holding an account reads their details from it, so the member
     * is always at hand wherever a guardian is.
     *
     * @var list<string>
     */
    protected $with = ['member'];

    /**
     * A short fingerprint of the address on file, carried by the invitation link
     * so that the link dies with the address it was mailed to.
     */
    public function addressFingerprint(): string
    {
        return mb_substr(hash_hmac('sha256', mb_strtolower(trim((string) $this->email)), (string) config('app.key')), 0, 16);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getIbanFormattedAttribute(): ?string
    {
        return IbanNormalizer::format($this->iban);
    }

    /** Whether this guardian already signs in with an account of their own. */
    public function hasAccount(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Where this guardian stands on the way to holding a proxy.
     *
     * A ward with no address of their own is reached through their guardians, so
     * this is what the ward's own row in the members list really reports. Four
     * answers, in the order the office cares about them: something to send
     * (`actionable`), a link already out (`waiting`), a guardian who can already
     * act (`ready`), and the one real dead end — a guardian with neither an
     * account nor an address (`unreachable`).
     *
     * @return 'actionable'|'waiting'|'ready'|'unreachable'
     */
    public function invitationStage(): string
    {
        if ($this->hasAccount()) {
            return match ($this->member?->invitationStatus()) {
                'active' => 'ready',
                'pending' => 'waiting',
                'not_invited', 'expired' => 'actionable',
                default => 'unreachable',
            };
        }

        if (blank($this->email)) {
            return 'unreachable';
        }

        return $this->isWaitingOnInvitation() ? 'waiting' : 'actionable';
    }

    /** Whether the link this guardian was sent is still worth waiting on. */
    public function isWaitingOnInvitation(): bool
    {
        return ! $this->hasAccount()
            && $this->last_invited_at !== null
            && $this->last_invited_at->greaterThan(now()->subDays(User::INVITATION_LINK_VALIDITY_DAYS));
    }

    /**
     * The club member this guardian record represents, when the guardian is
     * also a registered user. Null for external (non-member) guardians.
     *
     * @return BelongsTo<User, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The minors (users) this guardian is responsible for.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_user');
    }

    /**
     * A corrected address voids the link already mailed, so the guardian goes
     * back among those the office has to invite.
     */
    protected static function booted(): void
    {
        static::updating(function (Guardian $guardian): void {
            if ($guardian->isDirty('email') && ! $guardian->isDirty('last_invited_at')) {
                $guardian->last_invited_at = null;
            }
        });
    }

    /**
     * The details of a guardian who holds an account live on that account: the
     * sheet only links them to their wards. A guardian with no account is the
     * sheet itself.
     *
     * @return Attribute<?string, never>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $this->member instanceof User ? $this->member->email : $value,
        );
    }

    /** @return Attribute<string, never> */
    protected function firstName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => $this->member instanceof User ? $this->member->first_name : (string) $value,
        );
    }

    /** @return Attribute<?string, never> */
    protected function iban(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $this->member instanceof User ? $this->member->iban : IbanNormalizer::normalize($value),
        );
    }

    /** @return Attribute<string, never> */
    protected function lastName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => $this->member instanceof User ? $this->member->last_name : (string) $value,
        );
    }

    /** @return Attribute<?string, never> */
    protected function phone(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $this->member instanceof User ? $this->member->phone_number : $value,
        );
    }
}
