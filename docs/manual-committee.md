# Committee Member User Manual

This manual covers everything a committee member (and administrator) can do in the app, in addition to what regular members can do. For member-level features (profile, calendar, subscriptions, tournaments), refer to the [Member Manual](manual-member.md).

---

## Permission Summary

| Action | Committee | Admin |
|---|---|---|
| Create users | ✅ | ✅ |
| Edit users | ✅ | ✅ |
| Toggle active/inactive | ✅ | ✅ |
| Promote/demote committee member | ✅ | ✅ |
| Delete (archive) users | ❌ | ✅ |
| Restore archived users | ❌ | ✅ |
| Promote/demote admin | ❌ | ✅ |
| Anonymize user (GDPR) | ❌ | ✅ |
| Manage tournaments | ✅ | ✅ |
| Manage interclubs | ✅ | ✅ |
| Manage meetings | ✅ | ✅ |
| Manage training packs | ✅ | ✅ |
| Manage website content | ✅ | ✅ |
| Manage treasury | ✅ | ✅ |

> **Managing group**: some recent features — managing contacts (qualify, reply, onboard) and email templates, **editing** the season roster, **composing/importing** in the planning board — are restricted to administrators and committee members with the **secretary, president or vice-president** role. Other committee members keep **read** access (view and export).

---

## 1. User Management

### Accessing the user list

Go to **Club admin → Members**. The list shows all active members by default.

### User list features

- **Search**: type a name or email to filter in real time
- **Filters**: filter by licence type (competitive/recreational), gender, active status, team
- **Invitation badge**: each row shows the invitation status:
  - 🟢 **Active** — user has set their password and logged in
  - 🟡 **Pending** — invitation sent, not yet accepted (within 48h)
  - 🔴 **Expired** — invitation link expired, needs resending
  - ⚪ **Not invited** — no invitation sent yet
- **Payment badge**: shows paid/unpaid status for the current season; click to view their affiliation/payment details
- **Sorting**: click column headers to sort

### Creating a new user (inviting)

Click **New user** (top right). Fill in the grouped form:

- **Identity**: first name, last name, gender, date of birth, photo
- **Contact**: email (required), phone, address
- **Club status**: active toggle, competitive/recreational, licence number, ranking
- **Roles**: committee member flag, committee role, coach flag, admin flag (admin only)
- **Documents**: medical certificate, parental consent
- **Guardian/Dependents**: search and attach a guardian if the user is a minor (under 18)
- **Security**: optional — you can trigger a password reset or resend invitation here

Click **Save**. The user is created and an invitation email is sent automatically to their email address.

### Editing a user

Click the **Edit** button (pencil icon) on the user row or from their profile. Same grouped form as creation. Changes save immediately.

### Resending an invitation

On the users list, click **Resend invitation** (envelope icon) on any row with status **Pending** or **Expired**. A new 48-hour signed link is sent. The `last_invited_at` timestamp updates, resetting the expiry clock.

### Toggling active/inactive

On the users list or edit form, toggle the **Active** switch. Inactive users cannot log in. They still appear in the list (use the active filter to hide them).

### Archiving a user (admin only)

Click the **Delete** button on a user row. A confirmation bar appears at the bottom: confirm to archive. Archived users are **soft deleted** — they disappear from the default list but are not permanently removed. Their data is preserved.

### Viewing archived users (admin only)

Toggle the **Archived** tab on the users list. Archived users appear with a **Restore** button. Click restore to reactivate them.

### Anonymizing a user — GDPR (admin only)

For GDPR erasure requests: open the user, click **Anonymize (GDPR)**. A modal requires you to type **ANONYMIZE** to confirm. This action:
- Replaces name, email, phone, address, IBAN with anonymous placeholders
- Deletes photo and documents
- Soft deletes the user record
- **Cannot be undone**

Tournament results and payment records are preserved (no personal data attached).

### Bulk actions

Select multiple users with checkboxes. A confirmation bar appears at the bottom with available actions:
- **Activate selected** — marks them active
- **Deactivate selected** — marks them inactive
- **Archive selected** (admin only) — soft deletes all selected

After confirming, a success message appears.

### Guardian management

