<?php

declare(strict_types=1);

use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\AutomaticEmails\AutomaticEmailAgenda;
use App\Support\AutomaticEmails\AutomaticEmailCatalogue;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

/*
 * Read-only: what the application sends by itself, beside the queue that
 * carries it. Decided on 2026-10-31 — the scheduled emails as an agenda, then
 * the others by domain.
 */
new class extends Component
{
    use HasBreadcrumbs;

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('view-queue-monitoring') ?? false, 403);
    }

    public function render(): View
    {
        return $this->view([
            ...app(AutomaticEmailAgenda::class)->build(AutomaticEmailCatalogue::emails()),
            'breadcrumbs' => $this->getBreadcrumbs(),
        ]);
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Automatic emails'));
    }
};
