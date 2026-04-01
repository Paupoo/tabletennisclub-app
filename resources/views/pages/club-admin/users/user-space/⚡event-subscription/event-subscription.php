<?php

use App\Support\Breadcrumb;
use Livewire\Component;
use Illuminate\View\View;

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