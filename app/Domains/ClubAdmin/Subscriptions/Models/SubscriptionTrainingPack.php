<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The `subscription_training_pack` row: one member's enrolment in one pack.
 *
 * It exists to give the columns a type. Everything that touches a training
 * enrolment reads `$pack->pivot->status`, `->starts_on`, `->override_amount`,
 * and static analysis had no idea what any of them were — the errors were
 * carried in the PHPStan baseline instead (issue #67).
 *
 * **No casts on purpose.** Without `using()`, these rows were hydrated by the
 * bare {@see Pivot}, which casts nothing: `starts_on` and `ends_on` come back
 * as strings, `confirmation_deadline` as a string, `override_amount` as
 * whatever PDO returns. Declaring casts here would change what every existing
 * call site receives, in the same commit as a typing change — so the types
 * below document what is actually returned rather than what would be tidier.
 * Adding casts is a separate, testable change.
 *
 * The many raw `DB::table('subscription_training_pack')` queries bypass Eloquent
 * altogether and are unaffected by any of this.
 *
 * @property int $id
 * @property int $subscription_id
 * @property int $training_pack_id
 * @property string $status enrolled | waiting | offered | left | cancelled
 * @property int|null $waitlist_position
 * @property string|null $confirmation_deadline
 * @property string|null $starts_on
 * @property string|null $ends_on
 * @property int|string|null $override_amount centimes, as every amount in the app
 * @property string|null $override_reason
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class SubscriptionTrainingPack extends Pivot
{
    protected $table = 'subscription_training_pack';
}
