---
name: bulk-actions
description: "Use when implementing or modifying bulk actions (select multiple rows + apply an action) or filter drawers in any admin list view. Covers adding HasBulkActions/HasFilterDrawer traits to a component, creating filter chips, adding a selection-pill to the blade, and connecting modals for destructive bulk actions. Activate whenever the user asks to add checkboxes, bulk select, mass delete/archive/activate, or a filter panel to an admin Livewire list component."
license: MIT
metadata:
  author: aurelien
---

# Bulk Actions & Filter Drawer Pattern

This application uses a standardized system for bulk actions and filter drawers across all admin list views.

## Architecture

### Traits (implement these in each list component)

**`App\Livewire\Concerns\HasBulkActions`**
Provides: `$selected[]`, `$selectAll`, `$selectingAllResults`, `$selectionModeActive`, `clearSelection()`, `toggleSelectionMode()`, `selectAllResults()`, and the selection reset when the list changes (`renderingHasBulkActions()`)

Must implement in the component:
```php
protected function getPageIds(): array           // string IDs of current page
public function getTotalMatchingCount(): int      // total matching filters
protected function matchingQuery(): Builder       // the list's query, filters applied, NOT paginated
protected function selectionScope(): array        // names of the properties that decide which rows the list holds
```

**`matchingQuery()` is the single source of what the list shows.** The paginated computed is built on it (`$this->matchingQuery()->with(...)->orderBy(...)->orderBy('<table>.id')->paginate(N)`), and the trait's `selectAllResults()` plucks every key from it (order dropped). So "Select all N results" really puts the N ids in `$selected`, and every bulk action simply reads `$this->selected` — never re-query on `$selectingAllResults`, never override `selectAllResults()`.

- Put search, filters, tabs and visibility rules (e.g. drafts hidden from readers) in `matchingQuery()`; put eager loads, `withCount`, ordering and `paginate()` in the computed.
- Group every `orWhere` of a filter in a `where(fn)` — `when()` opens no parenthesis (see the treasury `applyFilters()`).
- The paginated query ends with `->orderBy('<table>.id')`: without a total order, MySQL serves ties in a different order on each page.
- Bulk actions receive ids the page never showed (and a client can send any id): re-check the permission server-side in the action **and** in its `confirm…()` opener (`Gate::authorize(...)` / `abort_unless($this->canManage, 403)`), and re-check per-row eligibility (`canBeDeleted()`, state machine…) inside the action. Hide the pill for readers.
- Test each list with more matching rows than one page plus rows the filter excludes: `selectAllResults` must hold all of the former, none of the latter, and the bulk action must reach rows beyond page one (see `tests/Feature/SelectAllResultsTest.php`).

**`selectionScope()` drops a selection the list no longer matches.** List every property that changes *which rows* the list holds — search, each filter, the tab, the default-view toggle (`allMembers`). The trait's `renderingHasBulkActions()` hook digests their values at each render and calls `clearSelection()` when the digest differs from the previous render's (kept in the `#[Locked] $selectionScopeDigest`). Comparing values at render catches every path: `wire:model`, `removeFilter()`, `clearFilters()`, a tab method — an `updated` hook would miss all but the first. No component code to add for the reset itself, and no `clearSelection()` in `updatedX()` hooks.

- Leave out `sortBy`, the paginator's page, `selected`/`selectAll`, modal flags and modal form fields: sorting and paging show the same rows, so the selection stays.
- A new filter property that is not listed keeps the selection alive across its changes — add it to `selectionScope()` in the same change. A typo in the list fails loudly (undefined property).
- Test: search → selection empty; filter → empty; sort → kept; page → kept (see `tests/Feature/SelectionResetTest.php`).

**`App\Livewire\Concerns\HasFilterDrawer`**
Provides: `$filterDrawer`, `removeFilter(string $key)`, `clearFilters()` (override)

Must implement in the component:
```php
public function getFilterChips(): array  // [['key' => 'status', 'label' => 'Active'], ...]
public function clearFilters(): void     // reset all filter properties to defaults
```

### Blade components (shared, use in all list views)

- `<x-admin.shared.filter-chips :chips="$filterChips" />` — chips row below header
- `<x-admin.shared.filter-drawer :title="__('Filters')">` — right drawer wrapper
- `<x-admin.shared.selection-pill :selected="$selected" :total="..." :selecting-all-results="$selectingAllResults" :select-all="$selectAll">` — floating centered pill (Notion style)

