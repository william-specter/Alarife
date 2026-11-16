# Alarife — Thematic Encyclopedia Platform

A multi-encyclopedia authoring, publishing and distribution platform built with
**Laravel 12 + Inertia 2 + Vue 3 + TypeScript + Tailwind CSS**, with a structured
Tiptap editor, five reader themes, immutable publication snapshots and a complete
**offline static website exporter**.

> The product and technical documentation lives in [`../Documentation`](../Documentation).
> Reference diagrams and UI inspiration are in [`../Docs`](../Docs).

## Highlights

- **Multiple independent encyclopedias** per workspace, each with its own identity,
  three-cover carousel, topics, articles and theme.
- **Structured authoring** with Tiptap JSON as the source of truth, autosave, optimistic
  locking, revision history and media references that survive theme changes.
- **Five themes** (Classic Reference, Modern Minimalist, Editorial Magazine, Illustrated
  Atlas, Contemporary Dark) sharing one Blade renderer between the live site and exports.
- **Versioned releases** stored as immutable snapshots with their own theme version.
- **Offline-first exports**: queued jobs render every page, collect media, generate a local
  JavaScript search index, validate links and package a checksummed ZIP that works from
  `file://` without a server, remote font or CDN.
- **Authorization and tenant isolation** enforced server-side through policies.

## Requirements

- PHP 8.3+ (tested on 8.5) with `pdo_pgsql`, `zip`, `fileinfo`
- Composer 2
- PostgreSQL 15+ (tested on 18)
- Node.js 22+ and **pnpm 10** (pinned via `packageManager`)
- Redis (optional locally; recommended in production)
- A queue worker for exports (`php artisan queue:work --queue=exports,default`)
- Optional: `docker compose up -d postgres redis minio` for local services

## Setup

```bash
cd source_code
cp .env.example .env
php artisan key:generate

# create the database, then:
# create both databases first:
#   createdb alarife && createdb alarife_test
php artisan migrate --seed
php artisan storage:link

pnpm install --frozen-lockfile

# one command (pnpm + concurrently): server, queue, logs and Vite
composer run dev
```

Demo accounts created by the seeder:

| Role | Email | Password |
| --- | --- | --- |
| Platform admin | `admin@alarife.test` | `password` |
| Editor | `editor@alarife.test` | `password` |

The demo includes five encyclopedias (one per theme), rich article blocks, published
releases and generated offline archives.

## Verification

```bash
php artisan test                 # 54 backend tests
pnpm test:unit                   # Vitest helpers
pnpm exec vue-tsc --noEmit       # TypeScript
pnpm build                       # production assets
vendor/bin/pint --test           # PHP style
```

Docker (app + queue + PostgreSQL + Redis; MinIO optional):

```bash
docker compose up -d --build
docker compose exec -u www-data app php artisan db:seed --force
# http://localhost:8000
```

MinIO is optional: `docker compose --profile object-storage up -d minio`.

Offline export smoke test (after generating an archive):

```bash
# download the ZIP from Exports, then
unzip ancient-civilizations.zip -d /tmp/offline
# open /tmp/offline/ancient-civilizations/index.html in a browser
```

Reference Playwright scripts used for the UI review live in `scripts/`.

## Architecture in one paragraph

Content is stored as structured JSON in PostgreSQL, normalised by a domain layer and
rendered by allow-listed PHP services into semantic HTML. The live reader and the static
exporter consume the **same** Blade theme package, so online and offline editions cannot
drift. Publishing freezes the content, topic tree, media references and theme settings into
an immutable snapshot; exports are reproducible, cached by artifact key, validated for
broken links/remote assets and delivered as a checksummed ZIP.
