<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;

/**
 * Who may see, issue and withdraw a certificate — and who may download one.
 *
 * Downloading is the one decision that is not about a duty: the member the
 * document names may always fetch it back, which is what keeps "one per
 * season" from turning into a trap the day they lose the file. A guardian
 * acting under proxy is authenticated *as* the ward, so this needs no clause
 * of its own for them.
 */
class MutualAttestationPolicy
{
    public function create(User $user): bool
    {
        return $user->can(Permission::AttestationsIssue->value);
    }

    public function delete(User $user, MutualAttestation $attestation): bool
    {
        return false;
    }

    /** The member it names, or the office. */
    public function download(User $user, MutualAttestation $attestation): bool
    {
        return $user->id === $attestation->user_id
            || $user->can(Permission::AttestationsView->value);
    }

    public function revoke(User $user, MutualAttestation $attestation): bool
    {
        return ! $attestation->isRevoked() && $user->can(Permission::AttestationsIssue->value);
    }

    public function update(User $user, MutualAttestation $attestation): bool
    {
        return false;
    }

    public function view(User $user, MutualAttestation $attestation): bool
    {
        return $this->download($user, $attestation);
    }

    /** The office's list of everything the club has certified. */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AttestationsView->value);
    }
}
