<?php

declare(strict_types=1);

namespace App\Models\ClubAdmin\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    use HasFactory;

    protected $casts = [
        'balance' => 'integer',
    ];

    protected $fillable = [
        'name',
        'balance',
        'notes',
    ];

    public function currentBalance(): int
    {
        return $this->entries()->sum('amount');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CashRegisterEntry::class);
    }
}
