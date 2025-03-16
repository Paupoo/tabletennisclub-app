<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;

class UsersTable extends Component
{
    public function render()
    {
        return view('livewire.users-table', [
            'users' => User::orderby('is_competitor', 'desc')->with('teams')->orderby('force_list')->orderBy('ranking')->orderby('last_name')->orderby('first_name')->paginate(20),
            'user_model' => User::class,
        ]);
    }
}
