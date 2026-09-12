<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Attestations;

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Withdraws a certificate the club can no longer stand behind.
 *
 * The member cannot ask twice in a season; this is the door the office has
 * when the rule meets reality — the wrong insurer was picked, a training pack
 * joined in January changed the sum, the member moved. Without it the first
 * such case would be filled in by hand, outside any record.
 *
 * The reason is required, not decorative: an attestation revoked without one
 * leaves the office unable to answer the member who asks why.
 *
 * The file goes; the row stays. The verification page has to keep answering —
 * that is the whole point of revoking rather than deleting — and whoever holds
 * the paper deserves to be told it no longer stands.
 */
final readonly class RevokeAttestation
{
    public function __invoke(MutualAttestation $attestation, string $reason, User $by): MutualAttestation
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(__('A reason is required to revoke an attestation.'));
        }

        // Already withdrawn: the first reason is the one that explains it, and
        // a second pass would overwrite the record of what actually happened.
        if ($attestation->isRevoked()) {
            return $attestation;
        }

        if ($attestation->path !== null) {
            Storage::disk('local')->delete($attestation->path);
        }

        $attestation->update([
            'path' => null,
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);

        activity()
            ->performedOn($attestation)
            ->causedBy($by)
            ->withProperties(['reason' => $reason])
            ->log('revoked');

        return $attestation;
    }
}
