Rounded-full pill for article categories, player levels, event types and statuses.

```jsx
<Badge tone="primary" solid>Compétition</Badge>
<Badge tone="secondary" solid>Formation</Badge>
<Badge tone="success">Payé</Badge>
<Badge tone="info">Tous niveaux</Badge>
```

- `solid` fills with the brand color (used for categories on cards); default soft tint is for levels/statuses.
- Tones map to the brand + daisyUI semantic palette. Use `primary` for Compétition/Tournoi, `secondary` for Formation, `dark` for Entraînement.
