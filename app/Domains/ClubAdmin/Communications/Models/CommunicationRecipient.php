<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Communications\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use Database\Factories\Domains\ClubAdmin\Communications\Models\CommunicationRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One address a communication went to, frozen when it was sent.
 *
 * Answers "did the parents of Tom receive it?" and tells a retry which
 * addresses failed. It holds personal data, so it is pruned two seasons after
 * the sending, while the communication itself is kept.
 *
 * @property int $id
 * @property int $communication_id
 * @property string $email
 * @property list<int> $user_ids
 * @property string $status 'pending'|'sent'|'failed'
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Communication $communication
 */
#[UseFactory(CommunicationRecipientFactory::class)]
class CommunicationRecipient extends Model
{
    use HasFactory;
    use MassPrunable;

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_SENT = 'sent';

    protected $casts = [
        'user_ids' => 'array',
        'sent_at' => 'datetime',
    ];

    protected $fillable = [
        'communication_id',
        'email',
        'user_ids',
        'status',
        'error',
        'sent_at',
    ];

    /** @return BelongsTo<Communication, $this> */
    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }

    /**
     * The members this address spoke for, in the order they were recorded.
     *
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        return User::query()->whereIn('id', $this->user_ids)->orderBy('first_name')->orderBy('id')->get();
    }

    /**
     * Two seasons, then the addresses go.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subYears(2));
    }
}
