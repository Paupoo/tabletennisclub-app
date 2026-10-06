<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Actions\OfferHelp;
use App\Domains\ClubAdmin\Feedback\Actions\SubmitFeedback;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackEntry;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\OffersHelp;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, OffersHelp, Toast;

    public bool $anonymous = false;

    public string $body = '';

    public ?int $themeId = null;

    public User $user;

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;
    }

    /**
     * The member's own signed feedback, newest first. Anonymous feedback has no
     * author on record, so it can never show here — the form says so.
     *
     * @return Collection<int, FeedbackEntry>
     */
    #[Computed]
    public function myFeedback(): Collection
    {
        return FeedbackEntry::query()
            ->whereBelongsTo($this->user, 'author')
            ->with('theme')
            ->latest()
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Send what the member filled in: the feedback, the offer of help, or both.
     * They travel together but are stored apart, so that an anonymous feedback
     * stays anonymous next to an offer that carries a name.
     */
    /**
     * The survey under way, if any: the box stays open, but points to it.
     */
    #[Computed]
    public function openCampaign(): ?FeedbackCampaign
    {
        return FeedbackCampaign::openOn(today())->orderBy('opens_on')->first();
    }

    public function send(SubmitFeedback $submit, OfferHelp $offerHelp): void
    {
        abort_unless(Auth::user()->is($this->user), 403);

        $offersHelp = $this->isOfferingHelp();

        $this->validate([
            'themeId' => [$offersHelp ? 'required_with:body' : 'required', 'nullable', Rule::exists('feedback_themes', 'id')->whereNull('hidden_at')],
            'body' => [$offersHelp ? 'nullable' : 'required', 'string', 'max:5000'],
            'anonymous' => ['boolean'],
            ...$this->helpRules(),
        ]);

        if (filled($this->body)) {
            $submit($this->user, FeedbackTheme::findOrFail($this->themeId), $this->body, $this->anonymous);
        }

        $this->offerHelpIfAsked($offerHelp);

        $this->reset(['body', 'themeId', 'anonymous']);
        unset($this->myFeedback);

        $this->success(__('Thank you. The committee reads every message.'));
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
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Your feedback'));
    }

    protected function helpVolunteer(): User
    {
        return $this->user;
    }
};
