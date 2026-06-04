<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Models;

use Database\Factories\Domains\ClubAdmin\Users\Models\GuardianFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guardian extends Model
{
    /** @use HasFactory<GuardianFactory> */
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'iban',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_user');
    }
}
