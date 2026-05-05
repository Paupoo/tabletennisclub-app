<?php

declare(strict_types=1);

use App\Support\Breadcrumb;
use Livewire\Component;

new class extends Component
{
    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current('Event Subscription')
                ->toArray(),
        ];
    }
};
