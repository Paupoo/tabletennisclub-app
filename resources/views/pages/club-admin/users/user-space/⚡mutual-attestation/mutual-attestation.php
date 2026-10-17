<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Attestations\IssueAttestation;
use App\Actions\ClubAdmin\Attestations\IssueCampAttestation;
use App\Data\Attestation\AttestationData;
use App\Data\Attestation\AttestationVerdict;
use App\Data\Attestation\MemberIdentifiers;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationAvailability;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationEligibility;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\BuildAttestationData;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\CampAttestationEligibility;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\CampDaysCounter;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\AttestationKind;
use App\Domains\Shared\Enums\AttestationRefusal;
use App\Domains\Shared\Enums\Mutuality;
use App\Exceptions\AttestationNotAllowed;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The member's steps to a mutual-insurer attestation.
 *
 * Which document comes first, when there is more than one: the season's
 * affiliation and each training camp of the last twelve months are separate
 * certificates, with separate sums. A member with no camp never sees that
 * step — the page opens straight on the affiliation, as it always did.
 *
 * Eligibility is a state of the page rather than a step: somebody who cannot
 * be certified today is told why and shown nothing else. What remains are the
 * two screens that actually ask something — which insurer, and the details the
 * club does not hold — and the result.
 *
 * The national register number and the disability answer are typed here and
 * travel no further than the document: they are never bound to the member's
 * record, and the page drops them as soon as the PDF is written.
 */
