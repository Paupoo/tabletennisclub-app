<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'title' => 'string',
        'content' => 'string',
        'user_id' => 'string',
    ];

    protected $fillable = [
        'title',
        'content',
        'user_id',
        'status',
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
}
