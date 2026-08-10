# Design System

This document describes the UI conventions for this Laravel/Livewire application.
Stack: Tailwind CSS v4 · daisyUI v5 · Mary UI · Alpine.js v3.

---

## Colors

Custom club colors are defined as CSS variables in `resources/css/app.css`:

| Token | Hex | Usage |
|-------|-----|-------|
| `club-blue` | `#1e40af` | Primary brand color |
| `club-yellow` | `#fbbf24` | Secondary/accent |
| `club-blue-light` | `#3b82f6` | Lighter blue variant |
| `club-yellow-light`| `#fcd34d` | Lighter yellow variant |

daisyUI theme colors (use these for component styling):

| Class | Usage |
|-------|-------|
| `primary` | Main action, linked to `club-blue` |
| `secondary` | Secondary action, linked to `club-yellow` |
| `success` | Positive state |
| `error` | Destructive/danger state |
| `warning` | Caution |
| `info` | Informational |
| `base-100/200/300` | Background surfaces |

Transparency variants work with `/`: `bg-primary/10`, `text-base-content/60`.

**Text on a tinted background — always use the `-content` variant.** `warning`, `success`, `error` and `info` are tuned as light/vivid *background* swatches (e.g. `--color-warning` is 82% lightness). Used directly as text color (`text-warning`), they're illegible on light backgrounds. Use the matching `-content` token instead, which is tuned for contrast against `base-100`:

```blade
{{-- Alert pill / tinted badge --}}
<span class="bg-warning/10 text-warning-content border-warning/20 ...">...</span>

{{-- Wrong: text-warning is a background swatch, not a text color --}}
<span class="bg-warning/10 text-warning ...">...</span>
```

Plain `text-X` (without `-content`) is only correct for icons/text placed directly on a *solid* `bg-X` fill (e.g. `bg-warning text-warning-content` is the pairing for a solid badge — the bg uses the swatch, the text uses content).

---

## Typography

Font: **Instrument Sans** (system font fallback chain).

No custom type scale — use Tailwind defaults (`text-sm`, `text-base`, `text-lg`, etc.).

---

## Buttons — `<x-button>`

**Always use Mary UI's `x-button` with daisyUI variant classes.** Legacy custom button components (`x-primary-button`, `x-danger-button`, etc.) have been removed.

| Intent | Class |
|--------|-------|
| Primary action | `class="btn-primary"` |
| Secondary / ghost | `class="btn-ghost"` |
| Destructive | `class="btn-error"` |
| Success | `class="btn-success"` |
| Warning | `class="btn-warning"` |
| Outline only | `class="btn-outline"` |
| Soft | `class="btn-soft"` |

Size modifiers: `btn-xs`, `btn-sm`, `btn-lg`.

```blade
{{-- Submit button in a form --}}
<x-button type="submit" :label="__('Save')" class="btn-primary" />

{{-- Icon button --}}
<x-button icon="o-trash" class="btn-error btn-sm" wire:click="delete" spinner />

{{-- Disabled --}}
<x-button :label="__('Generate')" class="btn-primary" :disabled="$isLocked" />
```

---

## Form Fields — `<x-form.field>`

For Breeze-style inputs (`x-text-input`, `x-select-input`, `x-textarea-input`), wrap the triplet label + input + error in the `x-form.field` component:

```blade
{{-- Before (old pattern, do not use) --}}
<x-input-label for="name" :value="__('Name')" />
<x-text-input id="name" name="name" ... />
<x-input-error class="mt-2" :messages="$errors->get('name')" />

{{-- After --}}
<x-form.field name="name" :label="__('Name')">
    <x-text-input id="name" name="name" type="text" class="block w-full mt-1" ... />
</x-form.field>
```

**Note:** For Livewire/Mary UI forms using `<x-form wire:submit>`, prefer Mary UI's `x-input`, `x-select`, `x-checkbox` (which have built-in `label` props) — no `x-form.field` needed.

---

## Confirmation Modals — `<x-confirm-modal>`

