Round avatar for club members — shows a photo, or initials on a club-blue disc.

```jsx
<Avatar name="Aurélien Paulus" />
<Avatar src="/uploads/me.jpg" name="Aurélien Paulus" size={56} ring />
```

- Falls back to up-to-two initials when `src` is missing.
- `ring` adds the white + club-yellow halo used to mark the active user.
