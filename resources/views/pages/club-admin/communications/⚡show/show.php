<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Communications\Actions\RetryFailedRecipients;
use App\Domains\ClubAdmin\Communications\Models\Communication;
use App\Domains\ClubAdmin\Communications\Models\CommunicationRecipient;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use App\Support\Markdown;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * One communication: what it said, how far the throttled sending has gone,
 * and which addresses it failed to reach — with a retry that writes to them
 * alone. The page polls while messages are still leaving.
 */
new class extends Component
{
    use HasBreadcrumbs, Toast;

    public Communication $communication;

    #[Computed]
    public function bodyHtml(): string
    {
        return Markdown::safe($this->communication->body);
    }

    /** @return array{sent: int, failed: int, pending: int} */
    #[Computed]
    public function progress(): array
    {
        $counts = $this->communication->recipients()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'sent' => (int) ($counts[CommunicationRecipient::STATUS_SENT] ?? 0),
            'failed' => (int) ($counts[CommunicationRecipient::STATUS_FAILED] ?? 0),
            'pending' => (int) ($counts[CommunicationRecipient::STATUS_PENDING] ?? 0),
        ];
    }

    /** @return Collection<int, CommunicationRecipient> */
    #[Computed]
    public function recipients(): Collection
    {
        return $this->communication->recipients()
            ->orderByRaw("case status when 'failed' then 0 when 'pending' then 1 else 2 end")
            ->orderBy('email')
            ->orderBy('id')
            ->get();
    }

    public function render(): View
    {
        return $this->view()->title($this->communication->subject);
    }

    public function retryFailed(): void
    {
        $count = app(RetryFailedRecipients::class)($this->communication);

        unset($this->progress, $this->recipients);

        $this->success(trans_choice('Sending again to :count address.|Sending again to :count addresses.', $count));
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
            ->add(__('History'), route('admin.communications.history'))
            ->current($this->communication->subject);
    }
};
