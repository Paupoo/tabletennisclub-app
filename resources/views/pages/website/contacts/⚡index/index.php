<?php

declare(strict_types=1);

namespace Resources\views\Pages\Website\Contacts\Index;

use App\Actions\User\OnboardFromContactAction;
use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\Shared\Enums\ContactReasonEnum;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Services\ClubAdmin\Contact\ContactEmailService;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, WithPagination, HasBreadcrumbs;
    use HasBulkActions, HasFilterDrawer;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $interest = '';

    /** @var array{column: string, direction: string} */
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    public bool $detailOpen = false;

    public ?int $selectedContactId = null;

    public bool $emailModal = false;

    public string $emailSubject = '';

    public string $emailBody = '';

    public bool $emailCopy = false;

    public bool $deleteModal = false;

    public ?int $deletingId = null;

    public bool $confirmBulkDeleteModal = false;

    // ── HasBulkActions ────────────────────────────────────────────────────────

    /** @return array<int, string> */
    protected function getPageIds(): array
    {
        return $this->contacts
            ->pluck('id')
            ->map(fn (int $id) => (string) $id)
            ->toArray();
    }

    public function getTotalMatchingCount(): int
    {
        return $this->contacts->total();
    }

    // ── HasFilterDrawer ───────────────────────────────────────────────────────

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        if (filled($this->status)) {
            $chips[] = [
                'key'   => 'status',
                'label' => match ($this->status) {
                    'new'       => __('Status') . ': ' . __('New'),
                    'pending'   => __('Status') . ': ' . __('Pending'),
                    'processed' => __('Status') . ': ' . __('Processed'),
                    'rejected'  => __('Status') . ': ' . __('Rejected'),
                    default     => __('Status') . ': ' . $this->status,
                },
            ];
        }

        if (filled($this->interest)) {
            $label = collect(ContactReasonEnum::cases())
                ->first(fn ($r) => $r->value === $this->interest)?->getLabel() ?? $this->interest;

            $chips[] = ['key' => 'interest', 'label' => __('Interest') . ': ' . $label];
        }

        return $chips;
    }

    public function clearFilters(): void
    {
        $this->status   = '';
        $this->interest = '';
        $this->resetPage();
    }

    // ── Bulk actions ──────────────────────────────────────────────────────────

    public function confirmBulkDelete(): void
    {
        $this->confirmBulkDeleteModal = true;
    }

    public function bulkDelete(): void
    {
        $count = count($this->selected);
        Contact::whereIn('id', $this->selected)->delete();
        $this->confirmBulkDeleteModal = false;
        $this->clearSelection();
        $this->error(trans_choice('selectedCount', $count, ['count' => $count]) . ' ' . __('deleted.'));
    }

    // ── Filter hooks ──────────────────────────────────────────────────────────

    public function updatedSearch(): void   { $this->resetPage(); }

    public function updatedStatus(): void   { $this->resetPage(); }

    public function updatedInterest(): void { $this->resetPage(); }

    // ── Single-record actions ─────────────────────────────────────────────────

    public function openDetail(int $id): void
    {
        $this->selectedContactId = $id;
        $this->detailOpen        = true;
    }

    public function updateStatus(int $id, string $status): void
    {
        Contact::findOrFail($id)->update(['status' => $status]);
        $this->success(__('Status updated.'));
    }

    public function sendTemplateEmail(string $template): void
    {
        $contact = Contact::findOrFail($this->selectedContactId);
        $message = app(ContactEmailService::class)->sendTemplate($contact, $template);
        $this->success($message);
    }

    public function sendCustomEmail(): void
    {
        $this->validate([
            'emailSubject' => ['required', 'string', 'max:255'],
            'emailBody'    => ['required', 'string'],
        ]);

        $contact = Contact::findOrFail($this->selectedContactId);
        $service = new ContactEmailService;
        $service->sendCustom(
            $contact,
            ['subject' => $this->emailSubject, 'body' => $this->emailBody],
            Auth::user(),
            $this->emailCopy,
        );

        $this->emailModal   = false;
        $this->emailSubject = '';
        $this->emailBody    = '';
        $this->emailCopy    = false;

        $this->success(__('Email sent.'));
    }

    public function onboardContact(int $id): void
    {
        $contact = Contact::findOrFail($id);

        OnboardFromContactAction::handle($contact, Auth::user());

        $this->success(__('User created and invitation sent to :email.', ['email' => $contact->email]));
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId  = $id;
        $this->deleteModal = true;
    }

    public function delete(): void
    {
        Contact::findOrFail($this->deletingId)->delete();
        $this->deleteModal = false;
        $this->deletingId  = null;
        $this->detailOpen  = false;
        $this->error(__('Contact deleted.'));
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    #[Computed]
    public function contacts(): LengthAwarePaginator
    {
        return Contact::query()
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->status, fn ($q) => $q->byStatus($this->status))
            ->when($this->interest, fn ($q) => $q->where('interest', $this->interest))
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(20);
    }

    /** @return array<int, array{key: string, label: string}> */
    #[Computed]
    public function filterChips(): array
    {
        return $this->getFilterChips();
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Contacts'));
    }

    public function render(): View
    {
        return $this->view();
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
            'breadcrumbs'     => $this->getBreadcrumbs(),
            'contacts'        => $this->contacts,
            'stats'           => $stats,
            'statusOptions'   => $statusOptions,
            'interestOptions' => $interestOptions,
            'selectedContact' => $selectedContact,
            'headers'         => $headers,
            'filterChips'     => $this->filterChips,
        ];
    }
};
