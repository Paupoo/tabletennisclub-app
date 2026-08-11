# CTT Ottignies-Blocry — Design System

A design system for **CTT Ottignies-Blocry**, a table tennis club (*tennis de table*) based at the Centre sportif de Blocry in Ottignies-Louvain-la-Neuve, Belgium. The club runs two connected products from one codebase:

1. **Public website** — French-language marketing site: hero, club presentation, news/blog, interclub results, training schedules, events, contact form, sponsors.
2. **Club back office (admin)** — a members-and-competition management app: member directory, affiliations & payments, treasury, interclub teams & selections, tournaments, trainings, and a "Website" admin (articles, contacts, spam).

This design system distills the brand's real visual language — two club colors over a clean neutral canvas, Instrument Sans type, soft low-depth surfaces — into tokens, components, foundation cards and full-screen UI-kit recreations.

---

## Sources

Everything here was reverse-engineered from the club's open-source application. Explore it for deeper fidelity:

- **GitHub:** `Paupoo/tabletennisclub-app` — https://github.com/Paupoo/tabletennisclub-app
  - Stack: **Laravel** (PHP) · **Blade** templates · **TailwindCSS v4** · **daisyUI** (theme `bumblebee`) · **maryUI** (daisyUI Blade components, Heroicons) · **Alpine.js** · **Livewire** · MariaDB.
  - Brand tokens live in `resources/css/app.css` (`@theme` + daisyUI `@plugin "daisyui/theme"`).
  - Public site: `resources/views/components/public/*` and `resources/views/public/*`.
  - Admin: `resources/views/layouts/app.blade.php`, `resources/views/components/admin/*`, `resources/views/clubAdmin/*`.
  - Logo + imagery: `public/images/`.

> The repo is MIT-licensed and authored by Aurélien Paulus. If you have access, read the Blade components directly — they are the source of truth for exact spacing, copy and component states.

---

## Content fundamentals

**Language.** Primary language is **French (Belgian)**. The codebase is mid-migration to i18n, so some UI strings are English keys, but all user-facing copy is French. Write French first.

**Voice.** Warm, welcoming, community-first — never corporate. The club sells *belonging* as much as sport: *"Nous sommes plus qu'un simple club – nous sommes une communauté dédiée au sport que nous aimons."*

**Address.** Speaks to the reader as **vous** (polite/plural). Inclusive and encouraging: *"Des débutants aux champions, tout le monde est le bienvenu."*

**Casing.** Sentence case for body and most headings; **Title Case occasionally appears on CTAs and headings** (*"Rejoindre le Club", "En Savoir Plus", "Nos Sponsors"*). Eyebrows/labels are UPPERCASE with wide tracking (*"PROCHAINS ÉVÉNEMENTS"*). Respect French typographic spaces — a non-breaking space before `?`, `!`, `:` (`Pourquoi nous rejoindre&nbsp;?`).

**Tone examples.**
- Hero CTA: *"Rejoindre le Club"* / *"En Savoir Plus"*
- Section lead: *"Notez ces dates — ces rendez-vous du club valent le déplacement."*
- Contact: *"Des questions ? Envie de nous rendre visite ? Nous serions ravis de vous entendre !"*
- Footer: *"Votre destination de choix pour le tennis de table à Ottignies et environs."*

**Emoji.** Yes — used **deliberately and sparingly on the public site** as friendly accents: 🏓 (brand), 🏆 👥 🎯 (feature pills), 📅 ⏰ 📍 🎟️ (event metadata), 🍻 (social). The admin/back office does **not** use emoji — it uses line icons. Don't overuse: one emoji per feature/metadata item, never strings of them.

**Numbers & data.** Show real, useful figures (member counts, classements like `C2`/`D4`, scores `10-6`, prices `5 €`). Belgian conventions: `€ 4 280` / `5 €`, dates as `samedi 21 juin 2026`, times as `18h00 – 20h00`. Avoid invented vanity stats.

---

## Visual foundations

**Identity in one line:** two brand colors — **club blue** and **club yellow** — over a clean white/neutral-gray canvas, set in **Instrument Sans**, with **soft, low-depth** surfaces where **hairline borders** (not heavy shadows) do most of the separation.

**Color.**
- **Club Blue `#1e40af`** (Tailwind blue-800) is the primary — headings accents, primary buttons, links, icon chips, the logo. Hover lightens to **blue-light `#3b82f6`**.
- **Club Yellow `#fbbf24`** (amber-400) is the secondary — the "Rejoindre"/login CTAs (yellow fill + **blue** text), feature accents, the cookie bar. Hover lightens to **yellow-light `#fcd34d`**.
- Neutral canvas is the Tailwind gray scale; app background is daisyUI `base-200` (~`#f5f5f5`), surfaces are white, dividers are `base-300`/gray-200.
- Status from the daisyUI **bumblebee** theme: info `#3b82f6`, success emerald, warning amber, error red. Dark surfaces (footer, sponsor tiles) use gray-900.
- Categories/levels get tinted pills (see `colors-tags` card): blue=Compétition/Tournoi, yellow=Formation, soft blue/green/orange/red/purple for player levels.

