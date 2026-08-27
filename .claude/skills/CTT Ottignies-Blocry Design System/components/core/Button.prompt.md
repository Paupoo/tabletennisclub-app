Brand button for CTAs and form actions — club-blue primary, club-yellow secondary, with outline / ghost / danger variants and sm/md/lg sizes.

```jsx
<Button variant="primary">Rejoindre le club</Button>
<Button variant="secondary">En savoir plus</Button>
<Button variant="outline" size="sm">Annuler</Button>
<Button variant="primary" icon={<PlusIcon />}>Nouveau</Button>
```

- `variant`: primary (blue), secondary (yellow + blue text), outline, ghost, danger.
- Hover lightens the fill (blue→blue-light, yellow→yellow-light); ghost/outline tint gray.
- Pass `as="a"` + `href` to render a link-styled button (used in the public hero CTAs).
- `icon` takes any node — supply an inline Heroicon SVG to match the app.
