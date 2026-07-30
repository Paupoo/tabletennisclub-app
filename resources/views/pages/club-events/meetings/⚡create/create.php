<?php

declare(strict_types=1);

use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingFormatEnum;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast;

    /** 'fixed' or 'poll' */
    public string $dateMode = 'fixed';

    /** @var array<int, array{proposed_at: string}> */
    public array $dateProposals = [['proposed_at' => ''], ['proposed_at' => '']];

    #[Validate('nullable|date')]
    public ?string $endsAt = null;

    #[Validate('nullable|date')]
    public ?string $scheduledAt = null;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('required|string')]
    public string $type = 'committee';

    public function addDateProposal(): void
    {
        $this->dateProposals[] = ['proposed_at' => ''];
    }

    public function removeDateProposal(int $index): void
    {
        array_splice($this->dateProposals, $index, 1);
    }

    public function render(): View
    {
        return $this->view();
    }

    /** Create the meeting and land on its hub — nothing is sent at this point. */
    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:' . implode(',', array_column(MeetingTypeEnum::cases(), 'value')),
        ]);

        if ($this->dateMode === 'fixed') {
            $this->validate([
                'scheduledAt' => 'required|date',
                'endsAt' => 'nullable|date|after_or_equal:scheduledAt',
            ]);
        } else {
            $proposals = collect($this->dateProposals)->filter(fn (array $p) => filled($p['proposed_at']));
            if ($proposals->isEmpty()) {
                $this->addError('dateProposals', __('At least one date proposal is required.'));

                return;
            }
        }

        $isFixed = $this->dateMode === 'fixed';
        $scheduledAt = $isFixed ? Carbon::parse($this->scheduledAt) : null;

        $meeting = Meeting::create([
            'title' => $this->title,
            'type' => $this->type,
            'format' => MeetingFormatEnum::PHYSICAL,
            'status' => $isFixed ? MeetingStatusEnum::CONFIRMED : MeetingStatusEnum::PLANNING,
            'scheduled_at' => $scheduledAt,
            'ends_at' => $isFixed
                ? (filled($this->endsAt) ? $this->endsAt : $scheduledAt->copy()->addHours(2))
                : null,
            'created_by' => auth()->id(),
        ]);

        if (! $isFixed) {
            foreach ($this->dateProposals as $proposal) {
                if (filled($proposal['proposed_at'])) {
                    $meeting->dateProposals()->create(['proposed_at' => $proposal['proposed_at']]);
                }
            }
        }

        $this->toast(type: 'success', title: __('Meeting created'));
        $this->redirectRoute('admin.meetings.show', $meeting, navigate: true);
    }

    #[Computed]
    public function typeOptions(): array
    {
        return MeetingTypeEnum::getOptions();
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
            ->current(__('Create'));
    }
};
