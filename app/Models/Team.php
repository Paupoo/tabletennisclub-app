<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'captain',
    ];

    public function user() :HasOne
    {
        return $this->hasOne(User::class);
    }
}
