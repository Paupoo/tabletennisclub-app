<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Models\User;
use App\Services\ForceList;
use Exception;
use Illuminate\Database\QueryException;
use Livewire\Component;
use Livewire\WithPagination;

class UsersTable extends Component
{
    use WithPagination;

    public string $competitor = '';

    public int $perPage = 20;

    public string $search = '';

    public ?int $selectedUserId = null;

    public string $sex = '';

    public string $sortByField = '';

    public string $sortDirection = 'desc';

    public string $status = '';

    protected ForceList $forceList;

    public function boot(ForceList $forceList)
    {
        $this->forceList = $forceList;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        $user = User::findOrFail($this->selectedUserId);
        $this->authorize('delete', [Auth()->user(), $user]);
        try {
            if ($user->tournaments()->whereIn('status', ['draft', 'open', 'pending'])->count() > 0) {
                session()->flash('error', __('Cannot delete ' . $user->first_name . ' ' . $user->last_name . ' because he subscribed to one or more tournaments'));
                $this->redirectRoute('users.index');
            }
            $user->delete();
            $this->forceList->setOrUpdateAll();
            session()->flash('warning', __('User ' . $user->first_name . ' ' . $user->last_name . ' has been deleted'));
            $this->redirectRoute('users.index');
        } catch (QueryException $e) {
            

            session()->flash('error', __('The user could not be deleted'));
            $this->redirectRoute('users.index');
        }
    }

    public function render()
    {
        $users = User::searchTerms($this->search)
            ->when($this->competitor !== '', function ($query): void {
                $query->where('is_competitor', $this->competitor);
            })
            ->when($this->sex !== '', function ($query): void {
                $query->where('sex', $this->sex);
            })
            ->when($this->status === 'active', function ($query): void {
                $query->isActive();
            })
            ->when($this->status === 'inactive', function ($query): void {
                $query->where('is_active', false);
            })
            ->when($this->status === 'paid', function ($query): void {
                $query->where('has_paid', true);
            })
            ->when($this->status === 'unpaid', function ($query): void {
                $query->where('has_paid', false);
            })
            ->when($this->sortByField === '', function ($query): void {
                $query->orderby('is_competitor', 'desc')
                    ->orderby('force_list')
                    ->orderBy('ranking')
                    ->orderby('last_name')
                    ->orderby('first_name')
                    ->with('teams');
            })
            ->when($this->sortByField !== '', function ($query): void {
                $query->orderBy($this->sortByField, $this->sortDirection);
            })
            ->paginate($this->perPage);

        return view('livewire.admin.users.users-table', [
            'users' => $users,
            'user_model' => User::class,
        ]);
    }

    public function sortBy($field)
    {
        if ($this->sortByField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        }

        $this->sortByField = $field;
    }

    private function applySearch($query, string $search): void
    {
        $terms = collect(explode(' ', strtolower($search)))
            ->filter();

        foreach ($terms as $term) {
            $query->where(function ($subQuery) use ($term): void {
                $subQuery->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$term}%"]);
            });
        }
    }
}
