<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * Provides a right-side filter drawer and active filter chips for Livewire list components.
 *
 * Implementing class must define:
 *   - getFilterChips(): array — active filters as [['key' => string, 'label' => string], ...]
 *   - clearFilters(): void   — resets all filter properties to defaults
 *
 * Requires: Livewire\WithPagination (for resetPage in removeFilter)
 */
trait HasFilterDrawer
{
    /** Whether the filter drawer is open */
    public bool $filterDrawer = false;

    /**
     * Returns the currently active filters as displayable chips.
     * Each entry must have 'key' (the property name to reset) and 'label' (display text).
     *
     * @return array<int, array{key: string, label: string}>
     */
    abstract public function getFilterChips(): array;

    /**
     * Resets all filter properties to their defaults.
     * Override this in the component with component-specific reset logic.
     */
    public function clearFilters(): void
    {
        // Override in the component.
    }

    /**
     * Removes a single filter chip by resetting its corresponding property.
     * For array-type properties (e.g. $categories), override this in the component.
     */
    public function removeFilter(string $key): void
    {
        $this->reset([$key]);
        $this->resetPage();
    }
}
