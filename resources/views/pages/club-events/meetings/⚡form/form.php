<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\MeetingFormatEnum;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Jobs\SendMeetingInvitationsJob;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingAgendaItem;
use App\Domains\Meetings\Models\MeetingDateProposal;
use App\Domains\Meetings\Notifications\MeetingDatePollNotification;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, HasBreadcrumbs;

    #[Locked]
    public ?int $meetingId = null;

    // Step navigation
    public string $step = '1';

    // Mode exclusif : date fixe ou sondage
    public bool $datePollMode = false;

    // Step 1 — Basic info
    #[Validate('required|string|max:255')]
    public string $title           = '';

    #[Validate('required|string')]
    public string $type            = 'committee';

    #[Validate('required|string')]
    public string $format          = 'physical';

    #[Validate('nullable|string')]
    public string $description     = '';

    #[Validate('nullable|string|max:500')]
    public string $location        = '';

    #[Validate('nullable|url|max:500')]
    public string $meetingLink     = '';

    #[Validate('nullable|date')]
    public ?string $scheduledAt    = null;

    #[Validate('nullable|date')]
    public ?string $endsAt         = null;

    #[Validate('nullable|date')]
    public ?string $rsvpDeadline   = null;

    #[Validate('boolean')]
    public bool   $hasMeal         = false;

    #[Validate('nullable|string|max:255')]
    public string $mealDescription = '';

    #[Validate('nullable|numeric|min:0')]
    public string $mealPrice       = '';

    #[Validate('nullable|integer|min:1')]
    public ?int   $quorum          = null;

    // Step 2 — Agenda items
    /** @var array<int, array{title: string, description: string}> */
    public array $agendaItems = [];

    // Step 3 — Date poll (uniquement si $datePollMode = true)
    /** @var array<int, array{proposed_at: string}> */
    public array $dateProposals = [];

    // State
    public bool $showCancelModal = false;

    protected function rules(): array
    {
        $rules = [
            'title'           => ['required', 'string', 'max:255'],
            'type'            => ['required', 'string', 'in:' . implode(',', array_column(MeetingTypeEnum::cases(), 'value'))],
            'format'          => ['required', 'string', 'in:' . implode(',', array_column(MeetingFormatEnum::cases(), 'value'))],
            'description'     => ['nullable', 'string'],
            'location'        => ['nullable', 'string', 'max:500'],
            'meetingLink'     => ['nullable', 'url', 'max:500'],
            'hasMeal'         => ['boolean'],
            'mealDescription' => ['nullable', 'string', 'max:255'],
            'mealPrice'       => ['nullable', 'numeric', 'min:0'],
            'quorum'          => ['nullable', 'integer', 'min:1'],
            'agendaItems'     => ['array'],
            'agendaItems.*.title'       => ['required', 'string', 'max:255'],
            'agendaItems.*.description' => ['nullable', 'string'],
        ];

        if (! $this->datePollMode) {
            $rules['scheduledAt']  = ['nullable', 'date'];
            $rules['endsAt']       = ['nullable', 'date', 'after_or_equal:scheduledAt'];
            $rules['rsvpDeadline'] = ['nullable', 'date'];
        } else {
            $rules['dateProposals']               = ['array'];
            $rules['dateProposals.*.proposed_at'] = ['required', 'date'];
        }

        return $rules;
    }

    public function updatedDatePollMode(): void
    {
        // Vider les champs de l'autre mode
        if ($this->datePollMode) {
            $this->scheduledAt  = null;
            $this->endsAt       = null;
            $this->rsvpDeadline = null;
        } else {
            $this->dateProposals = [];
        }
    }

    public function mount(?Meeting $meeting = null): void
    {
        if ($meeting?->exists) {
            $this->meetingId       = $meeting->id;
            $this->title           = $meeting->title;
            $this->type            = $meeting->type->value;
            $this->format          = $meeting->format->value;
            $this->description     = $meeting->description ?? '';
            $this->location        = $meeting->location ?? '';
            $this->meetingLink     = $meeting->meeting_link ?? '';
            $this->scheduledAt     = $meeting->scheduled_at?->format('Y-m-d\TH:i');
            $this->endsAt          = $meeting->ends_at?->format('Y-m-d\TH:i');
            $this->rsvpDeadline    = $meeting->rsvp_deadline?->format('Y-m-d');
            $this->hasMeal         = $meeting->has_meal;
            $this->mealDescription = $meeting->meal_description ?? '';
            $this->mealPrice       = $meeting->meal_price !== null ? (string) $meeting->meal_price : '';
            $this->quorum          = $meeting->quorum;

            $this->agendaItems = $meeting->agendaItems
                ->map(fn (MeetingAgendaItem $item) => [
                    'title'       => $item->title,
                    'description' => $item->description ?? '',
                ])
                ->values()
                ->toArray();

            $this->dateProposals = $meeting->dateProposals
                ->map(fn (MeetingDateProposal $p) => [
                    'proposed_at' => $p->proposed_at->format('Y-m-d\TH:i'),
                ])
                ->values()
                ->toArray();

            // Déduire le mode : sondage si pas de date fixée et des proposals existent
            $this->datePollMode = $meeting->scheduled_at === null && $meeting->dateProposals->isNotEmpty();
        }
    }

    // ── Navigation ───────────────────────────────────────────────────
    public function nextStep(): void
    {
        $current = (int) $this->step;

        // Validate current step before advancing
        match ($current) {
            1 => $this->validateStep1(),
            2 => $this->validateStep2(),
            default => null,
        };

        // Stop if validation failed
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        if ($current === 2 && ! $this->datePollMode) {
            $this->step = '3';
        } else {
            $this->step = (string) min($current + 1, 3);
        }
    }

    private function validateStep1(): void
    {
        $this->validate([
            'title'       => 'required|string|max:255',
            'type'        => 'required|string',
            'format'      => 'required|string',
            'location'    => $this->format === 'physical' ? 'required|string|max:500' : 'nullable|string|max:500',
            'meetingLink' => $this->format === 'virtual' ? 'required|url|max:500' : 'nullable|url|max:500',
        ]);

        // Validate date fields if not in poll mode
        if (! $this->datePollMode) {
            $this->validate([
                'scheduledAt'  => 'required|date',
                'endsAt'       => 'required|date|after_or_equal:scheduledAt',
                'rsvpDeadline' => 'nullable|date',
            ]);
        }

        // Validate meal fields if enabled
        if ($this->hasMeal) {
            $this->validate([
                'mealDescription' => 'required|string|max:255',
                'mealPrice'       => 'required|numeric|min:0',
            ]);
        }

        // Validate quorum if general assembly
        if ($this->type === 'general_assembly') {
            $this->validate([
                'quorum' => 'required|integer|min:1',
            ]);
        }
    }

    private function validateStep2(): void
    {
        // At least one agenda item with title required
        $hasValidItem = false;
        foreach ($this->agendaItems as $item) {
            if (filled($item['title'])) {
                $hasValidItem = true;
                break;
            }
        }

        if (! $hasValidItem) {
            $this->addError('agendaItems', __('At least one agenda item with a title is required.'));
            return;
        }

        // Validate each item
        $this->validate([
            'agendaItems.*.title'       => 'required|string|max:255',
            'agendaItems.*.description' => 'nullable|string',
        ]);
    }

    public function prevStep(): void
    {
        $current = (int) $this->step;

        if ($current === 3 && ! $this->datePollMode) {
            $this->step = '2';
        } else {
            $this->step = (string) max($current - 1, 1);
        }
    }

    // ── Agenda helpers ────────────────────────────────────────────────
    public function addAgendaItem(): void
    {
        $this->agendaItems[] = ['title' => '', 'description' => ''];
    }

    public function removeAgendaItem(int $index): void
    {
        array_splice($this->agendaItems, $index, 1);
    }

    public function moveAgendaItemUp(int $index): void
    {
        if ($index > 0) {
            [$this->agendaItems[$index - 1], $this->agendaItems[$index]] =
                [$this->agendaItems[$index], $this->agendaItems[$index - 1]];
            $this->agendaItems = array_values($this->agendaItems);
        }
    }

    public function moveAgendaItemDown(int $index): void
    {
        if ($index < count($this->agendaItems) - 1) {
            [$this->agendaItems[$index + 1], $this->agendaItems[$index]] =
                [$this->agendaItems[$index], $this->agendaItems[$index + 1]];
            $this->agendaItems = array_values($this->agendaItems);
        }
    }

    // ── Date poll helpers ─────────────────────────────────────────────
    public function addDateProposal(): void
    {
        $this->dateProposals[] = ['proposed_at' => ''];
    }

    public function removeDateProposal(int $index): void
    {
        array_splice($this->dateProposals, $index, 1);
    }

    // ── Save ──────────────────────────────────────────────────────────
    public function save(): void
    {
        // Validate core fields first before any action
        $this->validate([
            'title'       => 'required|string|max:255',
            'type'        => 'required|string',
            'format'      => 'required|string',
            'location'    => $this->format === 'physical' ? 'required|string|max:500' : 'nullable|string|max:500',
            'meetingLink' => $this->format === 'virtual' ? 'required|url|max:500' : 'nullable|url|max:500',
        ]);

        // Validate date-related fields based on mode
        if ($this->datePollMode) {
            $hasValidProposal = collect($this->dateProposals)->some(fn ($p) => filled($p['proposed_at']));
            if (! $hasValidProposal) {
                $this->addError('dateProposals', __('At least one date proposal is required.'));
                return;
            }
            $this->validate([
                'dateProposals.*.proposed_at' => 'required_with:dateProposals|date',
            ]);
        } else {
            $this->validate([
                'scheduledAt'  => 'required|date',
                'endsAt'       => 'required|date|after_or_equal:scheduledAt',
            ]);
        }

        $isNew = ($this->meetingId === null);

        // Validate agenda items only on creation
        if ($isNew) {
            $hasValidItem = false;
            foreach ($this->agendaItems as $item) {
                if (filled($item['title'])) {
                    $hasValidItem = true;
                    break;
                }
            }
            if (! $hasValidItem) {
                $this->addError('agendaItems', __('At least one agenda item with a title is required.'));
                return;
            }
            $this->validate([
                'agendaItems.*.title'       => 'required|string|max:255',
                'agendaItems.*.description' => 'nullable|string',
            ]);
        }

        $data = [
            'title'            => $this->title,
            'type'             => $this->type,
            'format'           => $this->format,
            'is_public'        => MeetingTypeEnum::from($this->type)->isPublic(),
            'description'      => filled($this->description) ? $this->description : null,
            'location'         => $this->format === 'physical' && filled($this->location) ? $this->location : null,
            'meeting_link'     => $this->format === 'virtual' && filled($this->meetingLink) ? $this->meetingLink : null,
            'scheduled_at'     => ! $this->datePollMode && filled($this->scheduledAt) ? $this->scheduledAt : null,
            'ends_at'          => ! $this->datePollMode && filled($this->endsAt) ? $this->endsAt : null,
            'rsvp_deadline'    => ! $this->datePollMode && filled($this->rsvpDeadline) ? $this->rsvpDeadline : null,
            'has_meal'         => $this->hasMeal,
            'meal_description' => $this->hasMeal && filled($this->mealDescription) ? $this->mealDescription : null,
            'meal_price_cents' => $this->hasMeal && filled($this->mealPrice) ? (int) round((float) $this->mealPrice * 100) : null,
            'quorum'           => $this->type === 'general_assembly' ? $this->quorum : null,
            'created_by'       => $isNew ? auth()->id() : Meeting::find($this->meetingId)?->created_by,
        ];

        $meeting = $isNew
            ? Meeting::create($data)
            : tap(Meeting::findOrFail($this->meetingId), fn ($m) => $m->update($data));

        // Sync agenda items
        $meeting->agendaItems()->delete();
        foreach ($this->agendaItems as $i => $item) {
            if (filled($item['title'])) {
                $meeting->agendaItems()->create([
                    'sort_order'  => $i,
                    'title'       => $item['title'],
                    'description' => filled($item['description']) ? $item['description'] : null,
                ]);
            }
        }

        // Sync date proposals
        if ($this->datePollMode && (! $this->meetingId || $meeting->status === MeetingStatusEnum::PLANNING)) {
            $meeting->dateProposals()->delete();
            foreach ($this->dateProposals as $proposal) {
                if (filled($proposal['proposed_at'])) {
                    $meeting->dateProposals()->create(['proposed_at' => $proposal['proposed_at']]);
                }
            }
        }

        // ── Auto-confirm date if not poll mode + date is set ──────────
        if (! $this->datePollMode && filled($this->scheduledAt)) {
            $meeting->update([
                'scheduled_at' => $this->scheduledAt,
                'ends_at'      => $this->endsAt,
                'status'       => MeetingStatusEnum::CONFIRMED,
            ]);
        }

        // ── Send notifications ────────────────────────────────────────
        if ($isNew) {
            if ($this->datePollMode && $meeting->dateProposals()->exists()) {
                $committee = User::where(fn ($q) => $q->where('is_admin', true)
                    ->orWhere('is_committee_member', true))->get();
                Notification::send($committee, new MeetingDatePollNotification($meeting));
            }
        }

        // Send invitations only on creation if date is confirmed
        if ($isNew && ! $this->datePollMode && filled($this->scheduledAt) && $meeting->status === MeetingStatusEnum::CONFIRMED) {
            dispatch(new SendMeetingInvitationsJob($meeting->id));
        }

        $this->meetingId = $meeting->id;

        $this->toast(
            type: 'success',
            title: $isNew ? __('Meeting created') : __('Meeting updated'),
        );

        $this->redirectRoute('admin.meetings.show', $meeting, navigate: true);
    }

    public function cancelMeeting(): void
    {
        if (! $this->meetingId) {
            return;
        }

        Meeting::findOrFail($this->meetingId)->update([
            'status' => MeetingStatusEnum::CANCELLED,
        ]);

        $this->toast(type: 'warning', title: __('Meeting cancelled'));
        $this->redirectRoute('admin.meetings.index', navigate: true);
    }

    #[Computed]
    public function currentMeeting(): ?Meeting
    {
        return $this->meetingId ? Meeting::find($this->meetingId) : null;
    }

    #[Computed]
    public function maxReachableStep(): int
    {
        return $this->meetingId ? 3 : 1;
    }

    #[Computed]
    public function typeOptions(): array
    {
        return MeetingTypeEnum::getOptions();
    }

    #[Computed]
    public function formatOptions(): array
    {
        return MeetingFormatEnum::getOptions();
    }

    #[Computed]
    public function inviteeCount(): int
    {
        return $this->type === 'general_assembly'
            ? User::where('is_active', true)->count()
            : User::where(fn ($q) => $q->where('is_admin', true)->orWhere('is_committee_member', true))->count();
    }

    #[Computed]
    public function inviteeLabel(): string
    {
        return $this->type === 'general_assembly'
            ? __('All active members')
            : __('Committee members');
    }


    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current($this->meetingId ? __("Edit") : __("Create"));
    }

        public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }
};
