<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactReasonEnum;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $casts = [
        'interest' => ContactReasonEnum::class,
    ];

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

    public function scopeSearch($query, $value): void
    {
        $query->where('first_name', 'like', '%' . $value . '%')
            ->orWhere('last_name', 'like', '%' . $value . '%')
            ->orWhere('email', 'like', '%' . $value . '%')
            ->orWhere('message', 'like', '%' . $value . '%');
    }
}
