<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\UserCalendarService;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use HasBreadcrumbs;

    public bool $icsModal = false;

    /** @var string[] */
    public array $selectedCategories = [];

    public bool $showAllEvents = false;

    public User $user;

    /**
     * Permanent signed URL of the member's personal ICS feed — the signature
     * is the secret, so the feed works without a session (calendar providers
     * poll it server-side).
     */
    #[Computed]
    public function icsUrl(): string
    {
        return URL::signedRoute('admin.user.calendar.ics', ['user' => $this->user]);
    }

    #[Computed]
    public function calendarData(): array
    {
        return app(UserCalendarService::class)
            ->eventsFor($this->user, $this->showAllEvents, $this->selectedCategories)
            ->groupBy('monthKey')
            ->map(fn ($group) => $group->values()->all())
            ->all();
    }

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'calendar' => $this->calendarData,
            'categories' => [
                ['id' => 'tournament', 'name' => __('Tournament')],
                ['id' => 'training',   'name' => __('Training')],
                ['id' => 'interclub',  'name' => __('Interclub')],
                ['id' => 'meeting',    'name' => __('Meeting')],
            ],
            'selectedCategories' => $this->selectedCategories,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Calendar'));
    }
};
