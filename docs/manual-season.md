# User manual — Preparing a new season

This manual walks through everything needed to open a new season, in order: create the season, close the previous one, reopen affiliations, import the affiliates from the federation, then import the interclub calendar.

It is written for the committee member holding the **Seasons** delegation, and it crosses two others: **Members** (affiliations, listing import) and **Interclubs** (teams, divisions, calendar). For the rest of the back office, see the [Committee Manual](manual-committee.md).

> **When?** In practice between early July and late August. The first interclub match is played in mid-September, and the federation's calendar is only complete a few weeks beforehand.

---

## Who may do what

| Step | Delegation required |
|---|---|
| 1. Create the season | **Seasons** (`seasons.manage`) |
| 2. Close the outgoing season's affiliations | **Members** (`subscriptions.manage`) |
| 3. Activate the new season | **Seasons** (`seasons.manage`) |
| 4. Open affiliations | **Members** (`subscriptions.manage`) |
| 5. Import the affiliate listing | **Members** (`users.import`) |
| 6. Import the interclub calendar | Server access (see §6) |
| 7. Rebuild the teams | **Interclubs** (`interclubs.manage`) |

An administrator holds everything. A committee member without a delegation sees these screens read-only.

---

## 1. Create the season

**In principle there is nothing to do.** Every **1 July at 06:00**, the application creates the next two seasons automatically (1 September to 30 June). Just check they exist.

Go to **Club Settings → Seasons**. The list shows existing seasons, with the active one marked.

If the season is missing:

- **More actions → Auto-provision**, then **Provision**: recreates the next two seasons from the active one. The operation is safe and repeatable — a season that already exists is skipped.
- **New season**: for unusual dates. Name, start date, end date. Dates may not overlap an existing season — the application refuses and says so.

> **Naming convention**: `2026-2027`, a dash with four digits either side. This is not cosmetic: **the name is what links the club's season to the federation's** when importing the calendar (§6). A name that departs from it makes the import fail with an explicit message.

---

## 2. Close the outgoing season's affiliations

Before switching over, close affiliations on the season that is ending. It stops a member affiliating to a finished season while you prepare the next one.

Go to **Members Admin → Affiliations**, then **More actions → Close affiliations**. A message confirms: "Affiliations are now closed."

While closed, the **Register a member** button disappears, and a member trying to affiliate from their own space is refused.

---

## 3. Activate the new season

Still in **Club Settings → Seasons**, on the new season's row, click **Activate** and confirm.

**Only one season is active at a time**: activating the new one deactivates the old one in the same operation. There is nothing to switch off by hand.

Activation is structural — the active season is what affiliations, interclubs, trainings and dashboards all read. Do it when you are ready to carry on, not weeks ahead.

> The previous season's data is untouched: results and line-ups stay readable all year by changing season in the filters.

---

## 4. Open affiliations

Back in **Members Admin → Affiliations**, then **More actions → Open affiliations**. Confirmation: "Affiliations are now open."

From then on, members can affiliate from their own space, and the secretariat can do it for them with **Register a member**.

---

## 5. Import the federation's affiliate listing

When the federation publishes the affiliate listing, load it rather than retyping it.

Go to **Members Admin → Users → More actions → Import the federation listing**, and drop the file in.

The screen shows you **every line and what it proposes to do with it** before writing anything: create, update, skip. You arbitrate, then confirm. Nothing is sent to members at that point: **the import triggers no invitation** — the committee decides later when to grant access.

See the [Committee Manual](manual-committee.md) for the review screen in detail.

---

## 6. Import the interclub calendar

This is the step that replaces the most manual work: teams, divisions, opponent clubs and fixtures, loaded straight from the federation's database.

> **Today this is a command run on the server**, by whoever has technical access. A delegation-guarded button will follow in the interface; the behaviour described here will not change, only how it is triggered.

### What it loads

In one pass, for the active season:

- the **divisions** the club is entered in, with their level and category;
- **our teams**, named by their letter (Men A to F, Veterans A to C…);
- the **opponent clubs** met, created if unknown;
- **every fixture**, with date, time and venue address;
- the **byes** (rounds where a team does not play).

### The command

```bash
php artisan interclubs:import-aftt
```

It takes no parameters: the club number is read from the club's own record, and the season is the one the federation calls current, matched to the club's season **by name** (hence §1).

It prints a summary table:

```
Importing 2026-2027 for BBW214 from the federation…

+---------+-------+-----------+---------+---------+
| Created | Moved | Unchanged | Removed | Refused |
+---------+-------+-----------+---------+---------+
| 129     | 0     | 0         | 0       | 0       |
+---------+-------+-----------+---------+---------+
```

- **Created** — newly created fixtures
- **Moved** — fixtures whose date, time or venue changed; each is listed below
- **Unchanged** — nothing to do
- **Removed** — fixtures dropped from the federation calendar and deleted here
- **Refused** — divisions the application cannot model; each is named

### Re-running is safe

**It is in fact the normal mode.** Fixtures are recognised by the federation's own identifier: a fixture that moved is **corrected in place**, not recreated. Availability answers already given, selections and recorded results all stay attached.

