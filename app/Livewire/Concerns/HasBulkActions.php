<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * Provides bulk selection for Livewire list components.
 *
 * Implementing class must define:
 *   - getPageIds(): array         — string IDs of the current paginated page
 *   - getTotalMatchingCount(): int — total records matching current filters
 *
 * Requires: Livewire\WithPagination (for selectAllResults to make sense)
 */
trait HasBulkActions
{
    /** Whether the "select all on this page" checkbox is checked */
    public bool $selectAll = false;

    /** @var array<int, string> IDs (as strings) of selected items */
    public array $selected = [];

    /** Whether the user has escalated to "select ALL results" (Gmail pattern) */
    public bool $selectingAllResults = false;

    /** Mobile: whether selection mode is active (shows checkboxes in list view) */
    public bool $selectionModeActive = false;

    /**
     * Returns string IDs of all items on the current paginated page.
     * Implement in the component using the paginator result.
     *
     * @return array<int, string>
     */
    abstract protected function getPageIds(): array;

    /**
     * Returns total count of records matching the current filter state.
     * Typically: $this->yourPaginator->total()
     */
    abstract public function getTotalMatchingCount(): int;

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectAll = false;
        $this->selectingAllResults = false;
    }

    /**
     * Escalate selection to all results matching current filters (Gmail pattern).
     * NOTE: The IDs returned here are page-only; each component may override
     * this to fetch all matching IDs if the action requires it server-side.
     */
    public function selectAllResults(): void
    {
        $this->selectingAllResults = true;
        $this->selected = $this->getPageIds();
    }

    /** Toggle mobile selection mode; clears selection on exit. */
    public function toggleSelectionMode(): void
    {
        $this->selectionModeActive = ! $this->selectionModeActive;

        if (! $this->selectionModeActive) {
            $this->clearSelection();
        }
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selected = $value ? $this->getPageIds() : [];
        $this->selectingAllResults = false;
    }
}
