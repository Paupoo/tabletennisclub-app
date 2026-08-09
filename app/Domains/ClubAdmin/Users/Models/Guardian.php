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
    ];

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'iban',
    ];

    public function getIbanFormattedAttribute(): ?string
    {
        return IbanNormalizer::format($this->iban);
    }

    /**
     * The club member this guardian record represents, when the guardian is
     * also a registered user. Null for external (non-member) guardians.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The minors (users) this guardian is responsible for.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_user');
    }
}
