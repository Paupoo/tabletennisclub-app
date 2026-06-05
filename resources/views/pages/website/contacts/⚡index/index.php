<?php

declare(strict_types=1);

namespace Resources\views\Pages\Website\Contacts\Index;

use App\Actions\User\OnboardFromContactAction;
use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\Shared\Enums\ContactReasonEnum;
use App\Services\ClubAdmin\Contact\ContactEmailService;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
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

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $interest = '';

    public bool $showFilters = false;

    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    public bool $detailOpen = false;

    public ?int $selectedContactId = null;

    public bool $emailModal = false;

    public string $emailSubject = '';

    public string $emailBody = '';

    public bool $emailCopy = false;

    public bool $deleteModal = false;

    public ?int $deletingId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedInterest(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'interest']);
        $this->resetPage();
    }

    #[Computed]
    public function activeFiltersCount(): int
    {
        return collect([$this->status, $this->interest])
            ->filter(fn ($v) => filled($v))
            ->count();
    }

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


    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__("Contacts"));
    }

        public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        $contacts = Contact::query()
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->status, fn ($q) => $q->byStatus($this->status))
            ->when($this->interest, fn ($q) => $q->where('interest', $this->interest))
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(20);

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
            ['key' => 'full_name', 'label' => __('Name'), 'sortable' => false],
            ['key' => 'email', 'label' => __('Email'), 'class' => 'hidden sm:table-cell', 'sortable' => false],
            ['key' => 'interest', 'label' => __('Interest'), 'class' => 'hidden md:table-cell', 'sortable' => false],
            ['key' => 'status', 'label' => __('Status'), 'sortable' => false],
            ['key' => 'created_at', 'label' => __('Date'), 'class' => 'hidden lg:table-cell'],
        ];

        return [
            'breadcrumbs'     => Breadcrumb::make()->home()->add('Website', '#')->current('Contacts')->toArray(),
            'contacts'        => $contacts,
            'stats'           => $stats,
            'statusOptions'   => $statusOptions,
            'interestOptions' => $interestOptions,
            'selectedContact' => $selectedContact,
            'headers'         => $headers,
        ];
    }
};
