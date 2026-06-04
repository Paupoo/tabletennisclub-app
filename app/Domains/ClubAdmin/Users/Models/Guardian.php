<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Models;

use Database\Factories\Domains\ClubAdmin\Users\Models\GuardianFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guardian extends Model
{
    /** @use HasFactory<GuardianFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'iban',
    ];

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
