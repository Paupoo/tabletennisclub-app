<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'interest',
        'message',
        'membership_family_members',
        'membership_competitors',
        'membership_training_sessions',
        'membership_total_cost',
        'status',
    ];
}