### Chosen design variant: **Floating Pill (Variant C)**

Use `<x-admin.shared.selection-pill>` in all list views. It is the only selection component in the codebase — the full-width `selection-bar` variant was deleted once the pill was chosen.

The floating pill is fixed `bottom-6`, centered, `rounded-2xl shadow-2xl`. It floats over the content — does not push it down and does not span the full width.

Demo pages have been deleted after the variant was chosen.

## Minimal implementation template

```php
// In the component PHP:
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;

new class extends Component
{
    use Toast, WithPagination;
    use HasBulkActions, HasFilterDrawer;

    // Filters
    public string $search = '';
    public string $status = '';

    // Implement abstract methods
    protected function getPageIds(): array
    {
        return $this->items->pluck('id')->map(fn ($id) => (string) $id)->toArray();
    }

    public function getTotalMatchingCount(): int
    {
        return $this->items->total();
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return $this->matchingQuery()
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->orderBy('my_models.id') // total order
            ->paginate(20);
    }

    /** @return Builder<MyModel> */
    protected function matchingQuery(): Builder
    {
        return MyModel::query()
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%{$this->search}%")
                ->orWhere('body', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('status', $this->status));
    }

    /** @return array<int, string> */
    protected function selectionScope(): array
    {
        return ['search', 'status'];
    }

    public function getFilterChips(): array
    {
        return array_filter([
            $this->status ? ['key' => 'status', 'label' => $this->status] : null,
        ]);
    }

    public function clearFilters(): void
    {
        $this->reset(['status']);
        $this->resetPage();
    }

    // Bulk actions
    public function bulkDelete(): void
    {
        Gate::authorize(Permission::MyModelsManage->value);

        // $selected holds every matching id after "select all results".
        MyModel::whereIn('id', $this->selected)->delete();
        $this->clearSelection();
        $this->success(__('Deleted.'));
    }
}
```

```blade
{{-- In the blade: --}}

<x-header>
    <x-slot:middle>
        <x-input wire:model.live.debounce.300ms="search" ... />
    </x-slot:middle>
    <x-slot:actions>
        {{-- Filter button --}}
        <x-button icon="o-funnel" wire:click="$set('filterDrawer', true)">
            @if (count($filterChips) > 0)
                <x-badge value="{{ count($filterChips) }}" />
            @endif
        </x-button>
        {{-- Mobile select toggle --}}
        <x-button class="lg:hidden" wire:click="toggleSelectionMode"
            icon="{{ $selectionModeActive ? 'o-x-mark' : 'o-check-circle' }}"
            label="{{ $selectionModeActive ? 'Cancel' : 'Select' }}" />
    </x-slot:actions>
</x-header>

{{-- Active filter chips --}}
<x-admin.shared.filter-chips :chips="$filterChips" />

{{-- Mobile list: checkboxes visible only in selectionModeActive --}}
@if ($selectionModeActive)
    <input type="checkbox" wire:model.live="selected" value="{{ $item->id }}" />
@endif

{{-- Desktop table --}}
<x-table selectable wire:model.live="selected" ...>

{{-- Floating Pill — bulk actions (Variant C, chosen) --}}
<x-admin.shared.selection-pill
    :selected="$selected"
    :total="$this->getTotalMatchingCount()"
    :selecting-all-results="$selectingAllResults"
    :select-all="$selectAll">
    <x-slot:actions>
        <x-button wire:click="bulkDelete" label="Delete" class="btn-ghost btn-sm text-error" />
    </x-slot:actions>
</x-admin.shared.selection-pill>

{{-- Filter drawer --}}
<x-admin.shared.filter-drawer>
    <x-slot:filters>
        <x-select wire:model.live="status" ... />
    </x-slot:filters>
</x-admin.shared.filter-drawer>
```

## Rules

- **Array properties** (e.g. `$categories[]`): override `removeFilter()` in the component to handle them properly (use `categories_VALUE` key pattern, see demo A).
- **Complex bulk actions** (require a select/input): use a modal that opens after clicking the action button, never inline in the selection pill.
- **Destructive actions** always require a `<x-confirm-modal>` before execution.
- **Never forget the mobile toggle**: add `wire:click="toggleSelectionMode"` button in the header actions, visible only on mobile (`lg:hidden`).
- **Spacer**: the `selection-pill` component automatically adds a bottom spacer — do not add one manually.
