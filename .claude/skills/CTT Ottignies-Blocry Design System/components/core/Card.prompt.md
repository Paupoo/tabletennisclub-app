The default content surface — white, hairline gray border, soft shadow. Used everywhere: feature tiles, news, events, admin tiles.

```jsx
<Card hoverable>…</Card>
<Card accent="var(--club-blue)">Featured event</Card>
<Card accent="var(--club-blue)" accentSide="left">Dirigé · 18h–20h</Card>
```

- `hoverable` mimics the public site: border → club-blue, shadow lifts.
- `accent` paints a 4px bar — `top` for featured-event cards, `left` for schedule rows (color encodes the type).
- Children render inside the padded body; the accent bar sits flush above/beside it.