**Type.** **Instrument Sans** everywhere (the app's `--font-sans`); **Instrument Serif** is an optional editorial accent only. Bold (700) display & headings with tight tracking; 400 body at relaxed line-height (~1.6); UPPERCASE 700 eyebrows with `.15em` tracking. Hero is `clamp(40px → 72px)`; section H2 ≈ 36px; card titles 20px/600; body 16px; meta 12–14px.

**Spacing & layout.** 4px base scale. Content lives in a **max-w 1180–1280** centered container with 24px gutters. Sections breathe with **~80–88px vertical padding** (`py-20`). Cards pad **24px**. Grids use `gap` (24–32px), three-up for features/news/events.

**Backgrounds.** Marketing pages alternate **white ↔ gray-50** sections for rhythm. The hero (and section headers like news/events/results) use **full-bleed action photography** under a diagonal **blue→blue-light gradient wash at ~80–85% opacity** — warm, energetic sports imagery, never cold or desaturated. The admin is flat `base-200`, no photography.

**Corners.** Buttons/inputs are barely-rounded (`radius-field` 4px). Cards step up: news `rounded-lg` (8px), admin tiles & team cards `rounded-xl` (12px), feature/contact cards `rounded-2xl` (16px). Pills, avatars and icon circles are fully round.

**Borders, shadows, elevation.** The theme runs `--depth:0` → **flat by default**. A **1px gray-200 border** defines almost every card; shadows are soft and small. Resting cards are flat or `shadow-sm`; on hover they lift to `shadow-md`. **Signature hover:** a card's **border brightens from gray-200 → club-blue** (public site), often with the inner image scaling to 1.05.

**Accents on cards.** Two patterns: a **4px colored top bar** (featured events — color encodes type) and a **4px colored left border** (schedule rows — color encodes session type: blue=dirigé, amber=supervisé, red=interclubs, gray=libre).

**Buttons.** Primary = solid club-blue / white text. Secondary = solid club-yellow / **club-blue** text. Outline = transparent + gray border → blue border on hover. Hover lightens the fill; the hero CTAs also nudge `scale(1.05)`. Focus shows a **soft blue ring** (`0 0 0 3px rgba(30,64,175,.10)`), matching the app's `input:focus`.

**Motion.** Restrained and friendly. `fadeInUp` (0.6s ease-out) reveals sections on scroll; transitions are 150–300ms ease-out on color/border/shadow/transform. No bounces, no infinite loops, no parallax. Respect `prefers-reduced-motion`.

**Transparency & blur.** The sticky nav is `white/95` + `backdrop-blur`; the cookie bar is `yellow-light/95` + blur; modal scrims are `black/55` + light blur. Used sparingly for chrome, not decoration.

---

## Iconography

- **Primary system — Heroicons (outline).** The admin/back office is built on **maryUI**, which renders **Heroicons** via `o-*` (outline, ~1.5–2px stroke) and `s-*` (solid) names — e.g. `o-users`, `o-trophy`, `o-calendar-days`, `o-banknotes`, `o-bell`, `o-pencil`, `o-eye`, `o-trash`, `o-cog-6-tooth`. **Use Heroicons outline** for all UI chrome, navigation and actions. This system ships a ready helper: **`assets/heroicons.jsx`** exposes `window.Icon({ name, size, stroke })` with ~30 common outline glyphs — load it in UI kits. For production, use the real Heroicons package or maryUI `o-*` names.
- **Public marketing site — emoji accents.** As noted above, the public pages use a small, intentional emoji set (🏓 🏆 👥 🎯 📅 ⏰ 📍 🎟️) plus inline outline SVGs for contact metadata. Keep emoji to the public site only.
- **Legacy/decorative SVGs.** `assets/icons/*` holds a handful of older standalone icons imported from the repo (medal, ranking, win/loss, address, calendar…). They are **inconsistent** (mixed fills/viewBoxes) — kept for reference/parity, but prefer Heroicons for anything new.
- **Logo.** `assets/logo-club.svg` — a single-color circular table-tennis-paddle mark (club blue). Recolor to white via `filter: brightness(0) invert(1)` on dark/blue backgrounds. Pair with the wordmark "CTT Ottignies-Blocry".

---

## What's in here (index)

**Root**
- `styles.css` — the single entry point; `@import`s every token + font file. Link this.
- `readme.md` — this guide.
- `SKILL.md` — Agent-Skill manifest (for use in Claude Code).

**`tokens/`** — `fonts.css` (Instrument Sans/Serif via Google Fonts CDN), `colors.css`, `typography.css`, `spacing.css` (spacing, radii, shadows, motion).

**`guidelines/`** — foundation specimen cards shown in the Design System tab: brand/neutral/status/tag colors, type scale & weights, spacing scale, radii & shadows, logo, imagery, iconography.

**`components/`** — reusable React primitives (load `_ds_bundle.js`, read from `window.CTTOttigniesBlocryDesignSystem_a28edf`):
- `core/` — **Button**, **Badge**, **Card**, **Input**, **Avatar**
- `public/` — **NewsCard** (composes Badge)
- `admin/` — **DashboardTile**

**`ui_kits/`** — full-screen interactive recreations:
- `public-website/` — the French marketing homepage (nav, hero, about, featured events, news, schedule, contact form, footer + login modal).
- `club-admin/` — the back-office shell (sidebar + topbar) with Dashboard, Members table and Interclub Teams screens.

**`assets/`** — `logo-club.svg`, `heroicons.jsx`, `images/` (hero/news/events/results photography), `icons/` (legacy SVGs).

---

## Using it

Link the tokens, load the bundle, compose:

```html
<link rel="stylesheet" href="styles.css">
<script src="_ds_bundle.js"></script>
<script>
  const { Button, Badge, NewsCard, Card, DashboardTile } = window.CTTOttigniesBlocryDesignSystem_a28edf;
</script>
```

Reach for CSS variables (`var(--club-blue)`, `var(--surface-card)`, `var(--text-body)`, `var(--radius-lg)`) rather than hard-coded values, so designs stay on-brand.
