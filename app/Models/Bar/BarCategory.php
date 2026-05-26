<?php

declare(strict_types=1);

namespace App\Models\Bar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarCategory extends Model
{
    protected $table = 'bar_categories';

    // Only 'name' is fillable; created_by and modified_by are set automatically
    protected $fillable = ['name'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->created_by = auth()->id();
        });

        static::updating(function ($model) {
            $model->modified_by = auth()->id();
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(BarProduct::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }
}