Use `x-confirm-modal` for simple destructive confirmations:

```blade
<x-confirm-modal
    model="deleteModal"
    :title="__('Confirm deletion')"
    :subtitle="__('Warning!')"
    :confirmLabel="__('Delete')"
    confirmAction="delete">
    <p>{{ __('This action is irreversible.') }}</p>
</x-confirm-modal>
```

Props:
- `model` — Livewire property name controlling modal visibility
- `title` — modal title
- `subtitle` — optional subtitle (default: none)
- `confirmLabel` — confirm button text (default: `'Confirm'`)
- `confirmClass` — confirm button daisyUI class (default: `'btn-error'`)
- `confirmAction` — Livewire `wire:click` action name

For modals with complex content (forms, multi-step flows), use `x-modal` directly.

---

## Mary UI Component Inventory

Most common components used in this project:

| Component | Usage |
|-----------|-------|
| `x-button` | All interactive buttons |
| `x-input` | Livewire text/number inputs (with `label` prop) |
| `x-select` | Livewire select inputs |
| `x-checkbox` | Livewire checkboxes |
| `x-toggle` | Boolean toggles |
| `x-badge` | Status indicators, counts |
| `x-card` | Content containers (with `title`, `menu` slot, `shadow`) |
| `x-modal` | Dialog overlays (controlled via `wire:model`) |
| `x-header` | Section headings (with `title`, `subtitle`, `actions` slot) |
| `x-icon` | Heroicons wrapper (`o-`, `s-`, `m-` prefixes) |
| `x-alert` | Inline alert messages |
| `x-avatar` | User avatars |
| `x-breadcrumbs` | Page breadcrumb navigation |
| `x-tab` / `x-tabs` | Tabbed navigation |
| `x-choices` | Multi-select with search |
| `x-datepicker` | Date picker |
| `x-form` | Livewire form wrapper (with `wire:submit`) |

Full Mary UI docs: `vendor/robsontenorio/mary/`

---

## Alpine.js Patterns

### Prefer Livewire for state
Use `wire:model`, `wire:click`, `$wire.property = value` for server-side state. Use Alpine only for purely client-side UI (toggles, transitions, focus traps).

### Toggle / accordion
The `x-section-accordion` component already extracts the common pattern. Use it:

```blade
<x-section-accordion title="Section title">
    Content here
</x-section-accordion>
```

Avoid inline `x-data="{ open: false }"` duplicates.

### Complex state
For complex Alpine logic, extract to a JS module in `resources/js/components/` and reference with `x-data="myComponentObject"`.

---

## Tournament Views — Shared Partials

The tournament wizard has two navigation modes (tabs and steps). Shared content is extracted to:

| Partial (use `@include`) | Shared between |
|--------------------------|----------------|
| `components.admin.club-events.tournaments.partials.shared.invitations-content` | `tabs/invitations` · `steps/invitations` |
| `components.admin.club-events.tournaments.partials.shared.start-pools` | `tabs/start` · `steps/start` |

Pass `['isLocked' => $this->isLaunched]` when including from steps views to disable actions after tournament launch.

---

---

## UI Patterns — Standards de cohérence

This section defines **the one true way** to implement each recurring UI pattern. When adding a new admin page, start from these templates.

---

### Index Page — Structure complète

Every admin list page follows this structure (in order):

```
breadcrumbs → header (title | search | [filters] [create]) → filter-chips → mobile cards → desktop table → selection-pill → filter-drawer → modals
```

