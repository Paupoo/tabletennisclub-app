<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Attestations;

use App\Data\Attestation\MemberIdentifiers;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationTemplate;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\ClubAttestationRenderer;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\OfficialFormRenderer;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationAvailability;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationFieldValues;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\BuildAttestationData;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\CampAttestationEligibility;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\CampDaysCounter;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\AttestationKind;
use App\Domains\Shared\Enums\AttestationRefusal;
use App\Domains\Shared\Enums\Mutuality;
use App\Exceptions\AttestationNotAllowed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Certifies one member's participation in one camp, once.
 *
 * One live attestation per camp line, official form or club attestation
 * alike: the same money would otherwise be claimed from two payers. A family
 * that picked the wrong insurer goes through the office's revocation, exactly
 * as for an affiliation.
 *
 * The days are counted at the moment of issue and copied onto the row with
 * everything else the document states.
 */
final readonly class IssueCampAttestation
{
    public function __construct(
        private AttestationAvailability $availability,
        private CampAttestationEligibility $eligibility,
        private CampDaysCounter $days,
        private BuildAttestationData $builder,
        private AttestationFieldValues $values,
        private OfficialFormRenderer $officialForm,
        private ClubAttestationRenderer $clubAttestation,
    ) {}

    public function __invoke(
        SubscriptionTrainingPack $line,
        Mutuality $mutuality,
        MemberIdentifiers $identifiers = new MemberIdentifiers,
        ?User $issuedBy = null,
    ): MutualAttestation {
        $verdict = $this->eligibility->for($line);

        if (! $verdict->allowed) {
            throw new AttestationNotAllowed($verdict->refusal);
        }

        return DB::transaction(function () use ($line, $mutuality, $identifiers, $issuedBy): MutualAttestation {
            $alreadyHeld = MutualAttestation::query()
                ->where('subscription_training_pack_id', $line->id)
                ->live()
                ->lockForUpdate()
                ->exists();

            if ($alreadyHeld) {
                throw new AttestationNotAllowed(AttestationRefusal::AlreadyIssued);
            }

            $season = $line->subscription->season;
            $days = $this->days->for($line);
            $data = $this->builder->forCamp($line, $days, $this->eligibility->certifiedAmount($line));
            $settings = AttestationSetting::current();

            $reference = MutualAttestation::nextReference($season, AttestationKind::Camp);
            $token = strtolower((string) Str::ulid());
            $url = route('attestations.verify', ['token' => $token]);

            $template = $this->availability->officialFormFor($mutuality, AttestationKind::Camp);

            $pdf = $template instanceof AttestationTemplate
                ? $this->officialForm->render(
                    Storage::disk('local')->path($template->path),
                    $mutuality,
                    $this->values->for($data, $mutuality, $identifiers, $reference),
                    $settings,
                    kind: AttestationKind::Camp,
                )
                : $this->clubAttestation->render($data, $identifiers, $settings, $reference, $url);

            $path = 'attestations/' . $season->id . '/' . $reference . '.pdf';
            Storage::disk('local')->put($path, $pdf);

            return MutualAttestation::create([
                'user_id' => $line->subscription->user_id,
                'subscription_id' => $line->subscription_id,
                'subscription_training_pack_id' => $line->id,
                'season_id' => $season->id,
                'kind' => AttestationKind::Camp->value,
                'mutuality' => $mutuality->value,
                'reference' => $reference,
                'token' => $token,
                'path' => $path,
                'amount_certified' => $data->amountPaid,
                'period_from' => $data->periodFrom,
                'period_to' => $data->periodTo,
                'full_days' => $days->fullDays(),
                'half_days' => $days->halfDays(),
                'signatory_name' => $data->signatoryName,
                'discipline' => $data->discipline,
                'issued_by_user_id' => $issuedBy?->id,
                'issued_at' => now(),
            ]);
        });
    }
}
