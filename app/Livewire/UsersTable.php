<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use App\Services\ForceList;
use Illuminate\Database\QueryException;
use Livewire\Component;
use Livewire\WithPagination;

class UsersTable extends Component
{
    use WithPagination;

    public string $competitor = '';

    public string $sex = '';

    public int $perPage = 20;

    public string $search = '';

    public string $sortByField = '';

    public string $sortDirection = 'desc';

    protected ForceList $forceList;

    public function boot(ForceList $forceList)
    {
        $this->forceList = $forceList;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', User::class);

        try {
            $user->delete();
            $this->forceList->setOrUpdateAll();
            session()->flash('warning', __('User ' . $user->first_name . ' ' . $user->last_name . ' has been deleted'));
            $this->redirectRoute('users.index');
        } catch (QueryException $e) {
            if ($user->tournaments()->whereIn('status', ['draft', 'open', 'pending'])->count() > 0) {
                session()->flash('error', __('Cannot delete ' . $user->first_name . ' ' . $user->last_name . ' because he subscribed to one or more tournaments'));
                $this->redirectRoute('users.index');
            }
        }

    }

    public function render()
    {
        return view('livewire.users-table', [
            'users' => User::search($this->search)
                ->when($this->competitor !== '', function ($query): void {
                    $query->where('is_competitor', $this->competitor);
                })
                ->when($this->sex !== '', function ($query): void {
                    $query->where('sex', $this->sex);
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
                ->paginate($this->perPage),
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
}
