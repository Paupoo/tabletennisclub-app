<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Attestations;

use App\Data\Attestation\AttestationData;
use App\Data\Attestation\MemberIdentifiers;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationTemplate;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\ClubAttestationRenderer;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\OfficialFormRenderer;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationEligibility;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationFieldValues;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\BuildAttestationData;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Mutuality;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Renders a form the way it will come out, without issuing anything.
 *
 * Until now the office uploaded a seal and a signature and had no way to see
 * what they looked like on an insurer's page — it had to trust the millimetres
 * and wait for a member to report a stamp printed over the amount. That is how
 * three real defects survived: a stamp at a third of its stated size, a club
 * name written across the sentence that asked for it, and an amount landing on
 * the word « de » the form prints itself.
 *
 * Nothing is written: no row, no file, no reference. The rehearsal carries the
 * viewer's own identity, because a page full of placeholder names says nothing
 * about how a real one will sit — and a SPÉCIMEN watermark, because a preview
 * that looked like the real thing would reach an envelope eventually.
 */
final readonly class PreviewAttestation
{
    public function __construct(
        private AttestationEligibility $eligibility,
        private BuildAttestationData $builder,
        private AttestationFieldValues $values,
        private OfficialFormRenderer $officialForm,
        private ClubAttestationRenderer $clubAttestation,
    ) {}

    /**
     * @return string The finished PDF, for streaming straight back.
     */
    public function __invoke(User $viewer, Mutuality $mutuality): string
    {
        $season = Season::where('is_active', true)->first();

        if (! $season instanceof Season) {
            throw new RuntimeException(__('No season is open, so there is nothing to certify.'));
        }

        $settings = AttestationSetting::current();
        $data = $this->dataFor($viewer, $season);

        // A reference that cannot be confused with an issued one, and a
        // verification link that leads nowhere: nothing was recorded to verify.
        $reference = 'SPÉCIMEN';
        $identifiers = new MemberIdentifiers('00.00.00-000.00');

        $template = AttestationTemplate::where('mutuality', $mutuality->value)->first();

        if ($mutuality === Mutuality::Other || ! $template instanceof AttestationTemplate || ! $template->isUsable()) {
            return $this->clubAttestation->render(
                $data,
                $identifiers,
                $settings,
                $reference,
                route('attestations.verify', ['token' => 'specimen']),
                specimen: true,
            );
        }

        return $this->officialForm->render(
            Storage::disk('local')->path($template->path),
            $mutuality,
            $this->values->for($data, $mutuality, $identifiers, $reference),
            $settings,
            specimen: true,
        );
    }

    /**
     * The viewer's own affiliation when they have one, a placeholder otherwise.
     *
     * Eligibility is deliberately not enforced: whoever checks the layout is
     * usually a secretary who has not paid their own cotisation yet, and
     * refusing them the rehearsal would defeat its purpose. Their figures are
     * used when they exist, because a real amount shows how a long one sits.
     */
    private function dataFor(User $viewer, Season $season): AttestationData
    {
        $affiliation = $this->eligibility->for($viewer, $season)->affiliation;

        return $affiliation instanceof Subscription
            ? $this->builder->for($affiliation)
            : $this->builder->sampleFor($viewer, $season);
    }
}
