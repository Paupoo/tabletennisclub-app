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

## Architecture Tests

`tests/Feature/ComponentsArchTest.php` enforces:
- No legacy button components in views (`x-primary-button`, `x-danger-button`, etc.)
- No legacy button component files on disk

Run: `php artisan test --compact --filter=ComponentsArchTest`