When creating or editing a **minor** (under 18), the **Guardian/Dependents** section is shown. Type a parent's name in the search box:
- If found: click their name to attach
- If not found: a small inline form appears — fill in first name, last name, phone, email, and optionally IBAN, then click **Add guardian**

One guardian can cover multiple siblings. A guardian who is also a club member can be searched from the existing member list.

**Note:** A minor cannot complete affiliation without a guardian linked. The system warns on save if missing, and blocks affiliation until added.

---

## 2. Contact Management (Onboarding Flow)

Go to **Website → Contacts**. Contacts submitted via the public club website form appear here.

> **Who can manage?** Viewing contacts is open to the whole committee. **Managing** a contact (qualifying the profile, sending an email, onboarding, deleting) is restricted to the **managing group**: administrators and committee members with the **secretary, president or vice-president** role. Other committee members see contacts but have no action buttons.

### Contact statuses

- **New** — just arrived or still being handled (follow-up in progress)
- **Processed** — dealt with
- **Rejected** — declined

### Qualifying a contact (profile)

The goal is to **note things gradually** as you learn them through the exchange, without forcing anything. Open a contact's detail panel: the **Profile** block offers **fully optional** fields, filled in as information arrives:

- **Age category** — child / teenager / adult
- **Experience** — never played / a few months / a few years / ranked player
- **Wants competition** — yes / no / not provided
- **Family can drive** — useful for interclub carpooling
- **Preferred days** — envisaged training slots

Each field saves as you go. These qualifications help **triage the inbox** (filters by age, experience, competition interest) and **find the right profiles** to compose homogeneous groups. When the contact becomes a member (onboarding), this information is **carried over** to their first registration — no double entry.

> **Tip**: some email templates are *questionnaires* designed precisely to **gather** that missing information (see "Email templates" below).

### Onboarding a contact as a member

For contacts with interest **Join us** or **Have a try**:

1. Open the contact detail panel (click the row)
2. Click **Onboard as user**
3. A pre-filled user creation form opens (drawer) with name, email, and phone pre-populated from the contact
4. Review and adjust as needed, then click **Save**
5. An invitation email is sent automatically to the contact's email
6. The contact status is automatically set to **Processed**

This eliminates double-encoding — the secretary only reviews and confirms, not re-types.

### Sending emails to contacts

From the contact detail panel, **Send an email** block:

- **Choose a template** from the dropdown → the editor opens **automatically, pre-filled** with the template's subject and body, variables already resolved (first name, interest, club name…). You **review/adjust** the text, then send. You can send yourself a copy.
- **Custom email…** → opens a **blank** editor to write freely.

Some templates **automatically apply a status** to the contact when sent (e.g. the decline template sets the contact to *Rejected*, a welcome template to *Processed*).

### Email templates

Go to **Website → Email templates** (managing group only). Create and edit your reply templates freely, without depending on a developer:

- **Name**, **key**, **subject**, **body**
- **Variables** to insert in subject/body: `{{first_name}}`, `{{last_name}}`, `{{full_name}}`, `{{interest}}`, `{{club_name}}`
- **Applied status** (optional): status given to the contact when the template is sent
- **Information questionnaire**: marks templates meant to *gather* missing information
- **Active/inactive**: an inactive template no longer appears in the send list

A few "system" starter templates exist (welcome, membership info, info request, polite decline, questionnaire, trial invite); their **key** is locked but their text remains fully editable.

### Updating contact status

Click the status badge on a row or in the detail panel to cycle through statuses.

### Deleting a contact

Click **Delete** in the detail panel. The contact is permanently removed (hard delete).

---

## 3. Tournament Management

Go to **Club events → Tournaments**.

### Creating a tournament

Click **New tournament** (wizard). Fill in steps:
1. **Basic info**: name, date, location, price, max players
2. **Format**: pools, qualifiers, match type, sets to win, handicap
3. **Schedule**: start time, duration per match, logistics buffer
4. **Publish**: review and publish (makes it visible to members)

### Managing a live tournament

From the tournament page, click **Manage live**. Available tabs:
- **Upcoming matches**: next matches to call
- **Tables**: table assignments
- **Pools**: pool standings
- **Bracket**: elimination bracket (after pool phase)
- **Rankings**: live rankings
- **Closure**: end the tournament and publish results

