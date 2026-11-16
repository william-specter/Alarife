# 11 — Implementation Roadmap and Status

## 1. Phases

| Phase | Scope | Exit criteria |
| --- | --- | --- |
| 1. Foundation | Starter kit, auth, encyclopedia CRUD, DB, covers, topics, articles (basic) | Author can create an encyclopedia and content |
| 2. Authoring | Tiptap editor, media library, revisions, autosave, sanitisation | Rich article round-trip + recovery |
| 3. Presentation | 5 themes, settings, preview, reader pages, search | Public site renders on any theme |
| 4. Publishing & export | Snapshots, releases, queue, static site, offline search, ZIP | Downloaded site works offline and matches theme |
| 5. Collaboration | Members/roles, review, scheduling, duplication, activity | Multi-author safety |
| 6. Production | CI/CD, monitoring, scaling, audits | Operable deployment |

## 2. Current status (this build)

### Delivered
- Laravel 12 + Inertia 2 + Vue 3 + TypeScript + Tailwind starter kit, pnpm pinned.
- Domain modules for encyclopedias, topics, articles, media, themes, publishing, exports,
  search, activity; actions, policies and form requests.
- Full schema with constraints (3 covers, slug uniqueness, revision uniqueness, exports).
- Tiptap editor with custom callout/gallery/citation/timeline blocks, autosave, conflict
  handling, outline, media picker, revision history.
- Five themes sharing one Blade implementation for reader and exporter.
- Reader: home, topic, article, search, download, print styles, accessibility affordances.
- Publication snapshots, release history, unpublish, automatic public archive.
- Queued export pipeline with media collection, relative links, offline search, sitemap,
  validation, ZIP, checksum, caching, download authorization.
- Author UI: dashboard, wizard, overview, settings, covers, topics, articles, editor, media,
  appearance, publishing, exports.
- Cross-encyclopedia references: picker in the creation wizard and settings, server-side
  validation of published targets, related-works rendering and snapshot pinning.
- Seed data with five encyclopedias (one per theme), cross-references between three of them,
  and working demo archives.
- PHPUnit coverage (54 tests) for content rendering, authorization and tenant isolation,
  cross-references, article autosave/revisions, publishing, search, downloads and export structure; Vitest
  utilities; Playwright screenshot and offline-export review of key screens.

### Documented deviations
1. **Laravel 13 / Inertia 3 / Tailwind 4** → Laravel 12 / Inertia 2 / Tailwind 3.4, because
   the official Vue starter kit (v1.0.2) pins these versions. Rationale: supported baseline
   over an unproven combination; upgrade is a contained follow-up.
2. **Pest** → PHPUnit for backend tests (starter kit default); Vitest is configured for
   frontend utilities. Same coverage intent.
3. **S3/MinIO** → local disk by default with an S3-compatible driver option; the storage
   abstraction is used everywhere so switching is config-only.
4. **PDF/EPUB/Markdown** → deliberately deferred; snapshot architecture supports them.
5. **Custom domains** → deferred; reader routing resolves slugs today and the lookup seam is
   documented.

### Known gaps / next work
- Member invitation UI (schema + policies exist; management screen is phase 5).
- Scheduled publishing UI (status/date column prepared; scheduler command pending).
- Article diff viewer shows block counts rather than a rich inline diff.
- Media variants (WebP/srcset) are collected but not generated; originals are exported.
  Current behaviour is correct, heavier than ideal.
- Real-time collaboration, comments and notifications are out of scope.
- Platform admin screens are minimal (dashboard link + gate).
- Load testing of export concurrency not yet executed.

## 3. Milestone acceptance record

| Gate | Status |
| --- | --- |
| AC-01 multiple encyclopedias | ✅ |
| AC-02 three-cover limit (app + DB) | ✅ |
| AC-03 per-encyclopedia theme, content untouched | ✅ |
| AC-04 content round-trip + restore | ✅ |
| AC-05 draft isolation | ✅ |
| AC-06 immutable snapshots | ✅ |
| AC-07 offline website | ✅ (verified by extraction test) |
| AC-08 offline search | ✅ |
| AC-09 visual theme parity | ✅ (shared Blade renderer) |
| AC-10 server-side authorization | ✅ (policy tests) |
| AC-11 tenant isolation | ✅ (IDOR tests) |
| AC-12 checksum + reproducibility | ✅ |

## 4. Recommended next increments

1. Member management UI + invitations.
2. Scheduled release + timezone-aware worker.
3. Image variant generation and srcset in export.
4. Rich revision diff + autosave merge UI.
5. Theme marketplace/JSON import with strict schema.
6. PDF/EPUB exporters reusing the snapshot + content renderer.
7. Custom domain routing + per-domain TLS automation.
8. Observability dashboards and export SLA alerts.

## 5. Engineering conventions

- PSR-12 via Pint; strict types in domain services.
- Actions are invokable classes with one public `__invoke`/`execute`; no business logic in
  controllers.
- Form requests own validation; policies own authorization; actions own transactions.
- Vue: `<script setup lang="ts">`, typed props, composables for reusable state, no business
  rules that must be trusted.
- All new routes require a policy test and at least one happy-path feature test.
- Every export-facing change bumps `Renderer::VERSION` and adds a structural test.
