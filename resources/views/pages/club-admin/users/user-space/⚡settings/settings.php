<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('My settings')] class extends Component
{
    use HasBreadcrumbs, Toast;

    public string $password = '';

    public string $password_confirmation = '';

    public User $user;

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;
    }

    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],
            'password_confirmation' => 'required',
        ];
    }

    public function updatePassword(): void
    {
        abort_unless(Auth::user()->is($this->user), 403);

        $validated = $this->validate();

        $this->user->update(['password' => $validated['password']]);

        $this->reset(['password', 'password_confirmation']);

        $this->success(__('Your password has been updated.'));
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Personnal Settings'));
    }
};
