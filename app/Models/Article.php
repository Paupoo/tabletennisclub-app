<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ArticlesCategoryEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'tags' => 'array',
        'status' => 'string',
        'is_public' => 'boolean',
        'user_id' => 'integer',
    ];

    protected $fillable = [
        'title',
        'slug',
        'content',
        'category',
        'image',
        'tags',
        'status',
        'is_public',
        'user_id',
    ];

    public function scopeSearch($query, $value): void
    {
        $query->where('title', 'like', '%' . $value . '%')
            ->orWhere('content', 'like', '%' . $value . '%');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
