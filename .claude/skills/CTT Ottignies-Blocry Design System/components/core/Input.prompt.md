Labeled text field used in the contact form and admin/auth screens — white fill, gray border, club-blue focus ring.

```jsx
<Input label="Email" type="email" required placeholder="vous@exemple.be" />
<Input label="Nom" icon={<UserIcon />} hint="Prénom et nom" />
<Input label="Mot de passe" type="password" error="Champ requis" />
```

- Focus brings a club-blue border + soft blue ring (matches the app's `input:focus` rule).
- `error` paints the border red and shows the message; `hint` shows neutral helper text otherwise.
