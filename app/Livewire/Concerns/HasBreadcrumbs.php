<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Support\Breadcrumb;

/**
 * Trait for admin Livewire components to enforce breadcrumbs.
 *
 * Usage:
 *   use HasBreadcrumbs;
 *
 *   public function render()
 *   {
 *       return view(..., [
 *           'breadcrumbs' => $this->getBreadcrumbs(),
 *       ])->layout('layouts.app');
 *   }
 *
 * Subclass must implement: protected function breadcrumbChain(): Breadcrumb
 */
trait HasBreadcrumbs
{
    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbChain()->toArray();
    }

    /**
     * Override in subclass to define breadcrumb chain.
     * Example:
     *   return Breadcrumb::make()
     *       ->home()
     *       ->add('Users', route('admin.users.index'))
     *       ->current('Edit');
     */
    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()->home();
    }
}
