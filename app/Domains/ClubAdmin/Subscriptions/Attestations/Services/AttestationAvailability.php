<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Services;

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationTemplate;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Templates\AnchorMaps;
use App\Domains\Shared\Enums\AttestationKind;
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
 *
 * Every question takes the kind of document: an insurer's camp form and its
 * affiliation form are two files, and holding one says nothing of the other.
 */
final readonly class AttestationAvailability
{
    /**
     * Whether the document asks if the participant is recognised as disabled.
     *
     * Partenamut's camp form does, in the organiser's block, and pays more when
     * the answer is yes.
     */
    public function asksAboutDisability(Mutuality $mutuality, AttestationKind $kind = AttestationKind::Affiliation): bool
    {
        return in_array('disabled_yes', $this->printedFields($mutuality, $kind), true);
    }

    /**
     * Whether this insurer's form has a box for the mutual membership number.
     *
     * Only Partenamut asks for it. Showing the field to everybody made members
     * hunt for a number five documents out of six will never print, and marking
     * it optional on the one form that needs it sent it out blank.
     */
    public function asksForMutualNumber(Mutuality $mutuality, AttestationKind $kind = AttestationKind::Affiliation): bool
    {
        return in_array('member_mutual_number', $this->printedFields($mutuality, $kind), true);
    }

    /**
     * Whether the document will state the national register number.
     *
     * Every form but Partenamut has a place for it, and so does the club's own
     * attestation — which is what an insurer with no usable form falls back to.
     */
    public function asksForNationalRegisterNumber(Mutuality $mutuality, AttestationKind $kind = AttestationKind::Affiliation): bool
    {
        $fields = $this->printedFields($mutuality, $kind);

        if ($fields === []) {
            return true;
        }

        return array_any($fields, fn ($field): bool => str_starts_with($field, 'member_nrn'));
    }

    /**
     * Whether the number can be left out.
     *
     * The club's own camp attestation goes to whoever the family chooses — an
     * employer, a CPAS — and most of them have no use for it. Every form that
     * has a box for it needs it filled.
     */
    public function isNationalRegisterNumberOptional(Mutuality $mutuality, AttestationKind $kind): bool
    {
        return $kind === AttestationKind::Camp && $this->printedFields($mutuality, $kind) === [];
    }

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
    public function offered(AttestationKind $kind = AttestationKind::Affiliation): array
    {
        if (! $this->isReady()) {
            return [];
        }

        $usable = AttestationTemplate::where('kind', $kind->value)
            ->get()
            ->filter(fn (AttestationTemplate $template): bool => $template->isUsable())
            ->map(fn (AttestationTemplate $template): Mutuality => $template->mutuality)
            ->all();

        return Mutuality::inReadingOrder(array_filter(
            Mutuality::cases(),
            fn (Mutuality $mutuality): bool => $mutuality->acceptsClubAttestation($kind)
                || in_array($mutuality, $usable, true),
        ));
    }

    /**
     * The insurer's own form, when the club holds one it can still fill.
     *
     * The single place that decides between an official form and the club's own
     * attestation: the wizard asks for what the document will print, and the
     * action prints it. Two copies of this rule would eventually disagree, and
     * a member would be asked for a number that never reaches the paper.
     */
    public function officialFormFor(Mutuality $mutuality, AttestationKind $kind = AttestationKind::Affiliation): ?AttestationTemplate
    {
        if ($mutuality === Mutuality::Other || AnchorMaps::for($mutuality, $kind) === []) {
            return null;
        }

        $template = AttestationTemplate::where('mutuality', $mutuality->value)->where('kind', $kind->value)->first();

        return $template instanceof AttestationTemplate && $template->isUsable() ? $template : null;
    }

    /**
     * @return array<int, string> Empty when the club's own attestation is used.
     */
    public function printedFields(Mutuality $mutuality, AttestationKind $kind = AttestationKind::Affiliation): array
    {
        return $this->officialFormFor($mutuality, $kind) instanceof AttestationTemplate
            ? array_keys(AnchorMaps::for($mutuality, $kind))
            : [];
    }
}
