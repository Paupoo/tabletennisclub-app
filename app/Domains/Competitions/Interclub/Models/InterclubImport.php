<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What one run of the federation calendar import did.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $season_id
 * @property bool $is_fresh
 * @property int $created_count
 * @property int $updated_count
 * @property int $unchanged_count
 * @property int $deleted_count
 * @property int $skipped_count
 * @property array<string, array<int, string>>|null $changes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Season $season
 * @property-read User|null $user
 */
class InterclubImport extends Model
{
    protected $casts = [
        'changes' => 'array',
        'is_fresh' => 'boolean',
    ];

    protected $fillable = [
        'changes',
        'created_count',
        'deleted_count',
        'is_fresh',
        'season_id',
        'skipped_count',
        'unchanged_count',
        'updated_count',
        'user_id',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
