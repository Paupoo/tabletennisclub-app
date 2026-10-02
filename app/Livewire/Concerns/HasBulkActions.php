<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Provides bulk selection for Livewire list components.
 *
 * Implementing class must define:
 *   - getPageIds(): array          — string IDs of the current paginated page
 *   - getTotalMatchingCount(): int — total records matching current filters
 *   - matchingQuery(): Builder     — the list's query, filters applied, unpaginated
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

    /**
     * The records the list shows across all its pages: the same query as the
     * paginated one, search and filters applied, before `paginate()`.
     *
     * "Select all results" reads its ids from here, so the list and the
     * selection must share this query — two copies have drifted before, and a
     * bulk action then touched rows the banner never counted.
     *
     * @return Builder<covariant Model>
     */
    abstract protected function matchingQuery(): Builder;

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectAll = false;
        $this->selectingAllResults = false;
    }

    /**
     * Escalate the selection to every record the filters match, on every page
     * (Gmail pattern).
     *
     * The banner announces "all N results selected": the selection holds those
     * N ids, so a bulk action reading `$selected` reaches every one of them.
     * The order is dropped — it decides nothing here, and a sort on a computed
     * column has no business in a list of keys.
     */
    public function selectAllResults(): void
    {
        $query = $this->matchingQuery();

        $this->selectingAllResults = true;
        $this->selected = $query
            ->reorder()
            ->pluck($query->getModel()->getQualifiedKeyName())
            ->unique()
            ->map(fn (int|string $id): string => (string) $id)
            ->values()
            ->all();
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
