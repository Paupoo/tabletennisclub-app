<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

/**
 * Provides bulk selection for Livewire list components.
 *
 * Implementing class must define:
 *   - getPageIds(): array          — string IDs of the current paginated page
 *   - getTotalMatchingCount(): int — total records matching current filters
 *   - matchingQuery(): Builder     — the list's query, filters applied, unpaginated
 *   - selectionScope(): array      — the properties that decide which rows the list holds
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
     * A digest of the selection scope's values at the last render: the next
     * render compares against it to tell whether the list changed under the
     * selection.
     */
    #[Locked]
    public string $selectionScopeDigest = '';

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

    /**
     * The properties that decide which rows the list holds: search, filters,
     * tab, default view. When one of them changes value, the selection is
     * dropped — it was made on rows the screen may no longer show.
     *
     * Sorting and paging show the same rows in another order or slice: leave
     * them out, as well as modal and form fields.
     *
     * @return array<int, string>
     */
    abstract protected function selectionScope(): array;

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectAll = false;
        $this->selectingAllResults = false;
    }

    /**
     * Drops the selection when the list changed since the last render.
     *
     * Compared at render rather than in an `updated` hook: a filter moves
     * through `wire:model`, but also through a chip's `removeFilter()`, a
     * `clearFilters()` or a tab method, none of which fires `updated`. The
     * values are what decides, whatever path changed them. The first render
     * only records them.
     */
    public function renderingHasBulkActions(): void
    {
        $digest = hash('xxh128', (string) json_encode(array_map(
            fn (string $property): mixed => $this->{$property},
            $this->selectionScope(),
        )));

        if ($this->selectionScopeDigest !== '' && $this->selectionScopeDigest !== $digest) {
            $this->clearSelection();
        }

        $this->selectionScopeDigest = $digest;
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
