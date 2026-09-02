---
name: ctt-ottignies-design
description: Use this skill to generate well-branded interfaces and assets for CTT Ottignies-Blocry (a Belgian table tennis club — public website + admin back office), either for production or throwaway prototypes/mocks. Contains essential design guidelines, colors, type, fonts, assets, and UI-kit components for prototyping.
user-invocable: true
---

Read the `readme.md` file within this skill, and explore the other available files (tokens, components, ui_kits, assets).

If creating visual artifacts (slides, mocks, throwaway prototypes, etc), copy assets out and create static HTML files for the user to view. If working on production code, copy assets and read the rules here to become an expert in designing with this brand.

Key facts: two brand colors — **club blue `#1e40af`** + **club yellow `#fbbf24`** — over a clean white/neutral-gray canvas; type is **Instrument Sans**; surfaces are flat with **1px gray borders** (hover brightens the border to blue); public copy is **French (Belgian), warm and community-first**, with sparing emoji; the admin uses **Heroicons (outline)** and no emoji. See `readme.md` → Content fundamentals, Visual foundations, Iconography for the full rules.

If the user invokes this skill without other guidance, ask them what they want to build or design, ask a few clarifying questions, and act as an expert designer who outputs HTML artifacts _or_ production code, depending on the need.

**Migrating the design system into the Laravel app?** Follow `IMPLEMENTATION.md` — a phased, screenshot-verified playbook mapped to the real `tabletennisclub-app` paths (`resources/css/app.css`, `resources/views/components/**`, `clubAdmin/**`). Work one phase per PR, top-down. Component contracts are in each `components/**/*.prompt.md`.
