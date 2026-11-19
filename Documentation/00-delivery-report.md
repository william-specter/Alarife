# 00 — Delivery Report

This report is the audit trail for the delivered project. It answers three questions:
what documentation exists, what was implemented, and what visual/technical evidence proves it
works.

## 1. Documentation audit

| Document | Purpose | Present |
| --- | --- | --- |
| `README.md` (root + source) | entry points, setup, demo accounts | ✅ |
| `01-product-requirements.md` | capabilities, actors, acceptance criteria | ✅ |
| `02-system-architecture.md` | monolith, modules, runtime paths, technology matrix | ✅ |
| `03-data-model.md` | ERD, tables, constraints, invariants | ✅ |
| `04-content-model-and-editor.md` | Tiptap schema, rendering, sanitisation, autosave | ✅ |
| `05-theme-system.md` | theme contract, tokens, versioning | ✅ |
| `06-publishing-and-static-export.md` | snapshots, lifecycle, export pipeline, offline search | ✅ |
| `07-ui-ux-and-design-system.md` | screens, interaction, responsive rules, accessibility | ✅ |
| `08-routes-authorization-and-security.md` | routes, policies, threat model, headers | ✅ |
| `09-testing-and-quality.md` | test matrix, acceptance gates, QA workflow | ✅ |
| `10-deployment-and-operations.md` | Docker, Composer workflow, CI/CD, operations | ✅ |
| `11-implementation-roadmap.md` | status, deviations, known gaps, next work | ✅ |
| `12-ui-review.md` | Playwright review findings/fixes + screenshot index | ✅ |
| **`00-delivery-report.md`** | this audit | ✅ |

**Visual proof:** `Documentation/screenshots/` contains final captures of the dashboard,
overview, editor, appearance designer, three reader themes, the cross-reference flows, mobile
dashboard and the offline export opened directly from disk.

## 2. Implementation inventory

### Platform
- Laravel 12 modular monolith, Octane + FrankenPHP, Inertia 2, Vue 3 + TypeScript, Tailwind CSS.
- Laravel Reverb real-time broadcasting (Redis optional) with private encyclopedia/user channels.
- PostgreSQL schema (JSONB editor content, full-text search vector), Redis-ready cache/queue,
  S3-compatible storage abstractions, local-disk defaults.
- Demo seeder with five encyclopedias, one per theme, rich articles, cross-references,
  three publications and three completed offline archives.

### Authoring
- Dashboard, creation wizard, workspace overview, settings, covers, topics, article list,
  media library, appearance designer, publishing and export screens.
- Tiptap editor with custom image/gallery/callout/citation nodes, outline, autosave,
  optimistic locking, local crash recovery, revisions, SEO preview and media picker.
- Server-side structured content validation and allow-listed HTML rendering.

### Cross-encyclopedia references
- Link a work to **published articles or topics from other published encyclopedias** during
  creation or later from Settings.
- Search only returns readable published targets; the server re-validates every selection.
- Reader shows a **Related works** section.
- References are pinned into publication snapshots and exported archives.
- Stale/unpublished targets are hidden from readers and flagged in the editor.

### Publishing and export
- Immutable, versioned release snapshots with theme version/settings and content.
- Queued export pipeline: Blade pages, CSS, JS, media, local search index, sitemap, README,
  relative links, validation, ZIP and SHA-256.
- Cached artifacts, author/reader downloads, export history and progress.
- Verified offline from `file://` (carousel, navigation, images and search).

## 3. Execution paths

### Composer (single command)
`composer run dev` uses **pnpm** and starts Octane/FrankenPHP, the queue listener, Reverb,
Pail and Vite together. `composer test` runs PHPUnit + Vitest. Verified end-to-end.

### Docker
Added a production-style `Dockerfile` based on **`dunglas/frankenphp:php8.4`** (pnpm asset
build, Composer vendor stage, `pdo_pgsql`, `zip`, `intl`, `opcache`, `pcntl`) and a
`compose.yaml` stack with `app` (Octane/FrankenPHP), `reverb`, and `queue`.

It deliberately **does not pull PostgreSQL, Redis or Keycloak images**. It joins the existing
`sourcecode_default` Docker network and uses the already-running PostgreSQL 18 container
(database `alarife`, user `dev_user`). Redis is optional and Keycloak/SSO is not used.

Verified:

```bash
docker compose up -d --build
docker compose exec -u www-data app php artisan migrate:fresh --seed
```

The app returned 200 through FrankenPHP, the reader rendered, 6 cross-references existed and
all 3 export archives completed. Reverb served `:8080` and the browser showed the **Live**
indicator.

An offline/self-contained stack is provided by `compose.standalone.yaml`: bundled PostgreSQL,
local media volumes, self-hosted Instrument Sans, no Redis/Keycloak/external network and
`pull_policy: never`. Images are exported/imported with `docker save` / `docker load`.

Verified: started the standalone stack, seeded five encyclopedias, loaded local fonts,
resolved 6 cross-references and completed 3 export archives with no external services.

## 4. Verification evidence

| Check | Result |
| --- | --- |
| PHPUnit | **57 passed** (196 assertions) |
| Broadcasting | Reverb channel auth + Live UI verified |
| JSX/TSX audit | none present; Vue SFC + TypeScript only |
| Keycloak/SSO | not integrated |
| Vitest | **3 passed** |
| `vue-tsc --noEmit` | clean |
| ESLint / Prettier | clean |
| Pint | passed |
| Production asset build | passed |
| Composer dev with pnpm | verified (Octane/FrankenPHP + Reverb + queue + Vite) |
| Docker shared stack + seed + exports | verified |
| Docker standalone/offline stack | verified: bundled PostgreSQL, local fonts, seed, exports |
| Offline export from `file://` | verified: 3 carousel slides, article images, search result, no console errors |
| Cross-reference UI | verified: wizard search, selection/note, settings list, reader related works |
| Real-time | verified: Live indicator, dashboard activity refresh, remote-editor banner, export progress |
| Mobile navigation | verified at 390×844 |

## 5. Visual proof index

All images live in `Documentation/screenshots/`:

- `admin-dashboard.png`, `admin-dashboard-mobile.png`
- `admin-overview.png`
- `admin-editor.png`
- `admin-appearance.png`
- `admin-create-cross-references.png`
- `admin-cross-references-settings.png`
- `reader-editorial-article.png`
- `reader-atlas-home.png`
- `reader-nocturne-home.png`
- `reader-cross-references.png`
- `export-offline-home.png`
- `admin-realtime-collaboration.png`, `admin-realtime-export.png`

## 6. Repository state

The repository root contains `Docs/`, `Documentation/` and `source_code/`.
`main` holds the delivery commit. `dev` is branched from it and is the active branch for
continued development.

## 7. Known limitations (documented, not hidden)

- Member-invite UI is not built (membership schema/policies exist).
- Scheduled publishing control is deferred.
- Image variant generation/`srcset` is not implemented; originals are exported.
- Cross-references are encyclopedia-level related works, not arbitrary inline citations
  inside article bodies (the editor link tool still supports manual links).
