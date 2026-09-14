# Changelog — Bar on the club design system

**Branch:** `feat/bar-cttob-ui`  
**Date:** 2026-09-13  
**Tests:** 67 feature cases in the bar domain, plus 4 browser probes  
**Issues:** closes #125, partly closes #124  

The bar rendered its eight screens with a stylesheet of its own
(`public/assets/bar/bar.css`, 1155 lines) and a dark theme nobody else used. It now
renders inside the back-office shell, with the shared components, and its
conformance is held in place by the design-system tests rather than by attention.

---

## ✨ New Features

### The bar is in the back-office menu

A single **Bar** section in the sidebar, six entries, each one gated by the same
permission as its route — so a barman no longer sees links that lead to a 403.

- **New order** carries a badge with the number of items currently in the cart,
  visible from every screen of the back office
- Labels say what the screen *does*, not what it used to be called: **To cash in**
  rather than "Orders", because that list is a cash-out queue and not a history
- **Products**, **Categories** need `bar.products.manage`; **Cash sheet** needs
  `bar.cash_sheet.send`

### Favourites on the counter

The ten best-selling products — computed from paid orders — sit in their own
accordion at the top of the order screen, so the usual round is reachable without
opening a category. Open/closed accordions are remembered between orders.

### The cart follows your thumb

A floating pill at the bottom of the order screen shows the item count, the total,
and the way to the cart. It appears only once the cart holds something. A
validation bar at the top of the page scrolled out of view on the second product.

---

## 🔧 Improvements

### One look for the whole application

- The eight screens render through `<x-app-layout>`: same sidebar, same
  breadcrumbs, same dark theme, same toasts as every other admin screen
- `public/assets/bar/bar.css` and `bar/layout.blade.php` are gone; the bar is served
  by the Vite-built application stylesheet
- Shared components throughout: `<x-card>`, `<x-admin.shared.stat-card>`,
  `<x-admin.shared.row-menu>`, `<x-app-modal>`, `<x-breadcrumbs>`

### Readable receipts, reachable controls

- The cart and the payment receipt are capped at a readable measure — spread over
  the full content width, 800 px separated a product from its price
- Every `+` / `−` is a 44 px target, measured in a real browser at 390 px
- The product catalogue goes to two columns from `lg`: in one stretched column,
  500 px separated a product's name from its counter

### The counter no longer reloads the page on every drink

Each `+` and each `−` was a POST followed by a full page reload. Measured on the
development database: **20 SQL queries for 9 products**, because the `stock` accessor
ran two uncached `SUM` per read — so roughly a hundred on a real catalogue, *per tap*.
Scroll position was lost every time, and a toast fired on every addition.

This is the one screen in the application where latency is part of the task: you are
standing, one hand busy, with someone in front of you. A point of sale that slows the
service gets worked around — you write on paper, the stock goes wrong, and that breaks
the stock badges, the favourites and the cash sheet in turn.

The counter and the ticket are now Livewire components:

- **The counter updates in place.** No reload, no lost scroll position, and the
  catalogue is loaded with its stock aggregated in a single query.
- **The feedback is the state, not a message.** The counter going from 2 to 3 *is* the
  confirmation. Toasts are kept for what prevents the action — out of stock, cart
  ceiling, product off the menu. Thirty "added to cart" toasts in one round would end
  up hiding the only one that matters.
- **The shelves stay open.** Panel state moved from native `<details>` to Alpine: the
  server does not render an `open` attribute, so a Livewire morph would have collapsed
  every shelf on every tap.
- **The ticket follows the same rule** — not because you serve from it, but because a
  screen where the same gesture costs nothing one moment and a whole page the next
  teaches you to distrust it.

Three controllers went with the migration — `BarController`, `BarCartController` and
`BarProductController` — along with eleven routes that existed only to serve the Blade
forms. `routes/bar.php` went from 27 routes to 14.

### Tabs have names — closes #125

The cash-out queue showed a number. At a bar you remember "Alpa A's round" or
"Gilles", never "order #47", and an hour later — especially once the shift has
changed — nobody knows which line to settle.

