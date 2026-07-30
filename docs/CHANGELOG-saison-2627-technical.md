# Changelog — Season 2026-2027

**Branch:** `go_saison_2627` → `main`
**Range:** `39293cc..1c798db` — 270 commits, 2026-05-26 → 2026-07-30
**Diff:** 986 files changed, +74 099 / −25 274
**Migrations:** 47 new (several are data backfills — see [Deployment](#deployment))
**Tests:** 140 new test files; suite now holds 284 files / ~2 713 test declarations

> ⚠️ The federation-import work is **not fully committed**: `AddressNormalizer` plus edits across the import action, parser, `User`, and the import view are still in the working tree.

---

## Dependencies added

| Package | Version | Why |
|---|---|---|
| `spatie/laravel-permission` | `^8.3` | Roles and delegations, replacing boolean flags on `users` |
| `spatie/laravel-activitylog` | `^5.0` | Admin activity log across 42 models |
| `robsontenorio/mary` | `^2.7` | Admin UI kit (already in use, now an explicit dependency) |

`guzzlehttp/guzzle` bumped to 7.15.1 and `psr7` to 2.13.0 for security patches.

---

## ⚠️ Breaking changes

### Authorization moved to Spatie roles + delegations

Boolean role flags on `users` were dropped and replaced by a role/permission model. Anything reading those columns breaks.

- `2026_07_21_015130_create_permission_tables`
- `2026_07_21_015409_backfill_roles_from_user_flags` — backfills roles from the old flags
- `2026_07_21_234338_drop_role_flags_from_users`

Delegations (members, treasury, interclubs, trainings, bar, website, meetings) now decide access — not the committee title. Menu, routes and screens were resynchronised against the matrix, and back-office pages are gated at the **route** level. A `RoleSeeder` run is required on every deploy.

### Derived member state

`is_active`, `is_competitor` and `has_paid` were dropped from `users` and are derived from the active-season subscription:

- `2026_06_13_004442_drop_is_active_from_users_table`
- `2026_06_13_021300_drop_is_competitor_from_users_table`
- `2026_06_13_025720_drop_has_paid_from_users_table`

Active = subscription in `confirmed|paid`. The rule was then applied across the rest of the app (headcounts, pickers, filters).

### `users.email` is now nullable

Login identity was separated from the contact address, so managed accounts (minors contacted through a guardian) can exist without their own address.

- `2026_07_28_151048_make_users_email_nullable`

Any code assuming a non-null `email` — uniqueness checks, mailables, `whereEmail` lookups — needs auditing.

### Family links moved to a dedicated model

Family columns were dropped from `users` in favour of `family_groups` + `family_group_user`:

- `2026_07_05_211012_create_family_groups_table`
- `2026_07_05_211013_create_family_group_user_table`
- `2026_07_05_211014_drop_family_columns_from_users_table`

Family linking is an admin operation now, not self-service.

### Publication status is the single source of truth

`is_public` dropped from both `meetings` and `news_posts`; visibility derives from `status`.

- `2026_06_06_020603_drop_is_public_from_meetings_table`
- `2026_07_22_172859_drop_is_public_from_news_posts_table`

### Table state collapsed onto one enum

Three competing state vocabularies replaced by `TableStateEnum`; denormalised counters dropped.

- `2026_07_20_021430_normalize_tables_state_to_enum`
- `2026_07_20_025536_drop_is_available_from_tables`
- `2026_07_20_031734_drop_total_tables_from_rooms`

### Other schema-level breaks

- `seasons.registrations_open` → **`affiliations_open`** (`2026_07_26_214700`)
- Training packs now **require** `start`/`end` dates (`2026_07_26_124350_require_pack_dates_on_training_packs`)
- Member documents moved to the **private disk** (`2026_07_03_203526_migrate_member_documents_to_private_disk`) — public URLs to member documents no longer resolve
- `APP_CLUB_LICENCE` env var replaced by an `is_own_club` column on `clubs` (`2026_06_08_011057`); the setup wizard was overhauled around it
- Contact statuses collapsed to `new` / `processed` / `rejected` (`2026_07_25_134622_backfill_contact_pending_status_to_new`)
- `Ranking` enum is now the source for rankings; the `OTHER` gender case was removed

### Deleted code

Removed as dead or superseded: `SyncTrainingPackAction`, `CreateNewUserAction`, `InviteExistingUserAction`, `ToggleActiveAction`, `ToggleHasPaidMembershipAction`, `SubscriptionController`, `TransactionController`, `ArticleValidator`, and the `StoreUserRequest` / `UpdateUserRequest` / `StoreArticleRequest` / `UpdateContactRequest` form requests (validation lives in Livewire components). Obsolete subscription HTTP routes were dropped.

### Test suite is split

The browser suite **cannot** run under `--parallel`. `composer test` now runs `Unit,Feature,Architecture` in parallel, then `Browser` sequentially. A guard fails the browser suite outright if `--parallel` is passed.

---

## ✨ Features

### Roles, delegations and feature flags

Foundation laid in `feat(permissions): poser le socle des rôles et délégations`, then rolled out domain by domain (treasury, members, interclubs, competitions, communication). Delegations are assignable and viewable from a dedicated screen; captain relationship is now distinct from the interclubs delegation, and selection is restricted to a captain's own teams. Domains can be switched off per environment via feature flags. Invariants are locked down by `test(permissions)`.

### Member space (`Mon espace`)

Delivered in four increments plus follow-ups:

- Shell with identity strip, persistent tabs, sequenced affiliation flow
- Member payments hub; chosen licence surfaced on a submitted registration
- Member directory with opt-in contact visibility (phone/email/address) and a captain contact override
- ICS feed, roster availability overview, notification preferences (`2026_07_13_065642`, `2026_07_13_202837`)
- AFTTB rules & regulations page
- Prototype data purged; profile rebuilt (flat cards, responsive grid, team chips, danger zone moved to account settings)
- Login now redirects to the admin dashboard; Notifications moved into the member space

### Federation affiliate import

Pipeline: read the listing → match against the club roster → **review before it touches the roster** → import → bulk-invite → surface members who cannot be handed a login.

- `2026_07_28_151048_create_member_imports_table`
- `2026_07_28_151048_add_federation_columns_to_users_table`

Members can also be filtered by account standing. **Work in progress** — see the warning at the top.

### Onboarding and invitations

Onboarding wizard added, back office blocked until the profile is complete. Invitation validity extended to 7 days via a shared constant, with an expired-link page offering self-service resend; acceptance requires a **signed URL** and is throttled; password validation aligned on `Password::defaults()` and the global policy hardened. Structured legal guardian required for minors, with the link persisted on wizard completion and a legible, validated guardian step. Onboarding a known email no longer creates a duplicate account.

### Training packs and season planning

- Season training planning board (responsive grid, compact cards, pool filter, directed-training opt-in, layout optimizer)
- Season involvement attributes + season roster (`2026_06_15_070929`), `training_plans` / `training_plan_packs` / `training_plan_assignments`
- Pro-rata billing for months actually held (`2026_07_25_144523_add_prorata_and_override_to_subscription_training_pack`)
- Enrolment cap from the pack wizard; waitlist offer expiry communicated; withdraw and stop separated; sessions rebuilt on slot change
- Refund fixes (literal delta, cents preserved); packs voided when an affiliation is cancelled

### Meetings

Status-driven hub replacing show tabs; quick create and in-place card editing replacing the wizard; dedicated minutes page with autosave; live note-taking behind a soft lock (claimed on first edit, not on open); poll send tracking with leading-date highlight (`2026_07_12_181026`, `2026_07_12_132742`); web-publish form prefilled from meeting details; archiving and deletion (`2026_06_18_004058`).

### Treasury, payments and fines

- Robust bank statement import with deduplication and audit log (`2026_06_07_175654_create_bank_imports_and_update_transactions`), ISO-8859-1 handling
- Bulk actions and filter drawer on payments and transactions, plus event type/name filters
- `fines` table (`2026_07_13_215806`): federation fines passed on to members; treasurer can cancel a fine issued by mistake
- Subscription cancellation with optional refund
- IBAN normalised and formatted across users, guardians and clubs (`2026_07_05_225343_backfill_normalized_iban`); BIC/IBAN no longer hard-coded (`2026_06_07_230413`)
- Structured communication check digits zero-padded
- Tournaments: real payment deadline stated, member notified when an unpaid entry is cancelled

### Audit log

`spatie/laravel-activitylog` with a shared `HasAuditLog` trait on **42 models**, logging created/updated/deleted with before→after diffs on fillable fields only (`password`, `remember_token` excluded globally). Livewire page at `/admin/club-admin/audit`, filterable by item type, author, action and date range, with a mobile card layout. Bulk deletes routed through model events so they are captured. Gated on `view-audit-log`.

### Interclubs

Category force lists — general/women/veterans (`2026_07_25_005131`); non-competitive members excluded from every player picker; substitute search explains empty results; lineup updates notify only added/removed players; force list shown beside selected players in the selection mail; single own club enforced in the app layer; captain-selection filter drawer with double-booking detection and substitutes.

### Bar

Cart validation and session management, `StockService`-backed stock handling, cash sheet layout and email sending, payment methods, order creator display, `bar_settings` (`2026_06_02_181809`), auth middleware on the controllers. Club key tracking and cash register holder assignment (`2026_06_12_205755` ×2).

### Public site

Public events and results pages with a filter bar; article `reading_time` and meta description (`2026_06_07_101252` + backfill); homepage interclub schedule configurable via `AppSetting` with a season priority chain; unpublished posts no longer served; SVG favicon; empty states; footer guarded against a missing own club and fed from the database.

### Rooms and tables

Tables list merged into a room detail page; unassigned tables moved out of the room grid; blank purchase date stored as null instead of a 500.

### Ops and tooling

Queue monitoring with stalled-worker alerting; `docs:erd` artisan command generating deterministic Mermaid ERDs; migrations made portable across SQLite and MariaDB; in-memory SQLite forced for tests; 100 % type coverage enforced across all PHP files; i18n coverage tests including parameterized `__()` calls.

---

## 🔧 Improvements

- **Filter drawer promoted to the standard pattern** across every admin list (users, captain-selection, control-center, trainings, interclubs schedule/teams/results/division-setup, registrations, treasury), with shared `filters-button` and `mobile-header-actions` components and active-filter chips
- Page-specific actions grouped into a "More actions" dropdown; header Create buttons standardised on `btn-sm`
- Mobile: minimal headers + bottom action sheet on all 11 index pages; notification bell bottom-sheet; avatar upload with client-side crop
- Calendar: month list replaced by an adaptive grid, instant day selection, deep links, a11y polish
- Design system: semantic token layer, unified status badges, tokenized categories, DS type scale, legacy icons removed, dark-mode token fixes
- Role-based admin dashboard with real data and personal alerts; less invasive alerts, cleaner recent activity, upcoming interclubs/trainings/tournaments/meetings
- Complete `nl_BE` translation file, 101 missing keys translated, Dutch backfill after the avatar/agenda work, French validation messages, affiliation vocabulary unified across languages
- In-app help center at `/admin/aide`; captain, selector and equipment manuals (FR); training packs and sessions documented
- Session flashes bridged to Mary toasts in the admin layout
- Tailwind `@source` scans PHP class files so dynamically injected classes survive

---

## 🔒 Security

- Back-office pages gated at the route level
- Member documents moved to the private disk
- My-space pages restricted to their owner (self-only)
- Invitation acceptance requires a signed URL and is throttled
- Global password policy hardened
- Email uniqueness validated on the admin form instead of returning a 500 (JPVO-1)
- Unpublished club posts no longer served on the public site
- GDPR: erasure requests notify admins and the secretary, and flag pending payments
- Dependency security patches (guzzle, psr7)

---

## 🐛 Notable fixes

- Archived members no longer crash the planning board
- Licence type toggle locked when the member has no active-season subscription
- Headcounts count only active members
- Users index: name column sorted by first then last name; all filter state persisted in URL
- Circular computed broken between meetings and registrations
- Cash register balance cast fixed (`string` returned where `int` was declared)
- `CashRegister::currentBalance()` and array-cast attributes render correctly in the activity log
- Seeders assign Spatie roles instead of the dropped columns; stale `season.current` cache cleared before seeding
- Header title/subtitle passed as PHP expressions to `x-header` to prevent double HTML-escaping
- Inline `@php()` Blade trap disarmed; admin tab components no longer trip the architecture rules
- Mail header logo rendered as a bounded PNG instead of an unsized SVG
- Off-season and non-past events shown with working subscription buttons on the public pages
- `bg-base-400` (invalid) replaced with `bg-base-300`; warning alert contrast improved in light mode
- "Admin Pannel" breadcrumb typo corrected and given a real French label
- Dead reset buttons removed from the profile; stray "give fine" action removed from mobile

---

## Deployment

Follow the standing procedure in **[DEPLOYMENT.md](./DEPLOYMENT.md)**. What is specific to *this* release:

- **47 migrations**, four of which are **data backfills** that must run in order: roles from user flags → normalised IBAN → contact `pending` → `new` → member documents moved from the `public` disk to `local`. Take a database **and** `storage/app` dump first — the document migration moves files on disk, and no rollback restores them.
- **`RoleSeeder` is mandatory on this deploy specifically.** It is the release that introduces `spatie/laravel-permission`; skipping it leaves the permission tables empty and locks every user out of the back office.
- Verify `storage/app` is writable **before** migrating, or the document migration fails partway through.
- **New scheduled command:** `queue:check-health` (hourly). If the crontab entry for `schedule:run` was never installed, none of the six scheduled tasks have ever run — check this now.
- `composer install` pulls three new packages; `npm run build` is required for the design-system tokens and the Tailwind `@source` change.

## Follow-up

- Finish and commit the federation-import work still in the working tree.
- `CaptainSelectionTest` remains flaky under `--parallel`.