### Bulk registration

From the tournament management page, click **Bulk registration** to add multiple members at once.

### Inviting external players

From the tournament management page, click **Invite** to send invitations to non-members (by email).

---

## 4. Interclub Management

Go to **Club events → Interclubs**.

### Control center

The interclub control center shows all active interclub competitions, match schedule, and results.

### Team management

Go to **Interclubs → Teams**. Create or edit teams. Assign a captain to each team. The team builder lets you add/remove members.

### Captain selection

Go to **Interclubs → Captain selection**. Review available players for each round and confirm the lineup.

### Availability management

Send availability requests to players before each round from the interclub management page. Players respond via email or in-app. View responses in the availability dashboard.

### Broadcasting lineups

Once confirmed, broadcast the lineup to all selected players via **Broadcast lineup** — sends each player their selection notification.

### Match results

Enter match results from the control center after each round. Results update the standings automatically.

---

## 5. Meetings

Go to **Club events → Meetings**.

### Creating a meeting

Click **New meeting**. Fill in:
- Title and description
- **Date poll**: propose multiple date options for attendees to vote on, OR set a fixed date
- Invited members: select from the member list

### Sending invitations

From the meeting page, click **Send invitations**. Each invited member receives an RSVP email.

### Managing RSVPs

View attendance responses in real time on the meeting detail page. Responses: Attending / Not attending / Maybe. Meal reservations appear if enabled.

### Meeting agenda

Add agenda items to the meeting. Agenda is visible to all invited members.

### Minutes

After the meeting, add meeting minutes (free text). Click **Send minutes** to email minutes to all attendees.

### Action items

Create follow-up action items from the meeting, assigned to specific members with due dates.

### Postponing or cancelling

Click **Postpone** or **Cancel meeting** from the meeting detail page. All invited members receive a notification.

---

## 6. Training Management

Go to **Club events → Trainings**.

### Training packs

Training packs represent recurring training sessions (day, level, type). Create and manage packs from the trainings index.

### Approving training requests

When a member requests enrollment in a training pack, a notification appears. Go to the member's profile → **Registration management → Training** and click **Approve** or **Reject**. The member receives an email with the outcome.

### Managing waitlists

If a training pack is full, members join the waitlist. When a spot opens (cancellation), the first waitlisted member is automatically offered the spot via email.

### Planning board

Go to **Planning → Planning board**. It is a **decision-support tool** to compose the season's training groups and **visualise tension** (headcount vs capacity) — without touching real enrolments.

**Important: a plan is a thinking draft.** Nothing you do there is applied to the real packs or to members' enrolments. Once the committee has decided, you create/adjust the real packs via the trainings screen.

- **Create a plan**: give it a name and click **Create from season**. The plan is seeded from the active season: it copies the active packs and places enrolled members in their pack (or in the **Pool** column if they have none).
- **Compose by drag-and-drop**: move member cards between columns (packs), or to/from the **Pool**. Each card shows the **ranking**, **age category**, and *competitive / drives / captain / volunteer* badges — to aim for homogeneous groups.
- **Tension**: each column header shows headcount over capacity; a column **over capacity** is flagged — spot at a glance which slots are under pressure and whether the offer is too broad or too tight.
- **Model the offer**: add a **hypothetical group** ("+ Add a group"), change its name/level/day/capacity, or remove it. Removing a group **sends its members back to the Pool** (no one is lost). This lets you test "what if we opened a 2nd teen slot?".
- **Export / Import**: export a plan to **CSV, ODS or XLSX** to share with the committee or rework in a spreadsheet, then **re-import a CSV** (matched by licence, otherwise by email) to feed the layout back. Import only changes the plan.

**Permissions**: the whole committee can **view** a plan and export it; **composing, modelling the offer and importing** are restricted to the **managing group** (admin, secretary, president, vice-president).

---

## 7. Website Content

Go to **Website** in the navigation.

### Articles (news)

Create and edit news articles published on the public club website:
- Title, content (rich text), featured image
- Publish immediately or save as draft
- Set a "featured until" date for pinned articles

### Events

Manage public events displayed on the website event calendar.

### Contacts

See the contacts section above (section 2).

### Spam management

Go to **Website → Spam**. Review and manage flagged contact form submissions.

