<?php

declare(strict_types=1);

namespace App\Models\ClubAdmin\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    // TODO : implement TransactionFactory or remove the using

    protected $casts = [
        'amount' => 'float',
    ];

    protected $fillable = [
        'date',
        'description',
        'amount',
        'counterparty_name',
        'counterparty_bank_account',
        'structured_reference',
        'free_reference',
    ];

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'transaction_id');
    }

    public function refundPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'refund_transaction_id');
    }
}
