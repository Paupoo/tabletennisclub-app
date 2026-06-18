<?php

declare(strict_types=1);

namespace Resources\views\Pages\Website\Contacts\Index;

use App\Actions\User\OnboardFromContactAction;
use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use App\Domains\Shared\Enums\AgeCategoryEnum;
use App\Domains\Shared\Enums\ContactReasonEnum;
use App\Domains\Shared\Enums\PlayerExperienceEnum;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Services\ClubAdmin\Contact\ContactEmailService;
use App\Services\ClubAdmin\Contact\EmailTemplateRenderer;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast, WithPagination;
    use HasBulkActions, HasFilterDrawer;

    #[Url]
    public string $ageCategory = '';

    public bool $confirmBulkDeleteModal = false;

    public bool $deleteModal = false;

    public ?int $deletingId = null;

    public bool $detailOpen = false;

    public string $emailBody = '';

    public bool $emailCopy = false;

    // ── Email composer ─────────────────────────────────────────────────────────

    public bool $emailModal = false;

    public string $emailSubject = '';

    #[Url]
    public string $experience = '';

    #[Url]
    public string $interest = '';

    public ?string $pendingApplyStatus = null;

    // ── Profile capture (drawer, incremental — decision #15) ───────────────────

    public string $profileAgeCategory = '';

    public string $profileExperience = '';

    /** Tri-state: '1' yes, '0' no, '' not provided. */
    public string $profileFamilyCanDrive = '';

    /** @var array<int, string> */
    public array $profilePreferredDays = [];

    /** Tri-state: '1' yes, '0' no, '' not provided. */
    public string $profileWantsCompetition = '';

    #[Url]
    public string $search = '';

    public ?int $selectedContactId = null;

    public string $selectedTemplateKey = '';

    /** @var array{column: string, direction: string} */
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    #[Url]
    public string $status = '';

    #[Url]
    public string $wantsCompetition = '';

    // ── Email composer ─────────────────────────────────────────────────────────

    /**
     * Resolve the chosen template against the selected contact, pre-fill the
     * editor (decision #13) and remember its status to apply on send.
     */
    public function applyTemplate(): void
    {
        $this->authorizeManagement();

        if ($this->selectedTemplateKey === '') {
            return;
        }

        $contact = Contact::findOrFail($this->selectedContactId);
        $rendered = app(EmailTemplateRenderer::class)->render($this->selectedTemplateKey, $contact);

        $this->emailSubject = $rendered['subject'];
        $this->emailBody = $rendered['body'];
        $this->pendingApplyStatus = $rendered['apply_status'];
        $this->emailModal = true;

        // The dropdown is only a trigger: reset it so the placeholder shows
        // again and re-picking the same template re-opens the editor.
        $this->selectedTemplateKey = '';
    }

    public function bulkDelete(): void
    {
        $contacts = Contact::whereIn('id', $this->selected)->get();
        $count = $contacts->count();
        $contacts->each(fn (Contact $contact) => $contact->delete());
        $this->confirmBulkDeleteModal = false;
        $this->clearSelection();
        $this->error(trans_choice('selectedCount', $count, ['count' => $count]) . ' ' . __('deleted.'));
    }

    public function clearFilters(): void
    {
        $this->status = '';
        $this->interest = '';
        $this->ageCategory = '';
        $this->experience = '';
        $this->wantsCompetition = '';
        $this->resetPage();
    }

    /**
     * Close the editor and clear its state so re-selecting the same template
     * re-triggers the open.
     */
    public function closeEmailModal(): void
    {
        $this->reset(['emailModal', 'selectedTemplateKey', 'emailSubject', 'emailBody', 'pendingApplyStatus', 'emailCopy']);
    }

    // ── Bulk actions ──────────────────────────────────────────────────────────

    public function confirmBulkDelete(): void
    {
        $this->confirmBulkDeleteModal = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->deleteModal = true;
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    #[Computed]
    public function contacts(): LengthAwarePaginator
    {
        return Contact::query()
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->status, fn ($q) => $q->byStatus($this->status))
            ->when($this->interest, fn ($q) => $q->where('interest', $this->interest))
            ->when($this->ageCategory, fn ($q) => $q->where('age_category', $this->ageCategory))
            ->when($this->experience, fn ($q) => $q->where('experience', $this->experience))
            ->when($this->wantsCompetition !== '', fn ($q) => $q->where('wants_competition', $this->wantsCompetition === '1'))
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(20);
    }

    public function delete(): void
    {
        Contact::findOrFail($this->deletingId)->delete();
        $this->deleteModal = false;
        $this->deletingId = null;
        $this->detailOpen = false;
        $this->error(__('Contact deleted.'));
    }

    /** @return array<int, array{key: string, label: string}> */
    #[Computed]
    public function filterChips(): array
    {
        return $this->getFilterChips();
    }

    // ── HasFilterDrawer ───────────────────────────────────────────────────────

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        if (filled($this->status)) {
            $chips[] = [
                'key' => 'status',
                'label' => match ($this->status) {
                    'new' => __('Status') . ': ' . __('New'),
                    'pending' => __('Status') . ': ' . __('Pending'),
                    'processed' => __('Status') . ': ' . __('Processed'),
                    'rejected' => __('Status') . ': ' . __('Rejected'),
                    default => __('Status') . ': ' . $this->status,
                },
            ];
        }

        if (filled($this->interest)) {
            $label = collect(ContactReasonEnum::cases())
                ->first(fn ($r) => $r->value === $this->interest)?->getLabel() ?? $this->interest;

            $chips[] = ['key' => 'interest', 'label' => __('Interest') . ': ' . $label];
        }

        if (filled($this->ageCategory)) {
            $label = AgeCategoryEnum::tryFrom($this->ageCategory)?->getLabel() ?? $this->ageCategory;

            $chips[] = ['key' => 'ageCategory', 'label' => __('Age category') . ': ' . $label];
        }

        if (filled($this->experience)) {
            $label = PlayerExperienceEnum::tryFrom($this->experience)?->getLabel() ?? $this->experience;

            $chips[] = ['key' => 'experience', 'label' => __('Experience') . ': ' . $label];
        }

        if (filled($this->wantsCompetition)) {
            $chips[] = [
                'key' => 'wantsCompetition',
                'label' => __('Competition') . ': ' . ($this->wantsCompetition === '1' ? __('Yes') : __('No')),
            ];
        }

        return $chips;
    }

    public function getTotalMatchingCount(): int
    {
        return $this->contacts->total();
    }

    public function onboardContact(int $id): void
    {
        $this->authorizeManagement();

        $contact = Contact::findOrFail($id);

        OnboardFromContactAction::handle($contact, Auth::user());

        $this->success(__('User created and invitation sent to :email.', ['email' => $contact->email]));
    }

    /**
     * Open the editor for a blank, from-scratch custom email.
     */
    public function openCustomEmail(): void
    {
        $this->authorizeManagement();

        $this->reset(['selectedTemplateKey', 'emailSubject', 'emailBody', 'pendingApplyStatus']);
        $this->emailModal = true;
    }

    // ── Single-record actions ─────────────────────────────────────────────────

    public function openDetail(int $id): void
    {
        $this->selectedContactId = $id;
        $this->detailOpen = true;

        $this->loadProfile(Contact::findOrFail($id));
    }

    public function render(): View
    {
        return $this->view();
    }

    public function sendCustomEmail(): void
    {
        $this->authorizeManagement();

        $this->validate([
            'emailSubject' => ['required', 'string', 'max:255'],
            'emailBody' => ['required', 'string'],
        ]);

        $contact = Contact::findOrFail($this->selectedContactId);
        $service = new ContactEmailService;
        $service->sendCustom(
            $contact,
            ['subject' => $this->emailSubject, 'body' => $this->emailBody],
            Auth::user(),
            $this->emailCopy,
        );

        $allowed = ['new', 'pending', 'processed', 'rejected'];
        if ($this->pendingApplyStatus !== null && in_array($this->pendingApplyStatus, $allowed, true)) {
            $contact->update(['status' => $this->pendingApplyStatus]);
        }

        $this->emailModal = false;
        $this->emailSubject = '';
        $this->emailBody = '';
        $this->emailCopy = false;
        $this->selectedTemplateKey = '';
        $this->pendingApplyStatus = null;

        $this->success(__('Email sent.'));
    }

    public function updateContactProfile(): void
    {
        $this->authorizeManagement();

        $contact = Contact::findOrFail($this->selectedContactId);

        $contact->update([
            'age_category' => $this->profileAgeCategory ?: null,
            'experience' => $this->profileExperience ?: null,
            'wants_competition' => $this->triStateToBool($this->profileWantsCompetition),
            'family_can_drive' => $this->triStateToBool($this->profileFamilyCanDrive),
            'preferred_days' => $this->profilePreferredDays ?: null,
        ]);

        $this->success(__('Profile saved.'));
    }

    public function updatedAgeCategory(): void
    {
        $this->resetPage();
    }

    public function updatedExperience(): void
    {
        $this->resetPage();
    }

    public function updatedInterest(): void
    {
        $this->resetPage();
    }

    // ── Filter hooks ──────────────────────────────────────────────────────────

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Selecting a template in the dropdown immediately opens the editor
     * pre-filled with its (variable-resolved) content.
     */
    public function updatedSelectedTemplateKey(string $value): void
    {
        if ($value !== '') {
            $this->applyTemplate();
        }
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedWantsCompetition(): void
    {
        $this->resetPage();
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->authorizeManagement();

        Contact::findOrFail($id)->update(['status' => $status]);
        $this->success(__('Status updated.'));
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $stats = Contact::getStatusStats();

        $statusOptions = [
            ['id' => 'new',       'name' => __('New')],
            ['id' => 'pending',   'name' => __('Pending')],
            ['id' => 'processed', 'name' => __('Processed')],
            ['id' => 'rejected',  'name' => __('Rejected')],
        ];

        $interestOptions = collect(ContactReasonEnum::cases())
            ->map(fn ($r) => ['id' => $r->value, 'name' => $r->getLabel()]);

        // Explicit child → teen → adult order (Pint sorts enum cases alphabetically).
        $ageCategoryOptions = collect([
            AgeCategoryEnum::CHILD,
            AgeCategoryEnum::TEEN,
            AgeCategoryEnum::ADULT,
        ])->map(fn (AgeCategoryEnum $c) => ['id' => $c->value, 'name' => $c->getLabel()]);

        $experienceOptions = collect([
            PlayerExperienceEnum::NONE,
            PlayerExperienceEnum::FEW_MONTHS,
            PlayerExperienceEnum::FEW_YEARS,
            PlayerExperienceEnum::RANKED,
        ])->map(fn (PlayerExperienceEnum $e) => ['id' => $e->value, 'name' => $e->getLabel()]);

        $triStateOptions = [
            ['id' => '1', 'name' => __('Yes')],
            ['id' => '0', 'name' => __('No')],
        ];

        $dayOptions = collect([
            ['id' => 'monday',    'name' => __('Monday')],
            ['id' => 'tuesday',   'name' => __('Tuesday')],
            ['id' => 'wednesday', 'name' => __('Wednesday')],
            ['id' => 'thursday',  'name' => __('Thursday')],
            ['id' => 'friday',    'name' => __('Friday')],
            ['id' => 'saturday',  'name' => __('Saturday')],
            ['id' => 'sunday',    'name' => __('Sunday')],
        ]);

        $templateOptions = EmailTemplate::query()
            ->active()
            ->orderBy('name')
            ->get(['key', 'name'])
            ->map(fn (EmailTemplate $t) => ['id' => $t->key, 'name' => $t->name]);

        $canManage = Gate::allows('manage-contacts');

        $selectedContact = $this->selectedContactId
            ? Contact::find($this->selectedContactId)
            : null;

        $headers = [
            ['key' => 'full_name',  'label' => __('Name'),     'sortable' => false],
            ['key' => 'email',      'label' => __('Email'),     'class' => 'hidden sm:table-cell', 'sortable' => false],
            ['key' => 'interest',   'label' => __('Interest'),  'class' => 'hidden md:table-cell', 'sortable' => false],
            ['key' => 'status',     'label' => __('Status'),    'sortable' => false],
            ['key' => 'created_at', 'label' => __('Date'),      'class' => 'hidden lg:table-cell'],
        ];

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'contacts' => $this->contacts,
            'stats' => $stats,
            'statusOptions' => $statusOptions,
            'interestOptions' => $interestOptions,
            'ageCategoryOptions' => $ageCategoryOptions,
            'experienceOptions' => $experienceOptions,
            'triStateOptions' => $triStateOptions,
            'dayOptions' => $dayOptions,
            'templateOptions' => $templateOptions,
            'canManage' => $canManage,
            'selectedContact' => $selectedContact,
            'headers' => $headers,
            'filterChips' => $this->filterChips,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Contacts'));
    }

    // ── HasBulkActions ────────────────────────────────────────────────────────

    /** @return array<int, string> */
    protected function getPageIds(): array
    {
        return $this->contacts
            ->pluck('id')
            ->map(fn (int $id) => (string) $id)
            ->toArray();
    }

    // ── Authorization (decision #18) ───────────────────────────────────────────

    /**
     * Ensure the current user belongs to the club-admin management group before
     * running a management action (profile edit, email send, status apply).
     * Non-managers may view but not manage.
     */
    private function authorizeManagement(): void
    {
        Gate::authorize('manage-contacts');
    }

    private function boolToTriState(?bool $value): string
    {
        return match ($value) {
            true => '1',
            false => '0',
            default => '',
        };
    }

    // ── Profile capture (incremental, all optional — decision #15) ─────────────

    private function loadProfile(Contact $contact): void
    {
        $this->profileAgeCategory = $contact->age_category?->value ?? '';
        $this->profileExperience = $contact->experience?->value ?? '';
        $this->profileWantsCompetition = $this->boolToTriState($contact->wants_competition);
        $this->profileFamilyCanDrive = $this->boolToTriState($contact->family_can_drive);
        $this->profilePreferredDays = $contact->preferred_days ?? [];
    }

    private function triStateToBool(string $value): ?bool
    {
        return match ($value) {
            '1' => true,
            '0' => false,
            default => null,
        };
    }
};
