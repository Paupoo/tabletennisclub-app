# Implementation playbook — adopting this design system in `tabletennisclub-app`

A phased, low-risk migration plan for the Laravel/Tailwind v4/daisyUI/maryUI codebase
(`Paupoo/tabletennisclub-app`). Each phase is independently shippable. Work top-down;
don't start a phase before the previous one is merged.

The design system is **derived from your existing `app.css`**, so this is consolidation +
naming, not a rewrite. Reference values live in `tokens/*.css`; component contracts live in
`components/**/*.prompt.md`.

---

## Phase 0 — Baseline (no visual change)
- [ ] Read `readme.md` (Visual foundations, Content fundamentals, Iconography).
- [ ] Confirm current brand vars in `resources/css/app.css` (`--color-club-blue`, `--color-club-yellow`, daisyUI `bumblebee` theme). These already match the system — do not change their values.
- [ ] Take before/after screenshots of: public home, news index, admin dashboard, users index, interclub teams. Use these to verify "no visual regression" after each phase.

## Phase 1 — Token layer (foundation)
Goal: one source of truth for color/type/space/radius/shadow, referenced everywhere.
- [ ] In `resources/css/app.css`, extend the `@theme` / `:root` block with the **semantic aliases** from `tokens/colors.css`: `--surface-card`, `--surface-page`, `--text-strong/body/muted/faint`, `--border-default/strong/hover`, plus the tag/level palettes.
- [ ] Add `tokens/spacing.css` values (radii `--radius-field/lg/xl/2xl`, `--shadow-sm/md/lg`, motion `--dur-*`, `--ease-out`) into the same layer.
- [ ] Keep daisyUI `bumblebee` exactly as-is (already aligned) — just make sure `--color-primary`/`--color-secondary` read from the brand vars.
- [ ] Acceptance: rebuild Vite; diff screenshots — pixel-identical.

## Phase 2 — Primitives (Blade ⇄ DS parity)
Goal: your Blade/maryUI components behave like the documented primitives. Don't replace maryUI; align it.
- [ ] **Button** — map `Button.prompt.md` variants to your `x-button` usages: primary (blue), secondary (yellow + blue text), outline, ghost, danger. Confirm hover lightens the fill and focus shows the blue ring.
- [ ] **Badge / Tag** — consolidate the ad-hoc pill classes in `news-card`, `event-card`, `schedule-card`, `team-card` to the `Badge` tone matrix (`components/core/Badge.prompt.md`). One palette, not five.
- [ ] **Card** — standardize the white/`border-gray-200`/hover-to-blue surface + the two accent patterns (top bar = event type; left border = schedule type).
- [ ] **Input** — align form fields to the focus-ring + error/hint states.
- [ ] Acceptance: each refactored component matches its card in the Design System tab.

## Phase 3 — Public site sections
Goal: marketing pages reference tokens, not hard-coded hex/utilities.
- [ ] Sweep `resources/views/components/public/*` and replace literal colors with token vars (`text-club-blue` stays; raw `#1e40af`/`gray-600` → semantic vars where custom CSS is used).
- [ ] Verify the signature hover (border gray-200 → club-blue + image scale 1.05) on `news-card`, `event-card`.
- [ ] Keep the hero's full-bleed photo + blue gradient wash; keep emoji accents (public only).
- [ ] Acceptance: `ui_kits/public-website/index.html` is the visual target.

## Phase 4 — Admin / back office
Goal: consistent back-office chrome.
- [ ] Align `clubAdmin/_dashboard_tile.blade.php` to `DashboardTile` (urgency-mapped icon chips, hover lift, badge).
- [ ] Standardize sidebar/topbar spacing & active states (`layouts/app.blade.php`, `components/admin/navigation.blade.php`) against `ui_kits/club-admin/index.html`.
- [ ] Confirm **no emoji** in admin; all icons are Heroicons `o-*` (maryUI). Retire legacy `public/images/icons/*` where still referenced.
- [ ] Tables (users index, registrations) → consistent header/zebra/badge treatment.

## Phase 5 — Iconography & assets cleanup
- [ ] Adopt Heroicons `o-*` everywhere; remove inconsistent standalone SVGs.
- [ ] Confirm Instrument Sans is the only UI face (Instrument Serif only if you want an editorial accent).

---

## Working agreement for the agent
- One phase per PR; never skip the screenshot acceptance check.
- Prefer token vars over literals; if a needed token is missing, add it to `tokens/` first.
- French copy, `vous`, sentence case (+ occasional Title Case CTAs), NBSP before `? ! :`.
- When unsure how a component should look/behave, open its `.prompt.md` and the matching card in this skill — those are the spec.
