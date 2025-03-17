<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\ForceList;
use Livewire\Component;
use Livewire\WithPagination;

class UsersTable extends Component
{
    use WithPagination;

    protected ForceList $forceList;
    
    public int $perPage = 10;

    public string $search = '';

    public string $competitor = '';

    public string $sortByField = '';

    public string $sortDirection = 'desc';

    public function boot(ForceList $forceList)
    {
        $this->forceList = $forceList;
    }

    public function sortBy($field)
    {
        if($this->sortByField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        };

        $this->sortByField = $field;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', User::class);

        $user->delete();

        $this->forceList->setOrUpdateAll();

        session()->flash('deleted', 'User ' . $user->first_name . ' ' . $user->last_name . ' has been deleted');
        
        $this->redirectRoute('users.index');
    }

    public function render()
    {
        return view('livewire.users-table', [
            'users' => User::search($this->search)
                ->when($this->competitor !== '', function($query) {
                    $query->where('is_competitor', $this->competitor);
                })
                ->when($this->sortByField === '', function($query) {
                    $query->orderby('is_competitor', 'desc')
                    ->orderby('force_list')
                    ->orderBy('ranking')
                    ->orderby('last_name')
                    ->orderby('first_name')
                    ->with('teams');
                })
                ->when($this->sortByField !== '', function($query) {
                    $query->orderBy($this->sortByField, $this->sortDirection);
                })
                ->paginate(20),
            'user_model' => User::class,
        ]);
    }
}
