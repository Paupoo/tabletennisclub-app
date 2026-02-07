<?php

declare(strict_types=1);

namespace App\Models\ClubPosts;

use App\Enums\NewsPostCategoryEnum;
use App\Enums\NewsPostStatusEnum;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'title' => 'string',
        'slug' => 'string',
        'content' => 'string',
        'category' => NewsPostCategoryEnum::class,
        'image' => 'string',
        'status' => NewsPostStatusEnum::class,
        'is_public' => 'boolean',
        'user_id' => 'integer',
    ];

    protected $fillable = [
        'title',
        'slug',
        'content',
        'category',
        'image',
        'status',
        'is_public',
        'user_id',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeSearch($query, $value): void
    {
        $query->where('title', 'like', '%' . $value . '%')
            ->orWhere('content', 'like', '%' . $value . '%');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeIsPublic($query): Builder {
        return $query->where('is_public', true);
    }

    public function scopeIsPrivate($query): Builder {
        return $query->where('is_public', false);
    }
}
