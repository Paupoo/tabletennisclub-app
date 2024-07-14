<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_subscribed',
        'is_selected',
        'has_played',
    ]
}
