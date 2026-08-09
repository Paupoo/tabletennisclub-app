<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property-read User|null $createdBy
 * @property-read User|null $modifiedBy
 * @property-read Collection<int, BarProduct> $products
 * @property-read int|null $products_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarCategory whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarCategory whereModifiedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarCategory whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarCategory whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class BarCategory extends Model
{
    use HasAuditLog;

    // Only 'name' is fillable; created_by and modified_by are set automatically
    protected $fillable = ['name'];

    protected $table = 'bar_categories';

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    public function products(): HasMany
    {
        return $this->hasMany(BarProduct::class, 'category_id');
    }

    #[\Override]
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->created_by = auth()->id();
        });

        static::updating(function (self $model): void {
            $model->modified_by = auth()->id();
        });
    }
}
