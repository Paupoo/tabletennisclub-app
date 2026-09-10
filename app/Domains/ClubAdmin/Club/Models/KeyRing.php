<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Club\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use Database\Factories\Domains\ClubAdmin\Club\Models\KeyRingFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A physical set of keys the club hands over, and gets back.
 *
 * It records a fact and grants nothing by itself: holding one opens the venue
 * door, never a screen. The ring exists whether or not anyone holds it, which
 * is the whole reason it is a row rather than a flag on the member.
 *
 * Numbers are labels meant to be engraved: they climb from the highest ever
 * issued and are never reused, so a retired n°3 stays the only n°3 the club has
 * had. Retiring is a soft delete that keeps the last holder on the row — that
 * is how you know whose pocket a lost ring went missing from.
 *
 * @property int $id
 * @property int $number
 * @property int|null $held_by_user_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $heldBy
 * @property-read string $label
 *
 * @method static KeyRingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KeyRing newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KeyRing newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KeyRing onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KeyRing query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KeyRing withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KeyRing withoutTrashed()
 *
 * @mixin Eloquent
 */
class KeyRing extends Model
{
    use HasAuditLog;
    use HasFactory;
    use SoftDeletes;

    protected $casts = [
        'number' => 'integer',
    ];

    protected $fillable = [
        'number',
        'held_by_user_id',
        'notes',
    ];

    /**
     * The next number to issue: one above the highest ever used.
     *
     * Retired rings keep their number, so taking one out of service never puts
     * its number back into circulation.
     */
    public static function nextNumber(): int
    {
        return (int) static::withTrashed()->max('number') + 1;
    }

    public function heldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by_user_id');
    }

    /**
     * How the ring is named everywhere a human reads it.
     */
    public function label(): string
    {
        return __('Key ring #:number', ['number' => $this->number]);
    }

    /**
     * The ring numbers itself as it is written.
     *
     * Reading the highest number when the caller builds the model is one read
     * too early: a factory making two rings at once builds both before saving
     * either, and both would claim the same number. Deciding at insert time is
     * the only moment the answer is still true.
     */
    protected static function booted(): void
    {
        static::creating(function (self $keyRing): void {
            $keyRing->number ??= static::nextNumber();
        });
    }
}
