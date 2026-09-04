<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\CharterSignature;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use App\Support\ClubCharter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Club charter')] class extends Component
{
    use HasBreadcrumbs;

    public User $user;

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'chapters' => ClubCharter::chapters(),
            'values' => ClubCharter::values(),
            'signature' => $this->signature(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Club charter'));
    }

    /**
     * The member's signature for the running season, when there is one.
     *
     * Shown back to them because an engagement that leaves no trace once taken
     * reads as one more checkbox in a form.
     */
    protected function signature(): ?CharterSignature
    {
        $season = Season::current();

        if (! $season instanceof Season) {
            return null;
        }

        return CharterSignature::query()
            ->where('user_id', $this->user->id)
            ->where('season_id', $season->id)
            ->with('signedBy')
            ->first();
    }
};
