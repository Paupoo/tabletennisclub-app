Quick-action tile from the club admin dashboard — an icon chip over a label + sublabel, in a white rounded-xl card that lifts on hover.

```jsx
<DashboardTile icon={<UsersIcon/>} label="Membres" sub="142 actifs" />
<DashboardTile icon={<BellIcon/>} label="Notifications" badge={3} />
<DashboardTile icon={<CreditCardIcon/>} label="Cotisation" sub="À payer" color="secondary" />
```

- The chip colour carries **urgency, never domain**. `neutral` (default) for a tile with nothing pending, `primary` (club blue) when something is waiting on the reader, `secondary` (club yellow) for the one entry a persona must find first. Grouping by domain is the job of the section header, not of colour.
- `badge` shows a red count chip; pass null/0 to hide. A badge forces the club blue — a tile with a count is waiting on someone by definition.
- Lay tiles out in a responsive grid (the dashboard uses 4–6 columns).
