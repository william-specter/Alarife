# Review scripts

These Playwright scripts are development aids used to review the product against the
reference material in `../../Docs/examples` and to verify exported websites.

- `review.mjs` — signs in, captures the dashboard, overview, article list, editor,
  appearance and exports screens at 1440×1000.
- `capture-docs.mjs` / `references-review.mjs` — capture documentation screenshots
  (including cross-reference flows).
- `offline-test.mjs` — opens an extracted export from `file://`, checks the carousel,
  article rendering and offline search, and reports console errors.

Start the application (`php artisan serve`, seeded database) and run:

```bash
node scripts/review.mjs
node scripts/offline-test.mjs
```
