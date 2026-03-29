<?php

use App\Models\ClubAdmin\Users\User;
use App\Support\Breadcrumb;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;


new #[Title('My settings')] class extends Component
{
    use Toast;

    public User $user;

    public bool $public_profile = true;

    public bool $public_phone_number = false;

    public bool $public_email = false;

    public bool $notification_match = true;

    public bool $notification_team_result = false;

    public bool $notification_new_training = true;

    public bool $notification_waiting_list = true;

    public bool $notification_news_events = false;

    public string $password;

    public string $password_confirmation;

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Personnal Settings'))
                ->toArray(),
        ];
    }

    public function rules(): array
    {
        return [
            'password' => [
                'nullable',
                'confirmed',
                Password::min(8)->letters()->numbers()->symbols()->uncompromised(),
            ],
            'password_confirmation' => 'nullable',
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        if (! empty($validated['password'])) {
            Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        unset($validated['password_confirmation']);

        $this->user->update($validated);

        $this->reset([
            'password',
            'password_confirmation',
        ]);

        $this->success(__('Your settings have been updated.'));
    }
};
