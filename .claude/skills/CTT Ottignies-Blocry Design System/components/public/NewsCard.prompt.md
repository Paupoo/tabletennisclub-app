Public-site article card — 16:9 image that zooms on hover, a category pill + date, title, 3-line excerpt and a "Lire la suite" link. Composes `Badge`.

```jsx
<NewsCard
  image="/assets/images/background_news.webp"
  category="Compétition"
  date="14 juin 2026"
  title="Victoire de l'équipe A en D2"
  excerpt="Un week-end décisif pour nos joueurs…"
  href="/news/victoire-equipe-a"
/>
```

- Category drives the pill color (Compétition/Tournoi = blue, Formation = yellow, else neutral).
- The whole card border brightens to club-blue on hover. Place in a 3-up responsive grid for the news section.
