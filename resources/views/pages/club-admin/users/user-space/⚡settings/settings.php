<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Notifications\GdprErasureRequestedNotification;
use App\Domains\ClubAdmin\Users\Services\WhoDoesWhat;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\AccountProxy;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('My settings')] class extends Component
{
    use HasBreadcrumbs, Toast;

    // ── The « Who does what » page (committee members and duty holders only)
    public string $dutyBlurb = '';

    public bool $dutyShareEmail = true;

    public bool $dutySharePhone = false;

    public bool $listedOnWhoDoesWhat = false;

    public bool $notifyAvailabilityRequests = true;

    public bool $notifyInterclubSelections = true;

    public bool $notifyNewTournaments = true;

    public string $password = '';

    public string $password_confirmation = '';

    // ── Contact visibility (opt-in per field)
    public bool $shareAddress = false;

    public bool $shareEmail = false;

    public bool $sharePhone = false;

    public User $user;

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;
        $this->notifyNewTournaments = $user->wantsNotification('new_tournaments');
        $this->notifyAvailabilityRequests = $user->wantsNotification('availability_requests');
        $this->notifyInterclubSelections = $user->wantsNotification('interclub_selections');
        $this->sharePhone = $user->sharesContact('phone');
        $this->shareEmail = $user->sharesContact('email');
        $this->shareAddress = $user->sharesContact('address');
        $this->listedOnWhoDoesWhat = app(WhoDoesWhat::class)->lists($user);
        $this->dutyShareEmail = $user->sharesDutyContact('email');
        $this->dutySharePhone = $user->sharesDutyContact('phone');
        $this->dutyBlurb = $user->duty_blurb ?? '';
    }

    /**
     * Self-service GDPR erasure request. Lives here (account settings) rather
     * than on the profile page, which is about presenting the member.
     */
    public function requestErasure(): void
    {
        abort_unless(Auth::user()->is($this->user), 403);
        AccountProxy::denyWhenActing();

        // Idempotent: one request = one notification, keep the original request date.
        if ($this->user->gdpr_erasure_requested_at) {
            $this->success(__('Erasure request sent. The admin will process it shortly.'));

            return;
        }

        $this->user->update(['gdpr_erasure_requested_at' => now()]);

        $recipients = User::query()
            ->where('id', '!=', $this->user->id)
            ->where(function ($query): void {
                $query->whereHas('roles', fn ($q) => $q->where('name', Role::ADMINISTRATOR->value))
                    ->orWhere('committee_role', CommitteeRolesEnum::SECRETARY->value);
            })
            ->get();

        Notification::send($recipients, new GdprErasureRequestedNotification($this->user));

        $this->success(__('Erasure request sent. The admin will process it shortly.'));
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

    public function saveDutyBlurb(): void
    {
        abort_unless(Auth::user()->is($this->user), 403);

        $this->validate(['dutyBlurb' => ['nullable', 'string', 'max:280']]);

        $this->user->update(['duty_blurb' => filled($this->dutyBlurb) ? trim($this->dutyBlurb) : null]);

        $this->success(__('Your word for the members is saved.'), position: 'toast-bottom toast-end');
    }

    /**
     * Notification toggles save themselves as soon as they are flipped —
     * no extra submit button for a one-click preference.
     */
    public function updated(string $property): void
    {
        abort_unless(Auth::user()->is($this->user), 403);

        if (str_starts_with($property, 'notify')) {
            $this->user->update([
                'notification_preferences' => [
                    'new_tournaments' => $this->notifyNewTournaments,
                    'availability_requests' => $this->notifyAvailabilityRequests,
                    'interclub_selections' => $this->notifyInterclubSelections,
                ],
            ]);

            $this->success(__('Notification preferences saved.'), position: 'toast-bottom toast-end');

            return;
        }

        // Both families of toggles live in the same column: each writes its own
        // keys over what is stored, so flipping one never resets the other.
        if (str_starts_with($property, 'dutyShare')) {
            $this->user->update([
                'contact_visibility' => [
                    ...$this->user->contact_visibility ?? [],
                    'duty_email' => $this->dutyShareEmail,
                    'duty_phone' => $this->dutySharePhone,
                ],
            ]);

            $this->success(__('Privacy preferences saved.'), position: 'toast-bottom toast-end');

            return;
        }

        if (str_starts_with($property, 'share')) {
            $this->user->update([
                'contact_visibility' => [
                    ...$this->user->contact_visibility ?? [],
                    'phone' => $this->sharePhone,
                    'email' => $this->shareEmail,
                    'address' => $this->shareAddress,
                ],
            ]);

            $this->success(__('Privacy preferences saved.'), position: 'toast-bottom toast-end');
        }
    }

    public function updatePassword(): void
    {
        abort_unless(Auth::user()->is($this->user), 403);
        AccountProxy::denyWhenActing();

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
