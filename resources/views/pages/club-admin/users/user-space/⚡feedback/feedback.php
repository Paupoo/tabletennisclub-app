<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Actions\OfferHelp;
use App\Domains\ClubAdmin\Feedback\Actions\SubmitFeedback;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackEntry;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use App\Domains\ClubAdmin\Feedback\Models\HelpOffer;
use App\Domains\ClubAdmin\Feedback\Models\HelpTask;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\HelpRhythm;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast;

    public bool $anonymous = false;

    public string $body = '';

    public string $helpMessage = '';

    public string $helpRhythm = 'occasional';

    /** @var array<int, int> */
    public array $helpTaskIds = [];

    public ?int $themeId = null;

    public User $user;

    /**
     * Whether the form asks the member to give a hand: never a managed account
     * (its guardian offers from their own form), and not while an earlier
     * offer still waits for the club's answer.
     */
    #[Computed]
    public function asksForHelp(): bool
    {
        return ! $this->user->isManagedAccount() && ! $this->openOffer instanceof HelpOffer;
    }

    /**
     * @return Collection<int, HelpTask>
     */
    #[Computed]
    public function helpTasks(): Collection
    {
        return HelpTask::offered()->get();
    }

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
     * The offer the club still owes this member an answer to, if any.
     */
    #[Computed]
    public function openOffer(): ?HelpOffer
    {
        return HelpOffer::open()->whereBelongsTo($this->user, 'volunteer')->latest()->first();
    }

    /**
     * Send what the member filled in: the feedback, the offer of help, or both.
     * They travel together but are stored apart, so that an anonymous feedback
     * stays anonymous next to an offer that carries a name.
     */
    public function send(SubmitFeedback $submit, OfferHelp $offerHelp): void
    {
        abort_unless(Auth::user()->is($this->user), 403);

        $offersHelp = $this->asksForHelp && ($this->helpTaskIds !== [] || filled($this->helpMessage));

        $this->validate([
            'themeId' => [$offersHelp ? 'required_with:body' : 'required', 'nullable', Rule::exists('feedback_themes', 'id')->whereNull('hidden_at')],
            'body' => [$offersHelp ? 'nullable' : 'required', 'string', 'max:5000'],
            'anonymous' => ['boolean'],
            'helpRhythm' => [Rule::enum(HelpRhythm::class)],
            'helpTaskIds' => ['array'],
            'helpTaskIds.*' => ['integer'],
            'helpMessage' => ['nullable', 'string', 'max:1000'],
        ]);

        if (filled($this->body)) {
            $submit($this->user, FeedbackTheme::findOrFail($this->themeId), $this->body, $this->anonymous);
        }

        if ($offersHelp) {
            $offerHelp($this->user, HelpRhythm::from($this->helpRhythm), $this->helpTaskIds, $this->helpMessage);
        }

        $this->reset(['body', 'themeId', 'anonymous', 'helpRhythm', 'helpTaskIds', 'helpMessage']);
        unset($this->myFeedback, $this->openOffer, $this->asksForHelp);

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
            'rhythms' => HelpRhythm::cases(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Your feedback'));
    }
};
