<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Attestations;

use App\Data\Attestation\AttestationData;
use App\Data\Attestation\MemberIdentifiers;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationTemplate;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\ClubAttestationRenderer;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\OfficialFormRenderer;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationAvailability;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationEligibility;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationFieldValues;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\BuildAttestationData;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\AttestationRefusal;
use App\Domains\Shared\Enums\Mutuality;
use App\Exceptions\AttestationNotAllowed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Certifies one affiliation, once per season.
 *
 * Everything the document says is copied onto the row as it is written. A
 * member who joins a training pack in January changes what they owe, and an
 * attestation issued in October has to keep saying what it said — the club put
 * its seal to that sentence, not to a query.
 *
 * The "once per season" rule is checked inside the transaction rather than by
 * a unique index: what must be unique is a *live* attestation, and neither
 * MySQL nor SQLite treats NULL as a value in a unique key.
 */
final readonly class IssueAttestation
{
    public function __construct(
        private AttestationAvailability $availability,
        private AttestationEligibility $eligibility,
        private BuildAttestationData $builder,
        private AttestationFieldValues $values,
        private OfficialFormRenderer $officialForm,
        private ClubAttestationRenderer $clubAttestation,
    ) {}

    public function __invoke(
        User $member,
        Season $season,
        Mutuality $mutuality,
        MemberIdentifiers $identifiers = new MemberIdentifiers,
        ?User $issuedBy = null,
    ): MutualAttestation {
        $verdict = $this->eligibility->for($member, $season);

        if (! $verdict->allowed) {
            throw new AttestationNotAllowed($verdict->refusal);
        }

        return DB::transaction(function () use ($member, $season, $mutuality, $identifiers, $issuedBy, $verdict): MutualAttestation {
            $alreadyHeld = MutualAttestation::query()
                ->where('user_id', $member->id)
                ->where('season_id', $season->id)
                ->live()
                ->lockForUpdate()
                ->exists();

            if ($alreadyHeld) {
                throw new AttestationNotAllowed(AttestationRefusal::AlreadyIssued);
            }

            $affiliation = $verdict->affiliation;
            $data = $this->builder->for($affiliation);
            $settings = AttestationSetting::current();

            $reference = $this->nextReference($season);
            $token = strtolower((string) Str::ulid());
            $url = route('attestations.verify', ['token' => $token]);

            $pdf = $this->renderFor($mutuality, $data, $identifiers, $settings, $reference, $url);

            $path = 'attestations/' . $season->id . '/' . $reference . '.pdf';
            Storage::disk('local')->put($path, $pdf);

            return MutualAttestation::create([
                'user_id' => $member->id,
                'subscription_id' => $affiliation->id,
                'season_id' => $season->id,
                'mutuality' => $mutuality->value,
                'reference' => $reference,
                'token' => $token,
                'path' => $path,
                'amount_certified' => $data->amountPaid,
                'period_from' => $data->periodFrom,
                'period_to' => $data->periodTo,
                'signatory_name' => $data->signatoryName,
                'discipline' => $data->discipline,
                'issued_by_user_id' => $issuedBy?->id,
                'issued_at' => now(),
            ]);
        });
    }

    /**
     * ATT-2627-00042: the season, then a per-season counter.
     *
     * Readable out loud over the phone, which is how a mutual insurer's desk
     * will quote it back to the office.
     */
    private function nextReference(Season $season): string
    {
        $short = preg_replace('/\D/', '', (string) $season->name);
        $short = mb_substr((string) $short, 2, 2) . mb_substr((string) $short, 6, 2);

        $taken = MutualAttestation::where('season_id', $season->id)->count();

        return sprintf('ATT-%s-%05d', $short === '' ? 'XXXX' : $short, $taken + 1);
    }

    private function renderFor(
        Mutuality $mutuality,
        AttestationData $data,
        MemberIdentifiers $identifiers,
        AttestationSetting $settings,
        string $reference,
        string $url,
    ): string {
        // The club's own certificate whenever there is no usable form to fill:
        // an insurer we hold nothing for, one whose form lost a label, and the
        // two that accept it outright. The rule lives in one place, because the
        // wizard asks the member for exactly what this will print.
        $template = $this->availability->officialFormFor($mutuality);

        if (! $template instanceof AttestationTemplate) {
            return $this->clubAttestation->render($data, $identifiers, $settings, $reference, $url);
        }

        return $this->officialForm->render(
            Storage::disk('local')->path($template->path),
            $mutuality,
            $this->values->for($data, $mutuality, $identifiers, $reference),
            $settings,
        );
    }
}