So run it whenever you suspect a change — a postponement, a change of hall, a recomputed division.

### What happens when the federation drops a fixture

This really does occur, when a team withdraws and the division is recomputed.

- If **nobody has touched it**, the fixture is deleted silently: it was never seen.
- If **somebody answered on it** (availability, selection, result), it is **kept** and flagged in the report. You decide.

A fixture entered by hand is never touched: the import only recognises what it wrote itself.

### Refused divisions

If the federation enters the club in a category the application does not know — a **youth** division, typically — the import refuses it and shows it:

```
Refused, category or level we do not model: Division 3B ... - Jeunes
```

This is not a breakdown, it is a signal. The other divisions import normally, and somebody has to decide how the club wants to model that category before going further. Report it to the technical lead.

### Byes

A round with no opponent is loaded, but **does not appear** in the schedule, in "My matches", on the dashboard, or in the calendar exported to your phone: it would only show as a fixture against nobody. You will find it on the **Results** screen, shown as "Bye".

> One caveat worth knowing: the federation gives a bye no date. The application assigns it **the earliest day the rest of the division plays that round**. That is a reasonable inference, not information from the federation.

### Reloading a season from scratch — `--fresh`

```bash
php artisan interclubs:import-aftt --fresh
```

**To be used once, at the start of a season.** This option **empties the season** — divisions, teams, fixtures — then rebuilds it from the federation.

It takes **the captains and the team rosters** with it: those are club data, which the federation does not know and cannot give back. They will have to be re-entered (§7).

Before acting, the command states what it will destroy and asks for confirmation:

```
Rebuilding 2026-2027 deletes 129 fixtures, 81 teams, 0 captain assignments and 0 roster entries.
Delete them and rebuild from the federation? (yes/no)
```

And if the season already carries member answers, **it refuses**:

```
Refusing: 1 availability answers and 0 recorded results would be lost.
Re-run with --force if that is really what you want.
```

At the start of a season those counts are zero and the command never gets in your way. The day it objects, it is right: pass `--force` only if you accept losing those answers.

### Preparing next season early — `--season`

```bash
php artisan interclubs:import-aftt --season=2027-2028
```

Useful when the federation already publishes the following season without having made it current. The name must belong to a season **existing on both sides**; otherwise the command stops and says so.

### What the import does not do

- **It does not load results or scores.** Those are entered by hand in **Interclubs → Results**. The federation publishes them and the application will read them in a future version.
- **It does not compose the teams.** It creates the teams and their letter; who plays in them stays a club decision.

### If something goes wrong

The import **fetches everything from the federation before writing a single row**, and writes in one transaction. If the federation stops answering halfway through, nothing is written and the command says so:

```
Nothing was written. The federation failed mid-import: ...
```

Run it again later: no half-finished state was left behind.

---

## 7. Rebuild the teams

After a `--fresh` import, the teams exist with their letter and division, but they are **empty**: no captain, no players.

Go to **Interclubs → Configuration → Our teams** to name the captains and attach players to each team.

Remember the **force list** too, which ranks competitive players and feeds selections: it recalculates itself whenever a ranking changes, but after a federation listing import it is worth forcing a recalculation from **Members Admin → Users → More actions → Recalculate force list**. An individual position is corrected from the player's profile.

Only then can captains request availabilities and compose their selections.

---

## 8. Checks before opening the season to members

A quick pass that avoids most surprises:

- [ ] The right season is **active** (Club Settings → Seasons).
- [ ] **Affiliations are open**, and a test member can register from their own space.
- [ ] The **interclub schedule** shows the fixtures, with complete addresses (Interclubs → Planning).
- [ ] The number of teams matches what the club actually entered — **a missing or extra team shows up here**, not in October.
- [ ] Every team has a **captain**.
- [ ] No division was left "Refused" in the import report.

---

## Frequently asked

**The federation moved a fixture. What now?**
Run `php artisan interclubs:import-aftt` again. It will be corrected in place and listed under "Moved". Availabilities already given are kept. Then tell the players concerned — the application does not do that automatically yet.

**I edited a fixture by hand and the import overwrote it.**
That is intended: the federation is authoritative on date, time, venue and opponent. What belongs to the club — availabilities, selections, results — is never overwritten. If a manual correction is genuinely needed it will be undone at the next import; report the error to the federation instead.

**An opponent team is named differently here than at the federation.**
Harmless, and also intended: clubs are recognised by their federation **licence number**, not their name. The import **never renames** an already-registered club, so it cannot undo the contact details, IBAN or addresses the club typed in. It only fills in a missing address.

**Can I run the import several times a day?**
Yes. Without `--fresh` it has no side effects.

**The import fails with "the club has no season by that name".**
The season does not exist yet on the club side, or its name does not exactly match the federation's (§1). Create or rename it, then run again.

---

## See also

- [Committee Manual](manual-committee.md) — the rest of the back office
- [Captain Manual](manual-captain.md) — availabilities and line-ups
- [Selector Manual](manual-selector.md) — force list and selections
- [Delegations and permissions](permissions.md) — who holds what