**A name identifies a tab, not an order.** Typing a name that is already open
**joins** it rather than being refused: the barman does not need to know whether a
colleague opened it. Two tabs of the same name are therefore impossible by
construction, not by a validation message.

- **The question comes before the catalogue.** `/bar` now asks who the round is for,
  and lists the open tabs with what each one owes. The counter then says, in its
  title, which tab it is serving.
- **Pay now needs no name.** A walk-in orders, pays and leaves: nothing stays open, so
  there is nothing to find later. Asking for a name there would put a keyboard on the
  most frequent gesture of the evening, and the barman would type "x".
- **Names are matched on a normalised key** — lowercase, accents stripped, inner
  spaces collapsed. "Équipe A", "equipe a" and "EQUIPE  A" are one tab; "Alpa-A" is
  another. The displayed name stays as first typed. The comparison is done in PHP on a
  stored key rather than left to the database: MySQL collates case- and
  accent-insensitively while SQLite compares byte by byte, so delegating it would have
  produced a rule that is green in tests and wrong in production.
- **The key is released on payment**, so "Alpa A" can open a fresh tab the same
  evening while the settled one keeps its name in the history.
- **The queue is sorted alphabetically**, through `LocaleSort` — a byte-wise sort files
  "Vétérans" after "Zoé". The name titles each card; the number stays as a small
  secondary marker, because it is the handle the audit log and the stock movements use.
- **Renaming** is available from the row menu — expected to be rare, so it is not a row
  action. It goes through the same normalisation and the same uniqueness rule, and
  renaming onto a name that is already open is refused rather than silently merged.

### A colleague can cash in and add to a tab they did not open

`Permission::BarOrdersTakeover` was declared, granted to the BARMAN role, and checked
nowhere. Cashing in someone else's order returned a bare 403 — which does not describe
a bar, where the shift changes and the person settling is almost never the one who
served.

It is now checked in the four places that enforced ownership by hand, including one in
`BarCartService` that the design review had missed and that blocked even *adding* a
drink to a colleague's tab. The payment screen says who served the round when it is not
your own.

**Deletion stays with the person who opened the tab**: it restores stock and destroys
the lines, so it is irreversible. Taking over is for settling and completing, not for
erasing someone's work.

### Saving a tab returns you to the counter

The loop of a bar is serve → settle → serve the next one. Validating an order landed on
the queue — a list of what you had just done — and the queue offered no way back to the
counter except the sidebar, three gestures away, and only showed a "New order" button in
its *empty* state, which is exactly when it is not needed. The button is now permanent,
and saving a tab returns to the counter, which asks who is next.

### A dense stock table, instead of a tile per product — closes part of #124

`/bar/products` showed one card per product: two fields, a toggle and two buttons,
about 180 px tall each. Counting a shelf of forty references meant one screenful per
two products, and two neighbouring numbers were never on screen together.

It is now a Livewire entry grid:

- **Stock, alert threshold and availability** are edited in the row and saved when the
  field is committed — not on every keystroke, so typing `48` over `4` writes one stock
  movement rather than two.
- **Price, name, category and deletion** live in a drawer behind a Save button. Auto-save
  has no undo, and a mistyped price applies to every later sale without showing anywhere.
- **Two orders, both real tasks**: by shelf (sections per category, the order you walk an
  inventory in) or by urgency (flat, lowest stock relative to its threshold first — the
  order you write a shopping list in).
- **The alert threshold is per product**, falling back to the bar default. It used to be
  `<= 3` written into two views that could drift apart. A keg at 3 is out; a bag of
  crisps at 3 is fine.
- **The table stays a table on a phone** — a card per product is exactly the density this
  screen exists to remove. The columns a thumb does not need fold away instead; they stay
  reachable from the row's drawer. A sanctioned exception, written down in
  [DESIGN.md](DESIGN.md).

The stock field takes a **count**, not a movement: the gap becomes an incoming or FIFO
outgoing movement, and a count that is already right writes nothing.

