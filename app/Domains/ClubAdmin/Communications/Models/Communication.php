<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Communications\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use Database\Factories\Domains\ClubAdmin\Communications\Models\CommunicationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A message the committee wrote to the club from the application.
 *
 * Kept for good — the club's record of what it said, and the draft of the same
 * message next season. The addresses it went to live in
 * {@see CommunicationRecipient}, which is pruned after two seasons.
 *
 * @property int $id
 * @property int|null $author_id
 * @property string $subject
 * @property string $body
 * @property string|null $reply_to
 * @property array<string, mixed> $criteria
 * @property list<string>|null $invitation_targets
 * @property int $member_count
 * @property int $recipient_count
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $author
 */
#[UseFactory(CommunicationFactory::class)]
class Communication extends Model
{
    use HasFactory;

    protected $casts = [
        'criteria' => 'array',
        'invitation_targets' => 'array',
        'member_count' => 'integer',
        'recipient_count' => 'integer',
        'sent_at' => 'datetime',
    ];

    protected $fillable = [
        'author_id',
        'subject',
        'body',
        'reply_to',
        'criteria',
        'invitation_targets',
        'member_count',
        'recipient_count',
        'sent_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasMany<CommunicationRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(CommunicationRecipient::class);
    }
}