new class extends Component
{
    use HasBreadcrumbs;

    /** 'affiliation', 'camp-{line id}', or empty while the member is choosing. */
    public string $document = '';

    public ?int $issuedId = null;

    public string $mutuality = '';

    public ?string $mutualMembershipNumber = null;

    public ?string $nationalRegisterNumber = null;

    /** Partenamut's camp form asks; null until the member answers. */
    public ?string $recognisedDisabled = null;

    public int $step = 1;

    public User $user;

    #[Computed]
    public function availability(): AttestationAvailability
    {
        return app(AttestationAvailability::class);
    }

    public function back(): void
    {
        if ($this->step === 1 && $this->hasChoice()) {
            $this->backToDocuments();

            return;
        }

        $this->step = max(1, $this->step - 1);
    }

    public function backToDocuments(): void
    {
        $this->reset(['document', 'mutuality', 'nationalRegisterNumber', 'mutualMembershipNumber', 'recognisedDisabled', 'issuedId']);
        $this->step = 1;
        $this->forgetDocument();
    }

    /**
     * @return Collection<int, SubscriptionTrainingPack>
     */
    #[Computed]
    public function campLines(): Collection
    {
        return app(CampAttestationEligibility::class)->linesFor($this->user);
    }

    /**
     * One card per document the member could ask about, with where it stands.
     *
     * @return array<int, array{key: string, title: string, subtitle: string, verdict: AttestationVerdict, attestation: MutualAttestation|null}>
     */
    #[Computed]
    public function cards(): array
    {
        $cards = [];
        $season = $this->season();

        if ($season instanceof Season) {
            $cards[] = [
                'key' => 'affiliation',
                'title' => __('Affiliation :season', ['season' => $season->name]),
                'subtitle' => __('Your cotisation and your training packs for the season.'),
                'verdict' => app(AttestationEligibility::class)->for($this->user, $season),
                'attestation' => $this->heldFor('affiliation'),
            ];
        }

        foreach ($this->campLines as $line) {
            $days = app(CampDaysCounter::class)->for($line);

            $cards[] = [
                'key' => 'camp-' . $line->id,
                'title' => $line->trainingPack->name,
                'subtitle' => $days->first()?->format('d/m/Y') . ' — ' . $days->last()?->format('d/m/Y') . ' · ' . $days->label(),
                'verdict' => app(CampAttestationEligibility::class)->for($line),
                'attestation' => $this->heldFor('camp-' . $line->id),
            ];
        }

        return $cards;
    }

    public function chooseDocument(string $key): void
    {
        $card = collect($this->cards)->firstWhere('key', $key);

        abort_unless($card !== null, 404);

        $this->reset(['mutuality', 'nationalRegisterNumber', 'mutualMembershipNumber', 'recognisedDisabled', 'issuedId']);
        $this->document = $key;
        $this->step = 1;
        $this->forgetDocument();

        if ($card['attestation'] instanceof MutualAttestation) {
            $this->issuedId = $card['attestation']->id;
            $this->step = 3;
        }
    }

    public function chooseMutuality(): void
    {
        $this->validate(
            ['mutuality' => ['required', 'string']],
            ['mutuality.required' => __('Choose your mutual insurer to continue.')],
        );

        abort_unless(in_array($this->chosen(), $this->offered, true), 422);

        // A number carried over from a previous choice would be validated
        // against the new form's needs and printed on a document that has no
        // box for it.
        $this->reset(['nationalRegisterNumber', 'mutualMembershipNumber', 'recognisedDisabled']);
        unset($this->needsNationalRegisterNumber, $this->needsMutualNumber, $this->needsDisability);

        $this->step = 2;
    }

    public function generate(IssueAttestation $issue, IssueCampAttestation $issueCamp): void
    {
        // Asked for only where the chosen document has a box for it, and
        // required wherever it does: an optional field on the one form that
        // prints it went out blank, and a field shown on the five that do not
        // sent members hunting for a number nobody would ever read.
        $nrnRequired = $this->needsNationalRegisterNumber && ! $this->nationalRegisterNumberIsOptional();

        $this->validate([
            'nationalRegisterNumber' => [$nrnRequired ? 'required' : 'nullable', 'string', 'max:20'],
            'mutualMembershipNumber' => [$this->needsMutualNumber ? 'required' : 'nullable', 'string', 'max:40'],
            'recognisedDisabled' => [$this->needsDisability ? 'required' : 'nullable', 'in:yes,no'],
        ], [
            'recognisedDisabled.required' => __('Answer this question to continue.'),
        ], [
            'nationalRegisterNumber' => __('National register number'),
            'mutualMembershipNumber' => __('Mutual membership number'),
        ]);

        $identifiers = new MemberIdentifiers(
            $this->nationalRegisterNumber ?: null,
            $this->mutualMembershipNumber,
            $this->needsDisability ? $this->recognisedDisabled === 'yes' : null,
        );

        try {
            $line = $this->line();

            $attestation = $line instanceof SubscriptionTrainingPack
                ? $issueCamp($line, $this->chosen(), $identifiers)
                : $issue($this->user, $this->season(), $this->chosen(), $identifiers);
        } catch (AttestationNotAllowed $refused) {
            $this->addError('mutuality', $refused->getMessage());

            return;
        }

        $this->issuedId = $attestation->id;

        // What only the member knows leaves the component the moment it has
        // reached the paper.
        $this->reset(['nationalRegisterNumber', 'mutualMembershipNumber', 'recognisedDisabled']);
        $this->forgetDocument();

        $this->step = 3;
    }

    #[Computed]
    public function held(): ?MutualAttestation
    {
        return $this->heldFor($this->document);
    }

    public function kind(): AttestationKind
    {
        return str_starts_with($this->document, 'camp-') ? AttestationKind::Camp : AttestationKind::Affiliation;
    }

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;

        // Without a camp to choose from, the page is what it always was: the
        // season's affiliation, opened on the document when it is held.
        if (! $this->hasChoice()) {
            $this->document = 'affiliation';

            if ($this->held instanceof MutualAttestation) {
                $this->issuedId = $this->held->id;
                $this->step = 3;
            }
        }
    }

    #[Computed]
    public function needsDisability(): bool
    {
        $chosen = $this->chosen();

        return $chosen instanceof Mutuality && $this->availability->asksAboutDisability($chosen, $this->kind());
    }

    #[Computed]
    public function needsMutualNumber(): bool
    {
        $chosen = $this->chosen();

        return $chosen instanceof Mutuality && $this->availability->asksForMutualNumber($chosen, $this->kind());
    }

    #[Computed]
    public function needsNationalRegisterNumber(): bool
    {
        $chosen = $this->chosen();

        return $chosen instanceof Mutuality && $this->availability->asksForNationalRegisterNumber($chosen, $this->kind());
    }

    /**
     * @return array<int, Mutuality>
     */
    #[Computed]
    public function offered(): array
    {
        return $this->availability->offered($this->kind());
    }

    #[Computed]
    public function preview(): ?AttestationData
    {
        $verdict = $this->verdict;

        if (! $verdict->allowed) {
            return null;
        }

        $line = $verdict->line;

        if ($line instanceof SubscriptionTrainingPack) {
            return app(BuildAttestationData::class)->forCamp(
                $line,
                app(CampDaysCounter::class)->for($line),
                app(CampAttestationEligibility::class)->certifiedAmount($line),
            );
        }

        return $verdict->affiliation === null ? null : app(BuildAttestationData::class)->for($verdict->affiliation);
    }

    public function season(): ?Season
    {
        return Season::where('is_active', true)->first();
    }

    #[Computed]
    public function verdict(): AttestationVerdict
    {
        $line = $this->line();

        if ($line instanceof SubscriptionTrainingPack) {
            return app(CampAttestationEligibility::class)->for($line);
        }

        $season = $this->season();

        return $season === null
            ? AttestationVerdict::refuse(AttestationRefusal::NoAffiliation)
            : app(AttestationEligibility::class)->for($this->user, $season);
    }

    public function with(): array
    {
        $isCamp = $this->kind() === AttestationKind::Camp;

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'attestation' => $this->held,
            'cards' => $this->document === '' ? $this->cards : [],
            'hasChoice' => $this->hasChoice(),
            'isCamp' => $isCamp,
            'kind' => $this->kind(),
            'offered' => $this->document === '' ? [] : $this->offered,
            'preview' => $this->document === '' || $this->step !== 2 ? null : $this->preview,
            'needsDisability' => $this->needsDisability,
            'needsMutualNumber' => $this->needsMutualNumber,
            'needsNationalRegisterNumber' => $this->needsNationalRegisterNumber,
            'nationalRegisterNumberIsOptional' => $this->nationalRegisterNumberIsOptional(),
            'ready' => $this->availability->isReady(),
            'verdict' => $this->document === '' ? null : $this->verdict,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Mutual attestations'));
    }

    private function chosen(): ?Mutuality
    {
        return Mutuality::tryFrom($this->mutuality);
    }

    /** Drops every answer computed for the document the member was on. */
    private function forgetDocument(): void
    {
        unset($this->held, $this->verdict, $this->preview, $this->offered, $this->cards);
        unset($this->needsNationalRegisterNumber, $this->needsMutualNumber, $this->needsDisability);
    }

    private function hasChoice(): bool
    {
        return $this->campLines->isNotEmpty();
    }

    private function heldFor(string $document): ?MutualAttestation
    {
        if (str_starts_with($document, 'camp-')) {
            return MutualAttestation::query()
                ->where('user_id', $this->user->id)
                ->where('subscription_training_pack_id', (int) mb_substr($document, 5))
                ->live()
                ->first();
        }

        return MutualAttestation::query()
            ->where('user_id', $this->user->id)
            ->where('season_id', $this->season()?->id)
            ->where('kind', AttestationKind::Affiliation->value)
            ->live()
            ->first();
    }

    /** The camp line being certified — always one of this member's own. */
    private function line(): ?SubscriptionTrainingPack
    {
        if (! str_starts_with($this->document, 'camp-')) {
            return null;
        }

        return $this->campLines->firstWhere('id', (int) mb_substr($this->document, 5));
    }

    private function nationalRegisterNumberIsOptional(): bool
    {
        $chosen = $this->chosen();

        return $chosen instanceof Mutuality && $this->availability->isNationalRegisterNumberOptional($chosen, $this->kind());
    }
};
