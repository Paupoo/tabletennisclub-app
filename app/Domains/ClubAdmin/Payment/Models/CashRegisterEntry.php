<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashRegisterEntry extends Model
{
    use HasFactory;

    protected $casts = [
        'amount' => 'integer',
    ];

    protected $fillable = [
        'cash_register_id',
        'amount',
        'reason',
        'payable_type',
        'payable_id',
        'recorded_by_id',
        'notes',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