**The table fits a phone.** It did not, at first: measured at 390 px, it asked for 376 px
inside a 308 px container — 68 px of sideways scrolling on every row read. Two causes,
neither of them the number of columns:

- **82 px of the 390 went to padding** before the first figure — the content area's own
  `p-5` plus the card's. The table card now uses a compact padding below `lg`; a reading
  card can afford its margins, an inventory grid cannot.
- **The "Available" header reserved 106 px for a 40 px toggle.** In a table it is the
  header that sets a column's width, not its contents. The label is now "On menu", which
  says the same thing to a barman, and the control columns carry `w-1` so they shrink to
  fit.

After: 332 px in a 332 px box, no sideways scroll. And the widened probe caught a defect
the first pass had shipped — `toggle-sm` renders 33×20, under the project's 24×24 floor
(WCAG 2.5.8). It is now the default size, which fits the column the header had already
reserved.

Both are held by new probes in `tests/Browser/BarLayoutTest.php`: `<x-table>` wraps its
table in an `overflow-x-auto`, so a table that is too wide pushes nothing — it scrolls
silently inside its card, which is why no existing probe had seen it.

### The QR code opens in a modal

Rendered in the page flow, it pushed **Payment received** below the fold — you had
to scroll while the customer waited, phone in hand. It is now a dialog on the top
layer, with the code, the amount and the confirmation together on screen.

### Product state, resolved in one place

A product's stock label carries four different meanings (unavailable, out of stock,
cart ceiling reached, running low), and each one changes the badge colour and
whether `+` is live. They are resolved once and shared by the favourites and the
categories, which had drifted apart — the favourites offered no way to remove.

---

## 🐛 Bug Fixes

- **A failed checkout returned a 500.** The controller redirected to
  `bar.carts.show`, a route name that does not exist, so every business error —
  insufficient stock, empty cart — became a `RouteNotFoundException` instead of the
  message explaining what to do. The cart, thirty taps of work, was left with no
  visible way back.
- **A failed cash-sheet send was silent.** The failure was flashed as a `warning`,
  a level the layout's flash-to-toast bridge did not relay. The page reloaded
  unchanged, exactly as it does on success, and the barman closed the till
  believing the treasurer had the figures.
- **An empty cash sheet was sent and reported as a success.** The guard tested the
  CSV string, and `buildCsv()` always writes its header row, so it never fired. A
  day with no sales went out as a three-column, zero-row file under "Email sent
  successfully". The guard now counts orders.
- **The cash-out queue claimed to hold paid orders.** It lists unpaid ones only
  (`where('is_paid', 0)`), so the "Paid" badge, the conditional cash-out label and
  the conditional delete entry could never render — they told the reader the queue
  mixes the two.
- **Two routes pointed at nothing**, one of them (`POST bar/cart/pay`) at a
  controller method that has never existed: a guaranteed 500 for anyone reaching
  it. Also removed: three dead methods on `BarController` — two duplicating the
  cart controller, one rendering a view that does not exist — and an unused view
  variable.
- **The cash-sheet send URL repeated its own prefix** (`bar/cashsheet/bar/cashSheet/send`).

---

## 📋 Architecture

### Flash messages: four levels, not two

`layouts/app.blade.php` bridged only `success` and `error` into a toast, so any
controller flashing `warning` or `info` wrote into the void — with nothing, on
either side, to say so. The bridge now resolves all four levels and picks the icon
per level. This closes the class of bug, not just its one instance:
`FlashToastBridgeTest` holds the four.

### The bar joined the design-system tests

Two architecture tests skipped `views/bar` on the grounds that it had "a layout of
its own, hand-written CSS, no shared component" — true when it was written, false
after this branch. Five others simply never looked there.

Reopening the perimeter turned up **six `border-base-200`** in the bar, against
**zero** in the three directories the tests already scanned: the rule was honoured
everywhere it was checked and broken six times exactly where it was not. Those six
are now `border-base-300`, which is also the right answer visually — `base-200` is
the page background, so the counter's row separators were painted in the colour of
the ground behind them.

