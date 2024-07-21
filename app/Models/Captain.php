<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Captain extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'team_id',
        'is_active',
    ];

    protected $casts = [];

    public function team(): HasOne
    {
        return $this->hasOne(Team::class);
    }
}
