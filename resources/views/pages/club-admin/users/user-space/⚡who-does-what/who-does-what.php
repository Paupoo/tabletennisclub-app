<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\WhoDoesWhat;
use App\Domains\Shared\Enums\ClubDuty;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use HasBreadcrumbs;

    public User $user;

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function committee(): Collection
    {
        return app(WhoDoesWhat::class)->committee();
    }

    /**
     * @return Collection<int, array{duty: ClubDuty, holders: EloquentCollection<int, User>}>
     */
    #[Computed]
    public function duties(): Collection
    {
        return app(WhoDoesWhat::class)->duties();
    }

    /**
     * Same audience as the directory: the people behind the club are shown to
     * those who belong to it, and to the committee members who do not play.
     */
    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);
        abort_unless($user->is_active || $user->can(Permission::UsersView->value), 403);

        $this->user = $user;
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
            ->current(__('Who does what'));
    }
};
