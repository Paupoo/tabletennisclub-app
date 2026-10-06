<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaignResponse;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackEntry;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use App\Domains\ClubAdmin\Feedback\Models\HelpOffer;
use App\Domains\ClubAdmin\Feedback\Services\CampaignResults;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FeedbackStatus;
use App\Domains\Shared\Enums\HelpOfferStatus;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast, WithPagination;

    #[Url(as: 'campaign')]
    public ?int $campaignId = null;

    /** @var array<int, string> */
    public array $hideReasons = [];

    /** @var array<int, string> */
    public array $notes = [];

    /** @var array<int, int> */
    public array $revealed = [];

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $tab = 'feedback';

    #[Url]
    public ?int $themeFilter = null;

    /**
     * The surveys that ran, newest first, for the results picker.
     *
     * @return Collection<int, FeedbackCampaign>
     */
    #[Computed]
    public function campaigns(): Collection
    {
        return FeedbackCampaign::query()
            ->scheduled()
            ->whereDate('opens_on', '<=', today())
            ->orderByDesc('opens_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Whether the visitor sorts the feedback, rather than only reads it.
     */
    #[Computed]
    public function canManage(): bool
    {
        return Gate::allows(Permission::FeedbackManage->value);
    }

    /**
     * @return LengthAwarePaginator<int, FeedbackEntry>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return FeedbackEntry::query()
            ->with(['author', 'theme', 'hiddenBy', 'response.campaign'])
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->themeFilter, fn (Builder $query) => $query->where('feedback_theme_id', $this->themeFilter))
            ->latest()
            ->orderByDesc('feedback_entries.id')
            ->paginate(30, pageName: 'feedbackPage');
    }

    /**
     * Hide what insults someone, without making it disappear: the trace says
     * who hid it, when and why, and any committee seat can still open it.
     */
    public function hide(int $entryId): void
    {
        Gate::authorize(Permission::FeedbackManage->value);

        $this->validate(["hideReasons.{$entryId}" => ['required', 'string', 'max:255']]);

        FeedbackEntry::findOrFail($entryId)->update([
            'hidden_at' => now(),
            'hidden_by_id' => Auth::id(),
            'hidden_reason' => trim($this->hideReasons[$entryId]),
        ]);

        unset($this->hideReasons[$entryId], $this->entries);

        $this->success(__('Feedback hidden. The committee can still open it.'));
    }

    public function mount(): void
    {
        if (! in_array($this->tab, ['feedback', 'help', 'results'], true)) {
            $this->tab = 'feedback';
        }
    }

    #[Computed]
    public function newCount(): int
    {
        return FeedbackEntry::query()->where('status', FeedbackStatus::New->value)->count();
    }

    /**
     * @return LengthAwarePaginator<int, HelpOffer>
     */
    #[Computed]
    public function offers(): LengthAwarePaginator
    {
        return HelpOffer::query()
            ->with(['volunteer', 'tasks', 'handledBy'])
            // The offers still owed an answer first.
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [HelpOfferStatus::ToContact->value])
            ->latest()
            ->orderByDesc('help_offers.id')
            ->paginate(30, pageName: 'offersPage');
    }

    #[Computed]
    public function openOffersCount(): int
    {
        return HelpOffer::open()->count();
    }

    /**
     * The figures of the survey picked — the latest one by default.
     *
     * @return array{campaign: FeedbackCampaign, responses: int, participants: int, members: int, average: float|null, distribution: array<int, int>, themes: SupportCollection<int, array{theme: string, count: int}>, trend: list<array{title: string, average: float|null}>, yearAnswers: Collection<int, FeedbackCampaignResponse>}|null
     */
    #[Computed]
    public function results(): ?array
    {
        $campaign = $this->campaigns->firstWhere('id', $this->campaignId) ?? $this->campaigns->first();

        if (! $campaign instanceof FeedbackCampaign) {
            return null;
        }

        $results = app(CampaignResults::class);

        return [
            'campaign' => $campaign,
            'responses' => $results->responses($campaign),
            'participants' => $campaign->participants()->count(),
            'members' => User::active()->count(),
            'average' => $results->average($campaign),
            'distribution' => $results->distribution($campaign),
            'themes' => $results->commentsByTheme($campaign),
            'trend' => $results->trend(),
            'yearAnswers' => FeedbackCampaignResponse::query()
                ->whereBelongsTo($campaign, 'campaign')
                ->whereNotNull('year_answer')
                ->with('author')
                ->orderBy('id')
                ->get(),
        ];
    }

    /**
     * Open a hidden feedback for this visitor only — reading it changes nothing.
     */
    public function reveal(int $entryId): void
    {
        $this->revealed[] = $entryId;
    }

    public function saveNote(int $entryId): void
    {
        Gate::authorize(Permission::FeedbackManage->value);

        $this->validate(["notes.{$entryId}" => ['nullable', 'string', 'max:2000']]);

        $note = trim($this->notes[$entryId] ?? '');

        FeedbackEntry::findOrFail($entryId)->update(['internal_note' => $note !== '' ? $note : null]);

        unset($this->entries);

        $this->success(__('Note saved.'));
    }

    public function setOfferStatus(int $offerId, string $status): void
    {
        Gate::authorize(Permission::FeedbackManage->value);

        HelpOffer::findOrFail($offerId)->update([
            'status' => HelpOfferStatus::from($status),
            'handled_by_id' => Auth::id(),
            'handled_at' => now(),
        ]);

        unset($this->offers, $this->openOffersCount);
    }

    /**
     * The first move away from « new » dates the reading the author is told
     * about; later moves leave that date alone.
     */
    public function setStatus(int $entryId, string $status): void
    {
        Gate::authorize(Permission::FeedbackManage->value);

        $entry = FeedbackEntry::findOrFail($entryId);
        $status = FeedbackStatus::from($status);

        $entry->update([
            'status' => $status,
            'read_at' => $entry->read_at ?? ($status === FeedbackStatus::New ? null : now()),
        ]);

        unset($this->entries);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['statusFilter', 'themeFilter'], true)) {
            $this->resetPage('feedbackPage');
        }
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'statuses' => FeedbackStatus::cases(),
            'offerStatuses' => HelpOfferStatus::cases(),
            'themes' => FeedbackTheme::query()->orderBy('position')->orderBy('id')->get(['id', 'name']),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Feedback and suggestions'));
    }
};
