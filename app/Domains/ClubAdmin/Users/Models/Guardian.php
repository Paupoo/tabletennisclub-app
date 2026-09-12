<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Models;

use App\Domains\Shared\Casts\IbanCast;
use App\Domains\Shared\Support\IbanNormalizer;
use App\Domains\Shared\Traits\HasAuditLog;
use Database\Factories\Domains\ClubAdmin\Users\Models\GuardianFactory;
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
}
