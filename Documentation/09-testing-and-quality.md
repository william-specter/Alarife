# 09 — Testing and Quality

## 1. Strategy

| Layer | Tool | Scope |
| --- | --- | --- |
| Unit | PHPUnit | content renderer, theme settings, snapshot builder, link rewriter, search scoring |
| Feature | PHPUnit + RefreshDatabase | CRUD, authorization, publishing, export job with fake storage/queue |
| Frontend unit | Vitest | editor serialization helpers, token/format utilities |
| Browser review | Playwright | wizard, editor autosave, theme preview, reader, export download (manual scripts) |
| Static export | PHPUnit + file assertions | ZIP structure, no remote URLs, offline search data, link integrity |
| Accessibility | manual + axe-core (optional) | dashboard, editor, reader article |
| Visual | Playwright screenshots | key screens at 360/768/1280 |

Tests run on PostgreSQL (JSONB + FTS behaviour) in CI; SQLite is acceptable for fast local
unit tests but not for search/export feature tests.

## 2. Critical acceptance tests

1. A user can own multiple encyclopedias; the dashboard lists only authorized ones.
2. A fourth cover is rejected by validation and by the database constraint.
3. Article content round-trips through save/reload without loss; revisions restore correctly.
4. Theme switch does not alter article `content` (hash before/after).
5. Draft reads return 404 for guests; published reads return 200 with the active theme.
6. A member of encyclopedia A cannot access B by changing the URL or IDs.
7. Autosave with a stale `revision_number` returns 409 and does not overwrite.
8. Publishing creates an immutable snapshot; editing afterwards leaves the snapshot unchanged.
9. Publishing twice creates v1 then v2, unique per encyclopedia.
10. Export job produces: index, every topic/article, CSS, JS, search index, sitemap, README.
11. Export ZIP contains no absolute app URLs and no remote assets.
12. Offline search index includes every published article title and resolves to existing files.
13. Reader download is denied when `export_enabled=false` and for private encyclopedias.
14. Cache hit: requesting the same export twice reuses the completed artifact.
15. Export failure marks the row `failed` with a message and leaves no partial ZIP.
16. Unsafe link scheme (`javascript:`) is stripped by the renderer.
17. Unknown editor nodes are not rendered as HTML.
18. Theme settings outside schema are rejected with 422.
19. Slugs remain unique per encyclopedia; reserved slugs are rejected.
20. Media referenced by a cover cannot be deleted (422 with blocking reason).
21. Creating an encyclopedia with references stores only published article/topic targets.
22. Settings reject a private/draft target and roll back the encyclopedia update.
23. Reader pages render related works from other published encyclopedias.
24. Publication snapshots and exports pin cross-references and their target URLs.

## 3. Test data

Factories for every model with states:
`EncyclopediaFactory::published()`, `->private()`, `->withCovers(3)`;
`ArticleFactory::published()`, `->inReview()`, `->withContent()`;
`PublicationFactory`, `EncyclopediaExportFactory::completed()`.
Rich content fixtures live in `tests/Fixtures/content/` (docs with every node type).

## 4. Export verification procedure

For an exported archive:
1. Extract to a temp directory.
2. Assert `index.html`, `topics/*/index.html`, `articles/*/index.html` exist and are non-empty.
3. Parse every HTML file; collect `href`/`src`; assert local targets exist.
4. Assert no `http(s)://` references to the app host, bunny.net, CDNs, or fonts.
5. Load `assets/search/search-index.js`; assert entries non-zero and targets exist.
6. Assert `assets/css/style.css` contains `--site-primary` and no `@import url(http`.
7. Checksum matches the export row; ZIP has a single root folder.
8. (Browser) Open `file://…/index.html`, run search, operate carousel, navigate to an article.

## 5. Quality gates

- PHP: `php artisan test`, `vendor/bin/pint --test`, `phpstan` (level 6, module paths).
- JS/TS: `pnpm run lint`, `pnpm run format:check`, `pnpm run build`, `vue-tsc --noEmit`.
- Browser review: `source_code/scripts/review.mjs` and `offline-test.mjs` against a seeded
  database; run manually or on a nightly job, since CI keeps the deterministic PHP/JS suites.
- Accessibility: axe critical violations = 0 on dashboard/editor/reader.
- Performance budget: article HTML < 120 KB gzipped excluding images; theme CSS < 60 KB.
- Visual review: screenshots captured with Playwright at 360/768/1280/1440 and compared
  manually each milestone; regressions investigated.

## 6. Manual UX review checklist (Playwright)

- [ ] Dashboard at 360, 768, 1280, 1600 — no overlap, cards aligned.
- [ ] Wizard validation and keyboard navigation.
- [ ] Editor toolbar states, image insertion, autosave indicator, conflict banner.
- [ ] Theme preview updates within 1 s of a token change; mobile toggle works.
- [ ] Publishing readiness reflects real state.
- [ ] Export progress and download; extracted site works offline.
- [ ] Reader: carousel with 1/2/3 images; TOC anchors; search; print preview.
- [ ] Focus rings visible; tab order logical; dialogs trap focus.
