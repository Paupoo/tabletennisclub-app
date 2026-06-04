<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Contact\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property array<array-key, mixed>|null $inputs
 * @property int $is_blocked
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\Domains\ClubAdmin\Contact\Models\SpamFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spam newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spam newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spam query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spam whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spam whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spam whereInputs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spam whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spam whereIsBlocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spam whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spam whereUserAgent($value)
 * @mixin \Eloquent
 */
final class Spam extends Model
{
    use HasFactory;

    protected $casts = [
        'inputs' => 'array',
    ];

    protected $fillable = [
        'ip',
        'user_agent',
        'inputs',
    ];

    protected $table = 'spams';
}
