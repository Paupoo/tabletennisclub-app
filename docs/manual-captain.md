# Team Captain Manual — Interclub

This manual explains how a captain manages team selection from the app. For general member features (profile, calendar, registrations), refer to the [Member Manual](manual-member.md).

---

## 1. Accessing the selections view

Go to **Interclub → Selections** in the main navigation. The page is titled **Selections** and shows all matches for your team in the current season.

If you are captain of multiple teams, a team selector appears at the top — use the **Filters** button to switch between your teams.

---

## 2. Reading the match list

Each row represents one interclub match. From left to right:

| Element | Description |
|---|---|
| **Coloured bar** | Match status (see below) |
| **Week / Date** | Match day number and date |
| **Opponent** | Opposing club name, with a **Home** or **Away** badge |
| **Time** | Kick-off time |
| **X/Y avail.** | Number of available players out of the total required |
| **Status badge** | **Sent** if the lineup was confirmed, or the player selection count |
| **Buttons** | **Select** and availability request (envelope icon) |

### Colour meanings

| Colour | Status | Meaning |
|---|---|---|
| 🟢 Green | **Confirmed** | Lineup sent to the team — nothing to do |
| 🟡 Orange | **Actionable** | Enough players available or selection complete, but lineup not yet sent |
| 🔴 Red | **Urgent** | Match within 14 days and not enough availability responses received |
| ⚪ Light grey | **Upcoming** | Match is far away, no urgency |
| ◼ Dark grey | **Past** | Match has been played |

An urgent row also has a light red background to draw your attention.

A selection **declared short-handed** (see "Playing with three" below) and sent is **green**, like a full one: for you, it is settled. A lineup sent at four and then cut by a withdrawal **without** that declaration goes back to red or orange: the app no longer treats it as settled.

### Alert banner

If one or more matches are **Urgent**, a red banner appears at the top of the page with a direct shortcut to each affected match. Click a match in the banner to open its selection drawer immediately.

---

## 3. Requesting availability

On any upcoming match row, click the **envelope icon** (to the right of the Select button). An email is sent to all team members who have not yet responded, asking them to confirm their availability.

> Only members who have not yet replied receive the email — no need to chase players who have already responded.

---

## 4. Making the selection

Click **Select** on the match row. A drawer opens on the right.

### Understanding the selection drawer

At the top of the drawer: a progress bar **X / Y** shows how many players are selected versus how many are required. The bar turns green when the team is complete.

Below it, the **team roster** is displayed. For each player:

| Element | Description |
|---|---|
| **Ranking** | Player ranking number (blue background = selected, red = unavailable) |
| **Name** | Player name |
| **Availability badge** | Green = available, red = unavailable, absent = no response yet |
| **Note** | Optional message left by the player with their response |
| **Played / Sel.** | Number of matches played and selections this season |
| **Checkbox** | Whether the player is in the current selection |

### Selecting / deselecting a player

Click a player card to toggle them in or out of the selection. A **blocked** player (lock icon 🔒) is already selected in another team for the same match day and cannot be selected again.

> **Tip:** Prefer players with the **Available** (green) badge. Players without a response can still be selected, but contact them directly to confirm.

### Saving without sending

Click **Save selection**. The selection is saved and the match status changes to **Actionable** (orange). You can come back and modify it before sending the lineup.

### Playing with three

The rules let a men's team **start with 3 of its 4 players** (art. C.25.6), and a ladies', youth or veterans' team with **2 of 3** (art. C.25.7). Fewer than that is a forfeit.

1. **Look for a fourth player first**: the free players of the match day, or a player from a lower team — within their force index (art. C.22.1.1), the rule on the third player who actually played in the higher team (art. C.22.1.3), and the one-match-per-week rule (art. C.20.1).
2. **Failing that, play with three.** At the minimum, a box appears under the progress bar: *"I found no other player… we will play with 3"*. **Without it, the selection stays a draft and nobody is convoked.**
3. **Ticked**, the send window opens with a reminder that the missing player's matches will be lost. Convoked players read in their email that the team plays short; the rest of the team is asked to come forward if they become available.
4. **Below the minimum**, the app saves without sending and reminds you to report the forfeit **at least 48 hours before** (art. C.33.1).

The declaration is stored with your name and the date. It is withdrawn if you find a fourth player or the team falls below the minimum; it survives swapping one player for another.

---

## 5. Sending the lineup to the team

Once the selection is saved, a **Notify the team** window opens automatically.

### What players receive

All team members receive an email showing the confirmed lineup. **Selected players** additionally receive a **calendar invite (ICS)** to add to their agenda.

### Adding a meetup message (optional)

In the **Meetup info** text area, enter any practical details: meeting time, attire, departure point… This message is included in the email sent to the team.

> Example: *"Meet at 18:45 at the hall entrance, bring your club shirt."*

### Sending

Click the send button. The match status changes to **Confirmed** (green). The action cannot be undone, but you can make a new selection at any time — a new email will be sent.

### Not sending now

If you want to save without notifying the team, click **Don't send**. The selection is saved but no email is sent. The status remains **Actionable** (orange).

---

## 6. Switching seasons

The season selector at the top right lets you view selections from previous seasons. Past matches show the lineup that was sent but can no longer be edited.

---

## 7. Frequently asked questions

**A player is not responding to the availability request.**
Follow up with them directly, or select them anyway if you know they are available. The lineup email will inform them of their selection.

**I made a mistake in the selection after sending.**
Reopen the selection drawer, make changes, save and resend. A new email will be sent to the whole team with the corrected lineup.

**A player withdraws and I have nobody to replace them.**
Remove them, tick *"we will play with 3"*, save and send. The removed player is told, and the three who remain are convoked again with a note that the team plays short. Without the box, only the removed player is told and the fixture goes back to needing attention; you can come back and tick it later.

**A player is blocked (red lock icon).**
They are already selected in another club team for that match day. Choose a different player or contact the club selector to rearrange.

**I cannot see my team.**
Check that the club committee has designated you as captain of the team. If the problem persists, contact an administrator.
