<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rules & regulations')] class extends Component
{
    use HasBreadcrumbs;

    /**
     * Official AFTTB regulation (authoritative full text). The digest below is a
     * plain-language summary; this link always points to the federation source.
     */
    public const AFTTB_REGULATION_URL = 'https://aftt.be/index.php/base-de-donnees-des-documents/';

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
            'regulationUrl' => self::AFTTB_REGULATION_URL,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Rules & regulations'));
    }
};