```blade
<div>
    {{-- 1. Breadcrumbs --}}
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    {{-- 2. Header --}}
    <x-header progress-indicator separator :title="__('Items')">
        <x-slot:middle>
            <x-input class="w-full" clearable icon="o-magnifying-glass"
                :placeholder="__('Search...')"
                wire:model.live.debounce.300ms="search" />
        </x-slot:middle>
        <x-slot:actions>
            <x-admin.shared.filters-button :count="count($filterChips)" />
            {{-- Short form (≤ 4 fields) → modal --}}
            <x-button class="btn-primary" icon="o-plus" :label="__('Create')"
                wire:click="$set('createModal', true)" />
            {{-- Long form (≥ 5 fields) → dedicated page --}}
            {{-- <x-button class="btn-primary" icon="o-plus" :label="__('Create')"
                link="{{ route('admin.xxx.create') }}" /> --}}
        </x-slot:actions>
    </x-header>

    {{-- 3. Active filters, as removable chips --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

    {{-- 4. Mobile view (cards) --}}
    <div class="grid grid-cols-1 gap-4 lg:hidden">
        @forelse ($items as $item)
            <x-list-item :item="$item" class="bg-base-100 rounded-lg border">
                <x-slot:value>{{ $item->name }}</x-slot:value>
                <x-slot:actions>
                    <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil"
                        :tooltip="__('Edit')" wire:click="openEdit({{ $item->id }})" />
                    <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-trash"
                        :tooltip="__('Delete')" wire:click="confirmDelete({{ $item->id }})" />
                </x-slot:actions>
            </x-list-item>
        @empty
            <x-empty-state
                icon="o-inbox"
                :heading="__('No items found')"
                :message="__('Try adjusting your search or filters.')" />
        @endforelse
    </div>

    {{-- 5. Desktop view (table) --}}
    <div class="hidden lg:block">
        <x-card>
            @if ($items->isEmpty())
                <x-empty-state
                    icon="o-inbox"
                    :heading="__('No items found')"
                    :message="__('Try adjusting your search or filters.')" />
            @else
                <x-table :headers="$headers" :rows="$items" :sort-by="$sortBy"
                    selectable wire:model.live="selected">
                    @scope('cell_name', $item)
                        <span class="font-medium">{{ $item->name }}</span>
                    @endscope
                    @scope('actions', $item)
                        <x-admin.shared.row-actions>
                            <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil"
                                :tooltip="__('Edit')" wire:click="openEdit({{ $item->id }})" />
                            <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-trash"
                                :tooltip="__('Delete')" wire:click="confirmDelete({{ $item->id }})" />
                        </x-admin.shared.row-actions>
                    @endscope
                </x-table>
                <div class="mt-4">{{ $items->links() }}</div>
            @endif
        </x-card>
    </div>

    {{-- 6. Bulk actions, floating over the list while a selection exists --}}
    <x-admin.shared.selection-pill
        :selected="$selected" :total="$items->total()"
        :selecting-all-results="$selectingAllResults" :select-all="$selectAll">
        <x-slot:actions>
            <x-button class="btn-ghost btn-sm text-error" icon="o-trash"
                :label="__('Delete')" wire:click="confirmBulkDelete" />
        </x-slot:actions>
    </x-admin.shared.selection-pill>

    {{-- 7. Filter drawer --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">
                    {{ __('Filter group label') }}
                </p>
                <x-radio wire:model.live="someFilter" :options="$filterOptions" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- 8. Modals --}}
    <x-confirm-modal model="deleteModal" :title="__('Confirm deletion')" :subtitle="__('Warning!')"
        :confirmLabel="__('Delete')" confirmAction="delete">
        <p>{{ __('This action is irreversible.') }}</p>
    </x-confirm-modal>
</div>
```

---

### Header — Slot rules

| Slot | Content | When |
|------|---------|------|
| `x-slot:middle` | Search input | Always (unless season selector is present) |
| `x-slot:middle` | Season `<x-select>` | When page is scoped by season (replaces search in middle) |
| `x-slot:actions` | `[Filters▾ N]` button | When page has filterable columns |
| `x-slot:actions` | `[+ Create]` button | Always |
| `x-slot:actions` | Search input | Only when middle is taken by season selector |

**Season selector edge case** — when a page is season-scoped, the season selector takes the middle slot and search moves to actions:

```blade
<x-header progress-indicator separator :title="__('Teams')">
    <x-slot:middle>
        <x-select :options="$seasons" option-label="name" option-value="id"
            wire:model.live="selectedSeasonId" :placeholder="__('Select a season')"
            class="w-48" />
    </x-slot:middle>
    <x-slot:actions>
        <x-input clearable icon="o-magnifying-glass" :placeholder="__('Search...')"
            wire:model.live.debounce.300ms="search" />
        <x-button class="btn-primary" icon="o-plus" :label="__('Create')" ... />
    </x-slot:actions>
</x-header>
```

---

### Search Input — Standard

Always use this exact form in `x-slot:middle`:

```blade
<x-input class="w-full" clearable icon="o-magnifying-glass"
    :placeholder="__('Search...')"
    wire:model.live.debounce.300ms="search" />
```

Rules:
- `class="w-full"` — fills the middle slot width
- `clearable` — always present
- `icon="o-magnifying-glass"` — always present
- `wire:model.live.debounce.300ms` — **300ms** is the standard debounce (not 250, not 500)
- Placeholder always translated via `__()`

---

### Filters — button + drawer + chips (standard)

**The standard for every list view, admin and member space alike** — even when the
page has a single filter (R-filtres, décidé le 2026-07-13) : the muscle memory of
committee members wearing both hats must be identical everywhere. If a drawer feels
empty with one checkbox, enrich the filters rather than inlining the checkbox.

- `App\Livewire\Concerns\HasFilterDrawer` in the component (`getFilterChips()`,
  `clearFilters()`; override `removeFilter()` when the page has no pagination or
  array-type filters).
- `<x-admin.shared.filters-button :count="count($filterChips)" />` in
  `x-slot:actions` (desktop) + `<x-admin.shared.mobile-header-actions
  :filter-count="…" />` (mobile).
- `<x-admin.shared.filter-chips :chips="$filterChips" />` right below the header.
- `<x-admin.shared.filter-drawer :title="__('Filters')">` at the end of the view.

**R2 — view modes are not filters.** A control that changes *what the page is*
("Mes événements / Tout le club") lives as a segmented control attached to the
content, always visible — never inside the filter drawer.

**R6 — group headers.** Two styles coexist, on purpose: grouping by **period**
uses the neutral `<x-section-accordion>`; grouping by **category** uses the
tokenized colored chip header (classes from the `LeagueCategory` enum). Time is
neutral; categories carry their color.

**Dark mode.** Filter UIs (and everything else) use daisyUI tokens only
(`base-*`, `primary`, `warning-content`…) — never raw palette classes
(`bg-blue-50`, `text-black`) which break the `dark` theme.

---

### Tables — `<x-table>` (Mary UI)

**Always use `<x-table>` for desktop.** Never use raw `<thead>/<tbody>`. Headers are defined as PHP arrays in the Livewire component:

```php
public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

public function headers(): array
{
    return [
        ['key' => 'name', 'label' => __('Name'), 'sortable' => true],
        ['key' => 'status', 'label' => __('Status'), 'sortable' => false],
        ['key' => 'created_at', 'label' => __('Date'), 'class' => 'hidden lg:table-cell'],
    ];
}
```

```blade
<x-table :headers="$headers" :rows="$items" :sort-by="$sortBy"
    selectable wire:model.live="selected">
    @scope('cell_status', $item)
        <x-badge :value="$item->status->getLabel()" class="badge-soft badge-primary" />
    @endscope
    @scope('actions', $item)
        <x-admin.shared.row-actions>
            <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil"
                :tooltip="__('Edit')" link="{{ route('admin.xxx.edit', $item) }}" />
            <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-trash"
                :tooltip="__('Delete')" wire:click="confirmDelete({{ $item->id }})" />
        </x-admin.shared.row-actions>
    @endscope
</x-table>
```

**Mobile fallback** — every index page with a table has a mobile card list above it:

