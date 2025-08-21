<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Contacts;

use App\Models\Contact;
use Illuminate\Contracts\Database\Query\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public string $search = '';

    public int $perPage = 25;
    public string $status = '';
    public string $interest = '';
    public string $sortByField = '';
    public string $sortDirection = 'desc';
    public ?int $selectedContactId = null;


    public function mount(): void {}

    public function render()
    {
        $contacts = Contact::search($this->search)
            ->when($this->status !== '', function (Builder $query): void {
                $query->where('status', $this->status); 
            })
            ->when($this->interest !== '', function (Builder $query): void {
                $query->where('interest', $this->interest);
            })
            ->when($this->sortByField !== '', function (Builder $query): void {
                $query->orderBy($this->sortByField, $this->sortDirection);
            })
            ->paginate($this->perPage);

        return view('livewire.admin.contacts.index', compact('contacts'));
    }

    public function sortBy(string $field): void {
        if($this->sortByField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        }

        $this->sortByField = $field;
    }

    public function deleteContact() {
        
        $this->authorize('delete', Auth()->user());

        $article = Contact::find($this->selectedContactId);
        $article->delete();

        session()->flash('success', __('The contact has been deleted.'));
        return $this->redirectRoute('admin.contacts.index');
    }
}
