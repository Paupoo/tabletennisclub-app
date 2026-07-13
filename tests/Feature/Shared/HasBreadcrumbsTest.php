<?php

declare(strict_types=1);

use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;

describe('HasBreadcrumbs trait', function (): void {
    it('provides getBreadcrumbs() returning an array', function (): void {
        $component = new class
        {
            use HasBreadcrumbs;
        };

        expect($component->getBreadcrumbs())->toBeArray();
    });

    it('default breadcrumbChain returns home item', function (): void {
        $component = new class
        {
            use HasBreadcrumbs;
        };

        $items = $component->getBreadcrumbs();

        expect($items)->toHaveCount(1)
            ->and($items[0]['label'])->toBe(__('Admin Panel'))
            ->and($items[0]['icon'])->toBe('s-home');
    });

    it('subclass can override breadcrumbChain()', function (): void {
        $component = new class
        {
            use HasBreadcrumbs;

            protected function breadcrumbChain(): Breadcrumb
            {
                return Breadcrumb::make()
                    ->home()
                    ->current('Users');
            }
        };

        $items = $component->getBreadcrumbs();

        expect($items)->toHaveCount(2)
            ->and($items[0]['label'])->toBe(__('Admin Panel'))
            ->and($items[1]['label'])->toBe('Users')
            ->and($items[1]['link'])->toBeNull();
    });

    it('returns full chain with multiple items', function (): void {
        $component = new class
        {
            use HasBreadcrumbs;

            protected function breadcrumbChain(): Breadcrumb
            {
                return Breadcrumb::make()
                    ->home()
                    ->add('Section', '/section')
                    ->current('Page');
            }
        };

        $items = $component->getBreadcrumbs();

        expect($items)->toHaveCount(3)
            ->and($items[1]['label'])->toBe('Section')
            ->and($items[1]['link'])->toBe('/section')
            ->and($items[2]['label'])->toBe('Page')
            ->and($items[2]['link'])->toBeNull();
    });

    it('returns empty array when chain is empty', function (): void {
        $component = new class
        {
            use HasBreadcrumbs;

            protected function breadcrumbChain(): Breadcrumb
            {
                return Breadcrumb::make();
            }
        };

        expect($component->getBreadcrumbs())->toBe([]);
    });
})->group('Breadcrumbs');
