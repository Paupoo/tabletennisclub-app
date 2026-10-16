<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingActionItem;
use App\Domains\Meetings\Models\MeetingAgendaItem;
use App\Domains\Meetings\Models\MeetingDecision;
use App\Domains\Meetings\Models\MeetingMinutes;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use App\Support\Markdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * The register of minutes: the committee's memory.
 *
 * Three readings of every minutes ever taken — the decisions of the last
 * months in a line each, every action by owner and state, and a search
 * through what was said, decided and handed out. A draft is its managers'
 * until published, here as on the reading page.
 */
new class extends Component
{
    use HasBreadcrumbs;

    /** Results kept per reading: the register is read, not exported. */
    private const int LIMIT = 200;

    #[Url(as: 'owner', except: '')]
    public string $actionOwner = '';

    #[Url(as: 'state', except: '')]
    public string $actionState = '';

    /** '6' or '12' months, 'season', 'all' */
    #[Url(as: 'period', except: '12')]
    public string $decisionPeriod = '12';

    /** '' for both, or a MeetingTypeEnum value */
    #[Url(as: 'type', except: '')]
    public string $decisionType = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'decisions')]
    public string $tab = 'decisions';

    /** @return Collection<int, MeetingActionItem> */
    #[Computed]
    public function actions(): Collection
    {
        return MeetingActionItem::query()
            ->whereHas('meeting', fn (Builder $meeting): Builder => $this->readable($meeting))
            ->when($this->actionOwner !== '', fn (Builder $query) => $query->where('assigned_to_id', (int) $this->actionOwner))
            ->when($this->actionState === 'open', fn (Builder $query) => $query->open())
            ->when($this->actionState === 'done', fn (Builder $query) => $query->where('is_completed', true))
            ->when($this->actionState === 'abandoned', fn (Builder $query) => $query->whereNotNull('abandoned_at'))
            ->with(['meeting.minutes', 'assignedTo'])
            ->join('meetings', 'meetings.id', '=', 'meeting_action_items.meeting_id')
            ->select('meeting_action_items.*')
            ->orderByDesc('meetings.scheduled_at')
            ->orderBy('meeting_action_items.id')
            ->limit(self::LIMIT)
            ->get();
    }

    #[Computed]
    public function canManage(): bool
    {
        return auth()->user()?->can(Permission::MeetingsManage->value) ?? false;
    }

    /** "D3" within its own minutes, numbered across the meeting as the minutes do. */
    public function decisionNumber(MeetingDecision $decision): string
    {
        return 'D' . ($this->decisionNumbers[$decision->id] ?? '?');
    }

    /** @return array<int, int> decision id => its number within its meeting */
    #[Computed]
    public function decisionNumbers(): array
    {
        return MeetingDecision::query()
            ->whereIn('meeting_id', $this->decisions->pluck('meeting_id')->unique())
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'meeting_id'])
            ->groupBy('meeting_id')
            ->flatMap(fn (Collection $decisions) => $decisions->values()->mapWithKeys(fn (MeetingDecision $decision, int $i): array => [$decision->id => $i + 1]))
            ->all();
    }

    /** @return Collection<int, MeetingDecision> */
    #[Computed]
    public function decisions(): Collection
    {
        $since = match ($this->decisionPeriod) {
            '6' => now()->subMonths(6),
            'season' => Season::current()?->start_at,
            'all' => null,
            default => now()->subMonths(12),
        };

        return MeetingDecision::query()
            ->whereHas('meeting', fn (Builder $meeting) => $this->readable($meeting)
                ->when($since !== null, fn (Builder $query) => $query->where('scheduled_at', '>=', $since))
                ->when(MeetingTypeEnum::tryFrom($this->decisionType), fn (Builder $query, MeetingTypeEnum $type) => $query->where('type', $type)))
            ->with(['meeting.minutes', 'agendaItem'])
            ->join('meetings', 'meetings.id', '=', 'meeting_decisions.meeting_id')
            ->select('meeting_decisions.*')
            ->orderByDesc('meetings.scheduled_at')
            ->orderBy('meeting_decisions.sort_order')
            ->orderBy('meeting_decisions.id')
            ->limit(self::LIMIT)
            ->get();
    }

    /** @return SupportCollection<int, array{id: int, name: string}> */
    #[Computed]
    public function owners(): SupportCollection
    {
        return User::role([Role::ADMINISTRATOR->value, Role::COMMITTEE->value])
            ->orderBy('last_name')->orderBy('users.id')->get()
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->full_name]);
    }

    public function render(): View
    {
        return $this->view();
    }

    /**
     * Every place a word appears: a decision, a point and what was said on it,
     * an action, what came up outside the agenda, an announcement.
     *
     * @return SupportCollection<int, array{meeting: Meeting, where: string, excerpt: string, url: string}>
     */
    #[Computed]
    public function results(): SupportCollection
    {
        $term = trim($this->search);

        if (mb_strlen($term) < 3) {
            return collect();
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
        $readable = fn (Builder $meeting): Builder => $this->readable($meeting);
        $hits = collect();

        MeetingDecision::query()->whereHas('meeting', $readable)->where('body', 'like', $like)
            ->with('meeting.minutes')->orderBy('id')->limit(self::LIMIT)->get()
            ->each(fn (MeetingDecision $decision) => $hits->push($this->hit($decision->meeting, __('Decision'), $decision->body, $term)));

        MeetingAgendaItem::query()->whereHas('meeting', $readable)
            ->where(fn (Builder $query) => $query->where('title', 'like', $like)->orWhere('discussion', 'like', $like)->orWhere('description', 'like', $like))
            ->with('meeting.minutes')->orderBy('id')->limit(self::LIMIT)->get()
            ->each(fn (MeetingAgendaItem $point) => $hits->push($this->hit($point->meeting, $point->title, implode("\n\n", array_filter([$point->title, $point->description, $point->discussion])), $term)));

        MeetingActionItem::query()->whereHas('meeting', $readable)
            ->where(fn (Builder $query) => $query->where('title', 'like', $like)->orWhere('description', 'like', $like))
            ->with('meeting.minutes')->orderBy('id')->limit(self::LIMIT)->get()
            ->each(fn (MeetingActionItem $action) => $hits->push($this->hit($action->meeting, __('Action'), implode("\n\n", array_filter([$action->title, $action->description])), $term)));

        // Announcements are stored as JSON, accents escaped: searched once read.
        MeetingMinutes::query()->whereHas('meeting', $readable)->with('meeting.minutes')->orderBy('id')->get()
            ->each(function (MeetingMinutes $minutes) use ($hits, $term): void {
                if (filled($minutes->notes) && mb_stripos(Markdown::toPlainText($minutes->notes), $term) !== false) {
                    $hits->push($this->hit($minutes->meeting, __('Outside the agenda'), $minutes->notes, $term));
                }

                foreach ($minutes->announcements ?? [] as $announcement) {
                    if (filled($announcement) && mb_stripos(Markdown::toPlainText($announcement), $term) !== false) {
                        $hits->push($this->hit($minutes->meeting, __('Announcements'), $announcement, $term));
                    }
                }
            });

        return $hits
            ->sortByDesc(fn (array $hit): int => $hit['meeting']->scheduled_at?->timestamp ?? 0)
            ->take(self::LIMIT)
            ->values();
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
            ->add(__('Meetings'), route('admin.meetings.index'))
            ->current(__('Register of minutes'));
    }

    /** Some words either side of the first match, escaped, the match marked. */
    private function excerpt(string $text, string $term): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $position = mb_stripos($text, $term);

        if ($position === false) {
            return e(mb_strimwidth($text, 0, 180, '…'));
        }

        $start = max(0, $position - 70);
        $before = ($start > 0 ? '…' : '') . mb_substr($text, $start, $position - $start);
        $match = mb_substr($text, $position, mb_strlen($term));
        $afterStart = $position + mb_strlen($term);
        $after = mb_substr($text, $afterStart, 90) . ($afterStart + 90 < mb_strlen($text) ? '…' : '');

        return e($before) . '<mark class="rounded bg-warning/30 px-0.5 text-base-content">' . e($match) . '</mark>' . e($after);
    }

    /**
     * @return array{meeting: Meeting, where: string, excerpt: string, url: string}
     */
    private function hit(Meeting $meeting, string $where, string $markdown, string $term): array
    {
        return [
            'meeting' => $meeting,
            'where' => $where,
            'excerpt' => $this->excerpt(Markdown::toPlainText($markdown), $term),
            'url' => $meeting->minutes?->is_published
                ? route('meetings.minutes.read', $meeting)
                : route('admin.meetings.minutes', $meeting),
        ];
    }

    /**
     * Meetings whose minutes this reader may read here: published, or any
     * begun for whoever runs the meetings.
     *
     * @param  Builder<Meeting>  $meeting
     * @return Builder<Meeting>
     */
    private function readable(Builder $meeting): Builder
    {
        return $meeting->whereHas('minutes', fn (Builder $minutes) => $this->canManage ? $minutes : $minutes->where('is_published', true));
    }
};
