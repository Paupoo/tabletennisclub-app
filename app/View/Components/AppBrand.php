<?php

declare(strict_types=1);

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AppBrand extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return <<<'HTML'
                   
                <a href="{{ route('dashboard') }}" wire:navigate>
                    <!-- Hidden when collapsed -->
                    <div {{ $attributes->class(["hidden-when-collapsed"]) }}>
                        <div class="flex flex-col items-center shrink-0 gap-2">
                            <x-logo class="block w-auto text-club-blue fill-current h-9 dark:text-gray-200 group-hover:text-club-blue-light transition-colors duration-200" />
                            <span class="hidden sm:block ml-4 text-lg font-bold text-club-blue dark:text-gray-200 group-hover:text-club-blue-light transition-colors duration-200">
                                {{ config('app.name', 'Club') }}
                            </span>
                        </div>
                    </div>

                    <!-- Display when collapsed -->
                    <div class="display-when-collapsed hidden mx-5 mt-5 mb-1 h-[28px]">
                        <x-logo class="block w-auto text-club-blue fill-current h-9 dark:text-gray-200 group-hover:text-club-blue-light transition-colors duration-200" />
                    </div>
                </a>

            HTML;
    }
}
