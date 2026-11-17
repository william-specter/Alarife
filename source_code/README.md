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
- **Real-time collaboration** through Laravel Reverb (no Redis required) with private
  encyclopedia/user channels, live activity and export progress.
- **Octane + FrankenPHP** application server for fast worker-based responses.
- **Cross-encyclopedia references** to published articles/topics, pinned in releases.

## Requirements

- PHP 8.3+ (tested on 8.5) with `pdo_pgsql`, `zip`, `fileinfo`
- Composer 2
- PostgreSQL 15+ (tested on 18)
- Node.js 22+ and **pnpm 10** (pinned via `packageManager`)
- Redis: **optional** (database cache/queue/session is the default)
- Reverb WebSocket server (started by `composer run dev`)
- A queue worker for exports (`php artisan queue:work --queue=exports,default`)
- Optional: Docker for the app, reusing the existing shared PostgreSQL/Redis containers

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
php artisan octane:install --server=frankenphp   # one-time local FrankenPHP binary

# one command: Octane/FrankenPHP + queue + Reverb + logs + Vite
composer run dev
```

The full runtime guide is [`../Documentation/13-runtime-realtime-and-execution.md`](../Documentation/13-runtime-realtime-and-execution.md).

Demo accounts created by the seeder:

| Role | Email | Password |
| --- | --- | --- |
| Platform admin | `admin@alarife.test` | `password` |
| Editor | `editor@alarife.test` | `password` |

The demo includes five encyclopedias (one per theme), rich article blocks, published
releases and generated offline archives.

## Verification

```bash
php artisan test                 # 57 backend tests
pnpm test:unit                   # Vitest helpers
pnpm exec vue-tsc --noEmit       # TypeScript
pnpm build                       # production assets
vendor/bin/pint --test           # PHP style
```

Docker (Octane/FrankenPHP + Reverb + queue, using the **existing** PostgreSQL container;
no PostgreSQL/Redis/Keycloak images are pulled):

```bash
docker start sourcecode-postgres-1
docker exec sourcecode-postgres-1 psql -U dev_user -d chargman \
  -c "CREATE DATABASE alarife OWNER dev_user;"   # first time only
docker compose up -d --build
docker compose exec -u www-data app php artisan migrate:fresh --seed
# app: http://localhost:8000 | websockets: http://localhost:8080
```

Keycloak is present in the shared environment but **not used**; authentication remains
Laravel session auth (no SSO). Redis is bundled in the shared environment but optional.

Offline export smoke test (after generating an archive):

```bash
# download the ZIP from Exports, then
unzip ancient-civilizations.zip -d /tmp/offline
# open /tmp/offline/ancient-civilizations/index.html in a browser
```

Reference Playwright scripts used for the UI/realtime review live in `scripts/`.

## Architecture in one paragraph

Content is stored as structured JSON in PostgreSQL, normalised by a domain layer and
rendered by allow-listed PHP services into semantic HTML. The live reader and the static
exporter consume the **same** Blade theme package, so online and offline editions cannot
drift. Publishing freezes the content, topic tree, media references and theme settings into
an immutable snapshot; exports are reproducible, cached by artifact key, validated for
broken links/remote assets and delivered as a checksummed ZIP.
