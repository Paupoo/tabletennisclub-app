<?php

declare(strict_types=1);

namespace App\Models\ClubAdmin\Contact;

use App\Enums\ContactReasonEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

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

    /**
     * @return array
     */
    public static function getStatusStats(): array
    {
        return self::selectRaw("
        SUM(status = 'new') as totalNew,
        SUM(status = 'pending') as totalPending,
        SUM(status = 'processed') as totalProcessed,
        SUM(status = 'rejected') as totalRejected")->first()->toArray();
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where('first_name', 'like', '%' . $value . '%')
            ->orWhere('last_name', 'like', '%' . $value . '%')
            ->orWhere('email', 'like', '%' . $value . '%')
            ->orWhere('message', 'like', '%' . $value . '%');
    }
}
