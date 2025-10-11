<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Spam extends Model
{
    use HasFactory;

    protected $casts = [
        'inputs' => 'array',
    ];

    protected $fillable = [
        'ip',
        'user_agent',
        'inputs',
    ];

    protected $table = 'spams';
}
