<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Actions\AnswerCampaign;
use App\Domains\ClubAdmin\Feedback\Actions\OfferHelp;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaignResponse;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\OffersHelp;
use App\Support\AccountProxy;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

/*
 * The yearly survey, as the member answers it. Reached from the invitation
 * mail without any {user}: the link is the same for a whole family, and the
 * « answering for » switch puts the guardian in the right seat — the same
 * proxy every other screen obeys.
 */
new class extends Component
{
    use HasBreadcrumbs, OffersHelp, Toast;

    public bool $anonymous = false;

    /** @var array<int, string> theme id => comment */
    public array $comments = [];

    #[Locked]
    public ?int $previewOf = null;

    public ?int $rating = null;

    public string $yearAnswer = '';

    /**
     * The member's answer — or the guardian's ward's, under the proxy.
     */
    public function answerFor(int $userId): void
    {
        $chosen = $this->choices()->first(fn (User $choice): bool => $choice->id === $userId);

        abort_if($chosen === null, 403);

        $person = AccountProxy::origin() ?? Auth::user();

        if ($chosen->is($person)) {
            AccountProxy::stop();
        } elseif (! $chosen->is(Auth::user())) {
            AccountProxy::start($chosen);
        }

        $this->redirectRoute('admin.user.survey');
    }

    #[Computed]
    public function campaign(): ?FeedbackCampaign
    {
        return $this->previewOf !== null
            ? FeedbackCampaign::find($this->previewOf)
            : FeedbackCampaign::openOn(today())->orderBy('opens_on')->first();
    }

    /**
     * Who this person may answer for: themself — unless their account only
     * exists to act for their children — and each managed account they hold,
     * with whether each has answered yet.
     *
     * @return SupportCollection<int, User>
     */
    public function choices(): SupportCollection
    {
        /** @var User $person */
        $person = AccountProxy::origin() ?? Auth::user();

        return ($person->isGuardianOnlyAccount() ? collect() : collect([$person]))
            ->merge($person->managedAccounts())
            ->values();
    }

    #[Computed]
    public function hasAnsweredAnonymously(): bool
    {
        return $this->campaign instanceof FeedbackCampaign
            && ! $this->signedAnswer instanceof FeedbackCampaignResponse
            && $this->campaign->hasAnswered($this->member());
    }

    #[Computed]
    public function isEligible(): bool
    {
        return $this->previewOf !== null || User::active()->whereKey($this->member()->id)->exists();
    }

    public function mount(?FeedbackCampaign $campaign = null): void
    {
        if ($campaign?->exists) {
            Gate::authorize(Permission::FeedbackManage->value);
            $this->previewOf = $campaign->id;

            return;
        }

        if ($this->signedAnswer instanceof FeedbackCampaignResponse) {
            $this->rating = $this->signedAnswer->rating;
            $this->yearAnswer = $this->signedAnswer->year_answer ?? '';
            $this->comments = $this->signedAnswer->comments->pluck('body', 'feedback_theme_id')->all();
        }
    }

    public function send(AnswerCampaign $answer, OfferHelp $offerHelp): void
    {
        if ($this->previewOf !== null) {
            $this->info(__('Preview: nothing you fill in is recorded.'));

            return;
        }

        $campaign = $this->campaign;

        if (! $campaign instanceof FeedbackCampaign || ! $this->isEligible || $this->hasAnsweredAnonymously) {
            return;
        }

        $this->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comments' => ['array'],
            'comments.*' => ['nullable', 'string', 'max:5000'],
            'yearAnswer' => ['nullable', 'string', 'max:5000'],
            'anonymous' => ['boolean'],
            ...$this->helpRules(),
        ]);

        $answer($this->member(), $campaign, (int) $this->rating, $this->comments, $this->yearAnswer, $this->anonymous);
        $this->offerHelpIfAsked($offerHelp);

        unset($this->signedAnswer, $this->hasAnsweredAnonymously);

        $this->success(__('Thank you. The committee reads every answer.'));
    }

    #[Computed]
    public function signedAnswer(): ?FeedbackCampaignResponse
    {
        if (! $this->campaign instanceof FeedbackCampaign || $this->previewOf !== null) {
            return null;
        }

        return FeedbackCampaignResponse::query()
            ->whereBelongsTo($this->campaign, 'campaign')
            ->whereBelongsTo($this->member(), 'author')
            ->with('comments')
            ->first();
    }

    /**
     * @return Collection<int, FeedbackTheme>
     */
    #[Computed]
    public function themes(): Collection
    {
        return FeedbackTheme::offered()->get();
    }

    public function with(): array
    {
        $campaign = $this->campaign;

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'choices' => $this->previewOf === null ? $this->choices()->map(fn (User $choice): array => [
                'id' => $choice->id,
                'name' => $choice->full_name,
                'current' => $choice->is(Auth::user()),
                'answered' => $campaign instanceof FeedbackCampaign && $campaign->hasAnswered($choice),
            ]) : collect(),
            'answeringForWard' => $this->member()->isManagedAccount(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Yearly survey'));
    }

    protected function helpVolunteer(): User
    {
        return $this->member();
    }

    private function member(): User
    {
        /** @var User $member */
        $member = Auth::user();

        return $member;
    }
};
