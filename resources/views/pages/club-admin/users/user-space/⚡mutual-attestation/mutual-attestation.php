<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Attestations\IssueAttestation;
use App\Data\Attestation\AttestationVerdict;
use App\Data\Attestation\MemberIdentifiers;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationAvailability;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationEligibility;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\BuildAttestationData;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\AttestationRefusal;
use App\Domains\Shared\Enums\Mutuality;
use App\Exceptions\AttestationNotAllowed;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The member's three steps to a mutual-insurer attestation.
 *
 * Eligibility is a state of the page rather than a step: somebody who cannot
 * be certified today is told why and shown nothing else. What remains are the
 * two screens that actually ask something — which insurer, and the details the
 * club does not hold — and the result.
 *
 * The national register number is typed here and travels no further than the
 * document: it is never bound to the member's record, and the page drops it as
 * soon as the PDF is written.
 */
new class extends Component
{
    use HasBreadcrumbs;

    public ?int $issuedId = null;

    public string $mutuality = '';

    public ?string $mutualMembershipNumber = null;

    public ?string $nationalRegisterNumber = null;

    public int $step = 1;

    public User $user;

    #[Computed]
    public function availability(): AttestationAvailability
    {
        return app(AttestationAvailability::class);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function chooseMutuality(): void
    {
        $this->validate(
            ['mutuality' => ['required', 'string']],
            ['mutuality.required' => __('Choose your mutual insurer to continue.')],
        );

        abort_unless($this->chosen() instanceof Mutuality, 422);

        $this->step = 2;
    }

    public function generate(IssueAttestation $issue): void
    {
        $this->validate([
            'nationalRegisterNumber' => ['required', 'string', 'max:20'],
            'mutualMembershipNumber' => ['nullable', 'string', 'max:40'],
        ], [], [
            'nationalRegisterNumber' => __('National register number'),
            'mutualMembershipNumber' => __('Mutual membership number'),
        ]);

        try {
            $attestation = $issue(
                $this->user,
                $this->season(),
                $this->chosen(),
                new MemberIdentifiers($this->nationalRegisterNumber, $this->mutualMembershipNumber),
            );
        } catch (AttestationNotAllowed $refused) {
            $this->addError('mutuality', $refused->getMessage());

            return;
        }

        $this->issuedId = $attestation->id;

        // The number leaves the component the moment it has reached the paper.
        $this->reset(['nationalRegisterNumber', 'mutualMembershipNumber']);

        $this->step = 3;
    }

    #[Computed]
    public function held(): ?MutualAttestation
    {
        return MutualAttestation::query()
            ->where('user_id', $this->user->id)
            ->where('season_id', $this->season()?->id)
            ->live()
            ->first();
    }

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;

        if ($this->held() instanceof MutualAttestation) {
            $this->issuedId = $this->held()->id;
            $this->step = 3;
        }
    }

    /**
     * @return array<int, Mutuality>
     */
    #[Computed]
    public function offered(): array
    {
        return $this->availability()->offered();
    }

    #[Computed]
    public function preview(): ?object
    {
        $affiliation = $this->verdict()->affiliation;

        return $affiliation === null ? null : app(BuildAttestationData::class)->for($affiliation);
    }

    public function season(): ?Season
    {
        return Season::where('is_active', true)->first();
    }

    #[Computed]
    public function verdict(): object
    {
        $season = $this->season();

        return $season === null
            ? AttestationVerdict::refuse(AttestationRefusal::NoAffiliation)
            : app(AttestationEligibility::class)->for($this->user, $season);
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'attestation' => $this->held(),
            'offered' => $this->offered(),
            'preview' => $this->preview(),
            'ready' => $this->availability()->isReady(),
            'verdict' => $this->verdict(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Mutual attestation'));
    }

    private function chosen(): ?Mutuality
    {
        return Mutuality::tryFrom($this->mutuality);
    }
};
