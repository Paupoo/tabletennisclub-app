<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Spam extends Model
{
    use HasFactory;
    protected $table = 'spams';
    protected $fillable = [
        'ip',
        'user_agent',
        'inputs',
    ];

    protected $casts = [
        'inputs' => 'array',
    ];
}
