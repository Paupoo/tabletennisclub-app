<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_year',
        'end_year',
    ];

    protected $casts = [
        'name' => 'string',
        'start_year' => 'integer',
        'end_year' => 'integer',
    ];

    public function interclubs(): HasMany
    {
        return $this->hasMany(Interclub::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function leagues(): HasMany
    {
        return $this->hasMany(League::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }
}
