# Changelog — User Management Improvement

**Branch:** user-management-improvement  
**Date:** 2026-06-04  
**Tests:** 486 passing (241 in user management domain)

---

## ✨ New Features

### Guardian / Emergency Contact System

Minors (under 18) can now have a legal guardian or emergency contact linked to their profile. This ensures the club always has a responsible contact for young players.

- Secretary can search existing members as guardians, or create a new guardian inline (first name, last name, phone, email, optional IBAN for family discounts)
- One guardian can cover multiple siblings from the same family
- **Family discount** is automatically derivable when a guardian has 2+ affiliated members
- A warning appears when saving a minor without a guardian attached
- **Affiliation is blocked** for minors without a guardian — by design, for safety

### Invite-Only Onboarding

Public self-registration has been removed. All new members are onboarded by invitation:

- **Contact form → Onboard**: when a visitor submits a "Join us" or "Have a try" form, the secretary opens the contact and clicks **Onboard as user**. The create-user form opens pre-filled with the visitor's name, email, and phone. One click sends the invitation — no double encoding.
- Contact status is automatically set to **Processed** after onboarding.
- The secretary handles verification, not the visitor.

### Invitation Status Badges

The users list now shows the invitation status for each member at a glance:

- 🟢 **Active** — member has accepted invitation and logged in
- 🟡 **Pending** — invitation sent, link still valid (within 48h)
- 🔴 **Expired** — link expired, needs resending
- ⚪ **Not invited** — no invitation sent yet

### Resend Invitation (from list)

A **Resend invitation** button is now available directly on each user row — no need to open the edit form. The 48-hour clock resets on resend.

### Welcome Email on First Login

Members now receive a **welcome email** when they accept their invitation and set their password for the first time. The email includes a link back to their member space.

### Soft Delete (Archive) + Restore

Deleting a member no longer permanently removes them:

- **Archive**: admin soft-deletes the user — they disappear from the active list but data is preserved
- **Archived tab**: toggle the Archived view to see all archived members
- **Restore**: one click restores an archived member to active status

### GDPR Anonymization (Admin Only)

For members who request full data erasure:

- Admin clicks **Anonymize (GDPR)** on the user profile
- Requires typing **ANONYMIZE** to confirm — irreversible
- All PII is nulled (name, email, phone, address, IBAN, documents)
- Email replaced with `deleted-{id}@anonymous.local`
- Tournament history and payment records are preserved for accounting (no personal data attached)

### Contact → Onboard Action Pattern

A complete `OnboardFromContactAction` backs the onboarding flow, ensuring the process is testable and auditable.

---

## 🔒 Security & Permissions

### Admin vs Committee — Clear Permission Split

Committee members and administrators now have distinct permissions. Previously they shared identical access.

**Committee members can now:**
- Create and edit members
- Toggle active/inactive
- Promote/demote other committee members
- Send invitations
- Manage tournament, interclub, meeting, training, and website content

**Administrators only:**
- Archive (soft delete) members
- Restore archived members
- Promote/demote admin flag
- Anonymize members (GDPR erasure)
- Bulk archive

### Guardian Policy

A dedicated `GuardianPolicy` controls who can view, create, edit, and delete guardian records (admin and committee members only).

---

## 🔧 Improvements

### User Profile — All Fields Now Editable

Members can now update all their personal fields from their profile page:

- Previously: only email, address, phone (limited set)
- Now: first name, last name, gender, date of birth, photo, email, phone, address, city, IBAN

Email uniqueness is validated. Photo upload and deletion work from the profile page.

### Payment Status — Badge Instead of Toggle

The manual **Mark as paid / Mark as unpaid** toggle has been removed from the users list. Payment status now shows as a **badge** that links directly to the member's affiliation and payment details. Payment status is updated automatically by the payment system — no more manual flags.

### Training List — From Database

The training list in the user creation form was previously hardcoded. It now loads from the database, reflecting the actual active training packs configured for the current season.

### Photo Upload — Shared Trait

Photo upload logic (`handlePhotoUpload`, `deletePhoto`) has been extracted into a reusable `HasPhotoUpload` trait, eliminating code duplication between the admin form and the user profile page.

### Audit Trail (Partial)

Two new fields on every user record:

- `updated_by`: tracks which admin or committee member last edited the record
- `last_invited_at`: tracks when the invitation was last sent

These are the foundation for a full audit log (planned for a future release using `spatie/laravel-activitylog`).

---

## 🐛 Bug Fixes

- **Wrong exception import**: `form.php` imported `PHPUnit\Event\Code\Throwable` instead of the built-in `\Throwable`, silently swallowing real exceptions in the user form
- **Profile save() incomplete**: The user self-service profile page had a `// logique normale...` comment placeholder — save was not actually updating the database
- **No authorization check on profile**: Any authenticated user could theoretically edit another user's profile — now enforced
- **Duplicate photo upload code**: Removed duplication between admin form and user profile (see trait above)
- **Welcome email commented out**: `SendWelcomeEmail` listener was accidentally commented out — restored and wired to the invitation-acceptance flow
- **Dead code removed**: `ProfileUpdateRequest` form request class was unused (replaced by Livewire inline validation) — deleted

---

## 📋 Architecture

### Action + DTO Pattern

All user operations now go through dedicated action classes with typed DTOs:

| Action | What it does |
|---|---|
| `CreateUserAction` | Creates a user from `CreateUserData` DTO, sends invitation |
| `SoftDeleteUserAction` | Archives a user (soft delete) |
| `RestoreUserAction` | Restores an archived user |
| `AnonymizeUserAction` | GDPR erasure — nulls all PII |
| `ToggleActiveAction` | Activates or deactivates a user, tracks `updated_by` |
| `SendInvitationAction` | Sends invitation email, updates `last_invited_at` |
| `OnboardFromContactAction` | Creates user from Contact form data |

Actions are independently testable and set the stage for a full audit trail.

### Database Changes

Three new migrations:

1. **`users` table**: `deleted_at` (soft delete), `updated_by` (FK to users), `last_invited_at` (timestamp)
2. **`guardians` table**: new table for lightweight guardian records (not full user accounts)
3. **`guardian_user` pivot**: replaces old `user_guardian` (which incorrectly linked User→User); now correctly links User→Guardian

---

## 📖 Documentation

- **[Member Manual](manual-member.md)**: exhaustive guide covering login, profile, subscriptions, payments, tournaments, interclubs, and more
- **[Committee Manual](manual-committee.md)**: exhaustive guide covering all member features plus user management, onboarding, tournament/interclub/meeting management, treasury, and website content

---

## ⚠️ Breaking Changes

- **`/register` route removed**: public self-registration is no longer available. Members are onboarded by invitation only.
- **`user_guardian` pivot replaced**: any existing User→User guardian links are dropped. Guardian relationships must be re-entered using the new Guardian model.
- **`ProfileUpdateRequest` deleted**: not used anywhere — safe to remove.
- **Committee members can no longer delete users**: delete is now admin-only. Existing committee workflows that relied on delete should use archive + restore instead.
