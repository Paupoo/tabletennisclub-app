<?php
    declare(strict_types=1);

    namespace App\Models;

    use Illuminate\Database\Eloquent\Casts\Attribute;
    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Relations\MorphTo;

    class Payment extends Model
    {
        use HasFactory;
        
        protected $fillable = [
            'reference',
            'amount_due',
            'amount_paid',
            'status',
            'transaction_id',
        ];

        protected $casts = [
            'amount_due' => 'integer',   // stocké en centimes
            'amount_paid' => 'integer',  // stocké en centimes
        ];

        // Accessor pour les prix
        public function amountDue(): Attribute
        {
            return Attribute::make(
                get: fn (int $value): float => round($value / 100, 2) ,
                set: fn (int|float $value): int => (int) $value * 100 ,
            );
        }

        public function amountPaid(): Attribute
        {
            return Attribute::make(
                get: fn (int $value): float => round($value / 100, 2) ,
                set: fn (int|float $value): int => (int) $value * 100 ,
            );
        }
        
        /**
         * Relation polymorphique vers l'entité payée
         */
        public function payable(): MorphTo
        {
            return $this->morphTo();
        }
    }