```blade
{{-- Mobile: cards --}}
<div class="grid grid-cols-1 gap-4 lg:hidden">
    @forelse ($items as $item)
        <x-list-item :item="$item" class="bg-base-100 rounded-lg border">
            <x-slot:value>{{ $item->name }}</x-slot:value>
            <x-slot:sub-value>{{ $item->subtitle }}</x-slot:sub-value>
            <x-slot:actions>...</x-slot:actions>
        </x-list-item>
    @empty
        <x-empty-state ... />
    @endforelse
</div>

{{-- Desktop: table --}}
<div class="hidden lg:block">
    <x-card>
        @if ($items->isEmpty())
            <x-empty-state ... />
        @else
            <x-table ...>...</x-table>
            <div class="mt-4">{{ $items->links() }}</div>
        @endif
    </x-card>
</div>
```

---

### Empty States — `<x-empty-state>`

**Always use the component.** Never use ad-hoc `<p class="py-10 text-center...">`.

Props:
- `icon` — Heroicon name (default: `o-inbox`)
- `heading` — Short title (translated)
- `message` — Longer description (translated, optional)
- `buttonText` + `href` — CTA button linking to a URL
- Default `$slot` — For custom CTA (e.g. `wire:click` button)

```blade
{{-- Minimal (search results) --}}
<x-empty-state
    icon="o-magnifying-glass"
    :heading="__('No results')"
    :message="__('Try adjusting your search or filters.')" />

{{-- With link CTA --}}
<x-empty-state
    icon="o-user-plus"
    :heading="__('No users yet')"
    :message="__('Create the first user to get started.')"
    :buttonText="__('Create user')"
    href="{{ route('admin.users.create') }}" />

{{-- With wire:click CTA (slot) --}}
<x-empty-state
    icon="o-calendar"
    :heading="__('No seasons yet')"
    :message="__('Create your first season.')">
    <x-button class="btn-primary btn-sm" :label="__('Create first season')"
        wire:click="openCreate" />
</x-empty-state>
```

Choose the icon based on entity type:
| Entity | Icon |
|--------|------|
| Generic / no results | `o-inbox` |
| Users | `o-user-group` |
| Articles / posts | `o-document-text` |
| Events / calendar | `o-calendar` |
| Teams | `o-user-group` |
| Search results | `o-magnifying-glass` |
| Payments | `o-banknotes` |
| Rooms / tables | `o-home` |

---

### Create / Edit — Modal vs Page rule

| Scenario | Pattern |
|----------|---------|
| Form with **≤ 4 fields** | Modal Livewire (`<x-modal wire:model="createModal">`) |
| Form with **≥ 5 fields** or multi-step | Dedicated page (`link="{{ route('...create') }}"`) |
| Edit always follows same rule as create | — |

**Short form modal template:**

```blade
{{-- Trigger --}}
<x-button class="btn-primary" icon="o-plus" :label="__('Create')"
    wire:click="$set('createModal', true)" />

{{-- Modal --}}
<x-modal :title="__('New item')" wire:model="createModal">
    <div class="space-y-4">
        <x-input :label="__('Name')" wire:model="name" />
        <x-select :label="__('Category')" :options="$categories" wire:model="categoryId" />
    </div>
    <x-slot:actions>
        <x-button :label="__('Cancel')" wire:click="$set('createModal', false)" />
        <x-button class="btn-primary" :label="__('Create')" wire:click="create" spinner />
    </x-slot:actions>
</x-modal>
```

---

### Form Layouts

#### Layout A — Long form with section labels (≥ 5 fields, multiple sections)

```blade
<x-form wire:submit="save">
    <div class="grid grid-cols-6 gap-4 md:gap-6">

        {{-- Section label (left) --}}
        <div class="col-span-6 md:col-span-2">
            <x-header :title="__('Personal')" :subtitle="__('Personal information')" />
        </div>

        {{-- Fields (right) --}}
        <div class="col-span-6 md:col-span-4">
            <div class="grid gap-4 lg:grid-cols-2">
                <x-input :label="__('First Name')" wire:model="first_name" />
                <x-input :label="__('Last Name')" wire:model="last_name" />
                <x-input :label="__('Email')" wire:model="email" />
            </div>
        </div>

        <div class="col-span-6"><x-menu-separator /></div>

        {{-- Next section --}}
        <div class="col-span-6 md:col-span-2">
            <x-header :title="__('Security')" :subtitle="__('Secure your account')" />
        </div>
        <div class="col-span-6 md:col-span-4">
            <x-password :label="__('Password')" wire:model="password" />
        </div>

        <div class="col-span-6">
            <x-button type="submit" :label="__('Save')" class="btn-primary" spinner="save" />
        </div>
    </div>
</x-form>
```

