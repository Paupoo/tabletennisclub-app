<?php

declare(strict_types=1);

namespace Resources\views\Pages\Website\Contacts\Index;

use App\Enums\ContactReasonEnum;
use App\Models\ClubAdmin\Contact\Contact;
use App\Services\ClubAdmin\Contact\ContactEmailService;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $interest = '';

    public bool $detailOpen        = false;
    public ?int $selectedContactId = null;

    public bool $emailModal    = false;
    public string $emailSubject = '';
    public string $emailBody    = '';
    public bool $emailCopy      = false;

    public bool $deleteModal = false;
    public ?int $deletingId  = null;

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

    public function openDetail(int $id): void
    {
        $this->selectedContactId = $id;
        $this->detailOpen        = true;
    }

    public function updateStatus(int $id, string $status): void
    {
        Contact::findOrFail($id)->update(['status' => $status]);
        $this->success('Statut mis à jour.');
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

        $this->success('Email envoyé.');
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
        $this->error('Contact supprimé.');
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
            ->orderByDesc('created_at')
            ->paginate(20);

        $stats = Contact::getStatusStats();

        $statusOptions = [
            ['id' => 'new', 'name' => 'Nouveau'],
            ['id' => 'pending', 'name' => 'En cours'],
            ['id' => 'processed', 'name' => 'Traité'],
            ['id' => 'rejected', 'name' => 'Rejeté'],
        ];

        $interestOptions = collect(ContactReasonEnum::cases())
            ->map(fn ($r) => ['id' => $r->value, 'name' => $r->getLabel()]);

        $selectedContact = $this->selectedContactId
            ? Contact::find($this->selectedContactId)
            : null;

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add('Website', '#')
                ->current('Contacts')
                ->toArray(),
            'contacts'        => $contacts,
            'stats'           => $stats,
            'statusOptions'   => $statusOptions,
            'interestOptions' => $interestOptions,
            'selectedContact' => $selectedContact,
        ];
    }
};
