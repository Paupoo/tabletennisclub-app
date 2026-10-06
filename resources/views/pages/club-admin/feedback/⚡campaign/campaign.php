<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use App\Domains\ClubAdmin\Feedback\Models\HelpTask;
use App\Domains\ClubAdmin\Feedback\Services\CampaignAudience;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

/*
 * Prepare a yearly survey: dates, introduction, question of the year. Nothing
 * leaves until it is scheduled; once open, only the closing date and the
 * introduction still change, so that every member answers the same form.
 */
new class extends Component
{
    use HasBreadcrumbs, Toast;

    public ?FeedbackCampaign $campaign = null;

    public string $closesOn = '';

    public string $intro = '';

    public string $opensOn = '';

    public string $title = '';

    public string $yearQuestion = '';

    /**
     * @return array{members: int, addresses: int, unreachable: int}
     */
    #[Computed]
    public function audience(): array
    {
        return app(CampaignAudience::class)->summary();
    }

    #[Computed]
    public function isFrozen(): bool
    {
        return $this->campaign?->hasStarted() ?? false;
    }

    public function mount(?FeedbackCampaign $campaign = null): void
    {
        if ($campaign?->exists) {
            $this->campaign = $campaign;
            $this->title = $campaign->title;
            $this->intro = $campaign->intro;
            $this->yearQuestion = $campaign->year_question ?? '';
            $this->opensOn = $campaign->opens_on->toDateString();
            $this->closesOn = $campaign->closes_on->toDateString();
        }
    }

    #[Computed]
    public function reminderDay(): ?Carbon
    {
        if ($this->opensOn === '' || $this->closesOn === '' || $this->closesOn < $this->opensOn) {
            return null;
        }

        return new FeedbackCampaign(['opens_on' => $this->opensOn, 'closes_on' => $this->closesOn])->reminderDay();
    }

    public function save(): void
    {
        if ($this->isFrozen) {
            $this->validate([
                'intro' => ['required', 'string', 'max:2000'],
                'closesOn' => ['required', 'date', 'after_or_equal:today', 'after_or_equal:' . $this->campaign->opens_on->toDateString()],
            ]);

            $this->campaign->update(['intro' => trim($this->intro), 'closes_on' => $this->closesOn]);
            $this->success(__('Survey saved.'));

            return;
        }

        $this->validateForm();

        $attributes = [
            'title' => trim($this->title),
            'intro' => trim($this->intro),
            'year_question' => filled($this->yearQuestion) ? trim($this->yearQuestion) : null,
            'opens_on' => $this->opensOn,
            'closes_on' => $this->closesOn,
        ];

        if ($this->campaign instanceof FeedbackCampaign) {
            $this->campaign->update($attributes);
        } else {
            $this->campaign = FeedbackCampaign::create([...$attributes, 'created_by_id' => Auth::id()]);
        }

        $this->success(__('Survey saved.'));
        $this->redirectRoute('admin.feedback.campaigns.edit', $this->campaign, navigate: true);
    }

    /**
     * Let the survey leave on its opening day. Only one survey may run at a
     * time: two at once would ask the same members twice, and split their
     * answers across two sets of figures.
     */
    public function schedule(): void
    {
        abort_unless($this->campaign instanceof FeedbackCampaign && $this->campaign->isDraft(), 404);

        $this->validateForm();

        $overlaps = FeedbackCampaign::query()
            ->scheduled()
            ->whereKeyNot($this->campaign->id)
            ->whereDate('opens_on', '<=', $this->closesOn)
            ->whereDate('closes_on', '>=', $this->opensOn)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['opensOn' => __('Another survey is scheduled over these dates.')]);
        }

        $this->campaign->update([
            'title' => trim($this->title),
            'intro' => trim($this->intro),
            'year_question' => filled($this->yearQuestion) ? trim($this->yearQuestion) : null,
            'opens_on' => $this->opensOn,
            'closes_on' => $this->closesOn,
            'scheduled_at' => now(),
        ]);

        $this->success(__('Survey scheduled. The invitation leaves on its opening day.'));
    }

    public function unschedule(): void
    {
        abort_unless($this->campaign instanceof FeedbackCampaign && ! $this->campaign->isDraft() && ! $this->isFrozen, 404);

        $this->campaign->update(['scheduled_at' => null]);
        $this->success(__('Back to draft. Nothing will be sent.'));
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'themesCount' => FeedbackTheme::offered()->count(),
            'tasksCount' => HelpTask::offered()->count(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->add(__('Feedback and suggestions'), route('admin.feedback.index'))
            ->add(__('Yearly surveys'), route('admin.feedback.campaigns'))
            ->current($this->campaign?->title ?? __('New survey'));
    }

    private function validateForm(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'intro' => ['required', 'string', 'max:2000'],
            'yearQuestion' => ['nullable', 'string', 'max:255'],
            'opensOn' => ['required', 'date', 'after_or_equal:today'],
            'closesOn' => ['required', 'date', 'after:opensOn'],
        ]);
    }
};