#### Layout B — Short form with info sidebar (≤ 4 fields, contextual help)

```blade
<x-form wire:submit="save">
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">

        {{-- Info sidebar (left) --}}
        <div class="space-y-4">
            <x-admin.shared.info-bar :description="__('Contextual help text.')">
                <x-icon name="o-information-circle" class="h-5 w-5" />
            </x-admin.shared.info-bar>
        </div>

        {{-- Main fields (right, 2/3 width) --}}
        <div class="space-y-6 lg:col-span-2">
            <x-card :title="__('Details')" shadow>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-input :label="__('Name')" wire:model="name" />
                    <x-select :label="__('Room')" wire:model="room_id" :options="$rooms" />
                </div>
            </x-card>

            <x-button type="submit" :label="__('Save')" icon="o-check"
                class="btn-primary w-full" spinner="save" />
        </div>
    </div>
</x-form>
```

**Layout A** — users, articles, any entity with 4+ sections.
**Layout B** — tables, rooms, any entity where contextual help adds significant value.

---

### Modals & Drawers

| Use case | Component |
|----------|-----------|
| Destructive confirmation | `<x-confirm-modal>` |
| Form (creation / edit) | `<x-modal wire:model="...">` |
| Side detail panel | `<x-drawer right wire:model="...">` |

```blade
{{-- Confirmation --}}
<x-confirm-modal model="deleteModal" :title="__('Confirm deletion')" :subtitle="__('Warning!')"
    :confirmLabel="__('Delete')" confirmAction="delete">
    <p>{{ __('This action is irreversible.') }}</p>
</x-confirm-modal>

{{-- Side detail --}}
<x-drawer right wire:model="detailOpen" :title="__('Detail')" class="w-full max-w-md">
    @if ($selected)
        <div class="space-y-4 p-1">
            ...
        </div>
    @endif
</x-drawer>
```

---

### Pagination

Always use `{{ $items->links() }}` inside the table card, wrapped with `mt-4`:

```blade
<div class="mt-4">
    {{ $items->links() }}
</div>
```

Never use the custom `<x-pagination>` component in admin views (it is reserved for public pages).

---

### Row Actions — `<x-admin.shared.row-actions>`

Wrap all row action buttons in this component. It handles spacing consistently.

```blade
@scope('actions', $item)
    <x-admin.shared.row-actions>
        <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil"
            :tooltip="__('Edit')" link="{{ route('admin.xxx.edit', $item) }}" />
        <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-trash"
            :tooltip="__('Delete')" wire:click="confirmDelete({{ $item->id }})" />
    </x-admin.shared.row-actions>
@endscope
```

---

### Badges & Status Indicators

Use `<x-badge>` with daisyUI modifiers. Never use hand-crafted `<span class="rounded-full px-2...">`.

```blade
{{-- Soft (filled background, muted) --}}
<x-badge :value="$item->status->getLabel()" class="badge-soft badge-primary" />

{{-- Active / current --}}
<x-badge :value="__('Active')" class="badge-primary" />

{{-- Neutral / past --}}
<x-badge :value="__('Past')" class="badge-ghost" />

{{-- Info / upcoming --}}
<x-badge :value="__('Upcoming')" class="badge-info badge-soft" />
```

---

## Architecture Tests

`tests/Feature/ComponentsArchTest.php` enforces:
- No legacy button components in views (`x-primary-button`, `x-danger-button`, etc.)
- No legacy button component files on disk

Run: `php artisan test --compact --filter=ComponentsArchTest`
