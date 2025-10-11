<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'date',
        'description',
        'amount',
        'counterparty_name',
        'counterparty_bank_account',
        'structured_reference',
        'free_reference',
    ];

    public function payment()
    {
        return $this->hasOne(Payment::class, 'transaction_id');
    }
}