`views/bar` is now in the perimeter of `AdminThemeVocabularyTest`,
`AdminBorderTokenTest`, `BrandPaletteTest`, `AdminIconographyTest`,
`PageHeaderFeedbackTest`, and `TextSizeFloorTest` (whose exclusion is gone).

### DS-D — enforcement perimeter

A new rule in [DESIGN.md](DESIGN.md): a design-system test lists the directories it
*includes*, never the ones it excludes; every exclusion carries the condition for
lifting it; and a screen that joins the design system joins its tests in the same
commit. An exclusion that no longer describes reality is a defect of the same rank
as a broken rule — it just never fails.

### Stock is aggregated instead of counted product by product

`BarProduct::getStockAttribute()` ran two uncached `SUM` queries per read, and a list
read it once per product. `scopeWithStock()` folds both sums into one query for the whole
list; the accessor uses them when they are there and keeps its old path otherwise.

| Screen | Before | After |
|---|---|---|
| Products table (9 products) | 18 queries to read stock | 1 |
| Counter `/bar` (9 products) | 20 queries, on every tap | 2 |
| Counter, 40 references | ~82 queries, on every tap | 2 |

This was the precondition for the table — issue #124 says as much — and it pays off most
on the counter, where every `+` still reloads the page.

### Dead code the table made removable

`BarProductController` (158 lines) and its four POST/PUT/DELETE routes were no longer
reachable from anything once the component wrote its own rows. Gone with them: the
`products/state` endpoint and the three session keys behind it, which existed to park a
half-typed product while you went off to create a category — a detour the drawer makes
pointless. `BarCategoryController::store` now returns to the categories screen with a
confirmation, instead of landing on the products screen without a word.

### Tests

- `tests/Feature/Bar/BarStockTableTest.php` — 14 rules on the stock grid: a count above
  the shelf becomes an incoming movement of the difference, a count below becomes a FIFO
  consumption, a correct count writes nothing, an emptied field is ignored rather than
  read as zero, the threshold is per product with a fallback, the price is deliberately
  absent from the grid, and the stock query count does not grow with the catalogue
- `tests/Feature/Bar/BarDesignSystemTest.php` — 16 rules holding the migration:
  the shell, the absence of `bar.css`, the unpaid-only queue, the permission-gated
  menu entries, the stock states, the cart pill, the named row menu, the QR modal
- `tests/Feature/Bar/BarFeedbackPathsTest.php` — the paths where something goes
  wrong: a failed checkout, a cash sheet that cannot be sent, a cash sheet with
  nothing to send, and the routes that must stay deleted
- `tests/Browser/BarLayoutTest.php` — four probes in a real browser: no sideways
  scroll on any of the eight screens at 390 px, every quantity control at least
  44 × 44, the receipt within a readable measure at 1440 px, and the whole QR modal
  on screen without scrolling

---

## ⚠️ Known limitations

- **The bar is not translated.** Four `__()` calls across 1348 lines of Blade,
  where comparable screens run 15 to 56 per 200 lines. A Dutch-speaking barman gets
  a French interface under a translated breadcrumb, and flash messages mix French
  and English.
- The cash sheet still has no per-order breakdown, so a tab name cannot be traced from
  it — only the history carries names.
- **No Bancontact.** Cash, QR and "offered" only, while the cash sheet reserves an
  "Other" row that cannot be filled.
- **The cash sheet is out of reach of every bar role.** No role holds
  `bar.cash_sheet.send`; only `ADMINISTRATOR` gets it, through `Permission::cases()`.
- **The history has no pagination and no mobile card twin**, and loads every order
  ever with its items when the period is "All".
- Issue #124 is only partly closed: the "to restock" filter and a product's movement
  history are still missing.
- Stock fixtures without `remaining_quantity` are invisible to
  `StockService::consumeFIFO`, so a test written from the existing fixture cannot
  take an order to checkout. `BarFeedbackPathsTest` documents the trap.

Each of these is recorded, with its options and its priority, in the design review
this branch closes.
