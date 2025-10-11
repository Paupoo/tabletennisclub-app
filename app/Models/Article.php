<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ArticlesCategoryEnum;
use App\Enums\ArticlesStatusEnum;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'title' => 'string',
        'slug' => 'string',
        'content' => 'string',
        'category' => ArticlesCategoryEnum::class,
        'image' => 'string',
        'status' => ArticlesStatusEnum::class,
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

    public function scopeIsPrivate($query): Builder
    {
        return $query->where('is_public', false);
    }

    public function scopeIsPublic($query): Builder
    {
        return $query->where('is_public', true);
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
}
