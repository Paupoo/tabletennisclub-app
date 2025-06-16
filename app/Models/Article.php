<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'title',
        'content',
        'author',
        'status',
    ];

    protected $casts = [
        'title' => 'string',
        'content' => 'string',
        'author' => 'string',
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
