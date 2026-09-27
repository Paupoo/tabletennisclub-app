<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Communications\Models\Communication;
use App\Domains\ClubAdmin\Communications\Models\CommunicationRecipient;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * What the club has written to its members from the application, newest
 * first — who wrote it, to how many, and whether every address was reached.
 */
new class extends Component
{
    use HasBreadcrumbs, WithPagination;

    /** @return LengthAwarePaginator<int, Communication> */
    #[Computed]
    public function communications(): LengthAwarePaginator
    {
        return Communication::query()
            ->with('author')
            ->withCount([
                'recipients as sent_count' => fn ($query) => $query->where('status', CommunicationRecipient::STATUS_SENT),
                'recipients as failed_count' => fn ($query) => $query->where('status', CommunicationRecipient::STATUS_FAILED),
            ])
            ->orderByDesc('sent_at')
            ->orderByDesc('communications.id')
            ->paginate(20);
    }

    public function render(): View
    {
        return $this->view()->title(__('Communications history'));
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return ['breadcrumbs' => $this->getBreadcrumbs()];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->add(__('Communications'), route('admin.communications.index'))
            ->current(__('History'));
    }
};