---

## 8. Club Administration

### Club information

Go to **Club admin → Club info**. Update club details: name, address, contact email, phone, IBAN, enterprise number, website URL.

### Rooms

Go to **Club admin → Rooms**. Manage training/competition venues:
- Name, address, building name
- Capacity for trainings and interclubs
- Total tables per room

### Tables

Go to **Club admin → Tables**. Manage individual ping-pong tables:
- Name, brand
- Availability toggle (mark out-of-service)

### Seasons

Go to **Club admin → Seasons**. Create and activate competition seasons (used for affiliations, interclubs, trainings). Each season has a name, start date, and end date. Only one season can be active at a time.

---

## 9. Force List

The force list ranks competitive players by strength for interclub team selection.

- Go to **Club admin → Members** and click **Recalculate force list** to trigger a manual recalculation
- The list recalculates automatically whenever a player's ranking or competitive status changes
- You can manually set or override a player's force list position from their profile

---

## 10. Treasury

### Payments

Go to **Treasury → Payments**. View all member payments (tournament registrations, training packs, affiliations):
- Filter by status: pending, paid, partially paid, refunded
- Click a payment to view details and mark as paid

### Transactions

Go to **Treasury → Transactions**. Record bank transactions (from your bank statement):
- Add a transaction (amount, date, communication reference, payer)
- Reconcile: match transactions to pending payments

### Cash register (bar)

Go to **Treasury → Cash register**. Manage bar orders, cash sheet, and stock movements if the bar module is active.

---

## 11. Registrations

Go to **Club admin → Registrations**. View and manage all pending affiliation requests for the current season. Approve or reject affiliations. Filter by status.

### Member's season involvement

When registering, each member (or the secretary on their behalf) can indicate, **for the season**:

- **Competitive** — wants to play interclubs/competition
- **Can drive** (+ number of seats) — to organise carpooling
- **Wants to be captain** — volunteers to lead a team
- **Volunteer help** — available to help (tournaments, bar, refereeing…)

If the member comes from an onboarded contact, these fields are **pre-filled** from what was noted on the contact.

### Season roster

Go to **Club admin → Season roster**. This is the overview of the active season's members, one row per member, with: **ranking**, **age category** (derived from the birth date), **competitive**, **drives** (+ seats), **captain**, **volunteer**.

- **Filter and sort** (by ranking, age category, competitive, drives, captain, volunteer) to prepare group and team composition.
- **Quick edit**: toggle drives / captain / volunteer directly and adjust the number of seats, without opening each profile.

The whole committee can **view** the roster; **editing** is restricted to the managing group (admin, secretary, president, vice-president).

---

## 12. Notifications Sent to Members

As a committee member, the following emails are sent automatically — you do not need to trigger them manually:

| Trigger | Email sent to |
|---|---|
| User created | Invitation email (set password link, 48h) |
| Invitation accepted | Welcome email |
| Tournament registration confirmed | Member |
| Tournament payment request | Member |
| Tournament payment reminder | Member |
| Waitlist added | Member |
| Waitlist spot offered | Member |
| Tournament cancelled | All registered members |
| Tournament updated | All registered members |
| Interclub availability request | Selected members |
| Interclub selection confirmed | Selected member |
| Interclub lineup broadcast | All selected members |
| Training pack approved | Member |
| Training pack rejected | Member |
| Training session cancelled | Enrolled members |
| Meeting invitation | Invited members |
| Meeting RSVP confirmation | Member |
| Meeting minutes | All attendees |
| Meeting cancelled/postponed | All invited members |
| Refund requested | Admin |

---

## 13. Tips & Best Practices

- **Invitation expiry**: if a member doesn't set their password within 48h, resend from the users list (look for the 🔴 Expired badge)
- **Minors**: always attach a guardian before starting the affiliation process — it's blocked otherwise
- **Bulk actions require confirmation**: a bar appears at the bottom — you must click Confirm. Closing the bar cancels the action
- **Archived users**: use the Archived tab to restore a member who left and rejoined
- **GDPR requests**: process anonymization requests promptly. The request comes from the member's profile (Danger zone). As admin, open the user and click Anonymize
- **Contact onboarding**: process JOIN_US contacts promptly — they're waiting to hear from you
