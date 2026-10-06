<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use HasBreadcrumbs;

    /**
     * @return Collection<int, FeedbackCampaign>
     */
    #[Computed]
    public function campaigns(): Collection
    {
        return FeedbackCampaign::query()
            ->withCount('responses')
            ->orderByDesc('opens_on')
            ->orderByDesc('id')
            ->get();
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
            ->add(__('Feedback and suggestions'), route('admin.feedback.index'))
            ->current(__('Yearly surveys'));
    }
};
