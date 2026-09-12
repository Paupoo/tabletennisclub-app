<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Services;

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationTemplate;
use App\Domains\Shared\Enums\Mutuality;

/**
 * Which insurers the club can actually serve today.
 *
 * The feature flag decides whether the domain exists in this environment; this
 * decides whether it can do its job, and it is recomputed rather than stored:
 * a seal deleted by mistake or a form that lost a label must take effect at
 * once, not at the next deploy.
 *
 * An insurer the club cannot serve is simply absent from the member's list. No
 * error, no half-filled document — the alternative is a member sending a
 * sealed page with the amount missing.
 */
final readonly class AttestationAvailability
{
    /** Whether the club has provided everything a stamped document needs. */
    public function isReady(): bool
    {
        return $this->missing() === [];
    }

    /**
     * What the club still has to provide, in words the settings screen shows.
     *
     * @return array<int, string>
     */
    public function missing(): array
    {
        return AttestationSetting::current()->missingRequirements();
    }

    /**
     * @return array<int, Mutuality>
     */
    public function offered(): array
    {
        if (! $this->isReady()) {
            return [];
        }

        $usable = AttestationTemplate::all()
            ->filter(fn (AttestationTemplate $template): bool => $template->isUsable())
            ->map(fn (AttestationTemplate $template): Mutuality => $template->mutuality)
            ->all();

        return Mutuality::inReadingOrder(array_filter(
            Mutuality::cases(),
            fn (Mutuality $mutuality): bool => $mutuality->acceptsClubAttestation()
                || in_array($mutuality, $usable, true),
        ));
    }
}
