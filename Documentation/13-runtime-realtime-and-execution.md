# 13 — Runtime, Real-time and Execution Guide

This document is the operational guide for running Alarife. It covers the **Composer**
workflow, the **Docker** workflow, Octane/FrankenPHP, Reverb real-time broadcasting, the
optional nature of Redis, and the explicit decision not to use Keycloak/SSO.

## 1. Runtime at a glance

| Component | Choice | Notes |
| --- | --- | --- |
| Application server | **Laravel Octane + FrankenPHP** | Fast worker-based HTTP serving; same command in Docker and Composer modes |
| WebSocket / real-time | **Laravel Reverb** | First-party broadcaster; no Redis required for a single node |
| Queue | Database (default) | Redis optional; exports run on dedicated workers |
| Cache/session | Database (default) | Redis optional |
| Database | PostgreSQL 18 | Existing shared Docker instance in this environment |
| Frontend | Vue 3 + Inertia + Vite | No JSX/TSX files; `.vue` and `.ts` only |
| Authentication | Laravel session auth | **No SSO / no Keycloak integration** |

## 2. Composer execution

### 2.1 Prerequisites

- PHP 8.3+ (8.5 verified), Composer 2
- PostgreSQL reachable from `.env`
- Node 22+ and pnpm 10
- One-time FrankenPHP binary for local Octane:

```bash
cd source_code
php artisan octane:install --server=frankenphp
```

This downloads the local `frankenphp` binary (ignored by Git). Docker does not need it,
because the Docker image is based on `dunglas/frankenphp`.

### 2.2 One command

```bash
cd source_code
cp .env.example .env          # first run
php artisan key:generate
php artisan migrate --seed
composer run dev
```

`composer run dev` starts, in one terminal:

1. `php artisan octane:start --server=frankenphp` (HTTP on `:8000`)
2. `php artisan queue:listen` (default, exports, media)
3. `php artisan reverb:start` (WebSocket on `:8080`)
4. `php artisan pail` (logs)
5. `pnpm dev` (Vite)

It uses `pnpm exec concurrently`; **no npm/npx invocation is used**.

### 2.3 Existing Docker PostgreSQL from the host

The environment already contains a PostgreSQL 18 container. To use it from Composer mode:

```bash
docker start sourcecode-postgres-1          # if it is stopped
docker exec sourcecode-postgres-1 psql -U dev_user -d chargman \
  -c "CREATE DATABASE alarife OWNER dev_user;"   # first time only
```

Set in `.env`:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=5434
DB_DATABASE=alarife
DB_USERNAME=dev_user
DB_PASSWORD=dev_password
```

This is the same database used by the Docker app container, so both workflows share data
(but only run one at a time on port 8000).

## 3. Docker execution

The stack intentionally **does not download PostgreSQL, Redis or Keycloak images**. It
joins the existing shared Docker network (`sourcecode_default` by default) and uses the
existing PostgreSQL service. The existing Redis and Keycloak containers are not required:
Redis is optional and Keycloak/SSO is not used.

```bash
cd source_code
docker start sourcecode-postgres-1 sourcecode-redis-1   # existing containers, no pull
docker exec sourcecode-postgres-1 psql -U dev_user -d chargman \
  -c "CREATE DATABASE alarife OWNER dev_user;"           # first time only

docker compose up -d --build
docker compose exec -u www-data app php artisan migrate:fresh --seed
```

Open **http://localhost:8000**. WebSockets are served by the `reverb` service on
**http://localhost:8080**.

Services in `compose.yaml`:

| Service | Purpose | Port |
| --- | --- | --- |
| `app` | Octane + FrankenPHP | `8000` |
| `reverb` | Reverb WebSocket server | `8080` |
| `queue` | Queue workers for exports/media/default | internal |
| PostgreSQL | external existing container | host `55433` → container `5432` |
| Redis | external existing container, **optional** | host `6381` |
| Keycloak | existing but **not used** | — |

Network override for another environment:

```bash
SHARED_DOCKER_NETWORK=my_shared_network docker compose up -d --build
```

Docker notes:

- Database credentials for the shared PostgreSQL are `dev_user` / `dev_password` and the
  app database is `alarife`.
- Always run `docker compose exec -u www-data app …` for Artisan commands, so files created
  in the mounted storage volumes remain owned by the runtime user.
- `RUN_SEED=1 docker compose up` seeds an empty database automatically; `RUN_MIGRATIONS=0`
  is used for `queue` and `reverb` because the `app` container performs migrations.
- The app image builds assets with pnpm and runs `octane:start --server=frankenphp`.
- MinIO is not part of the default stack; local filesystem storage is used.

## 4. Octane and FrankenPHP details

- Local server: `php artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8000`
- Worker reload after code changes: `php artisan octane:reload`
- Docker command:
  `php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000 --workers=auto --max-requests=500`
- The Docker image is based on `dunglas/frankenphp:php8.4`, installs `pdo_pgsql`, `zip`,
  `intl`, `opcache` and `pcntl`, and drops privileges to `www-data`.
- HTTP/2 and HTTP/3 are skipped without TLS (development); terminate TLS at a proxy in
  production.
- Because Octane keeps workers alive, code that caches request state must be avoided.
  The domain services are stateless; the reader theme CSS cache is keyed by settings hash.

## 5. Real-time broadcasting (Reverb, no Redis required)

`BROADCAST_CONNECTION=reverb` is the default. Reverb is started by `composer run dev` and by
the Docker `reverb` service. Redis is **not** required for broadcasting. For multi-node
Reverb scaling, Redis can be enabled later with `REVERB_SCALING_ENABLED=true`.

### Channels

| Channel | Authorization |
| --- | --- |
| `private-encyclopedia.{id}` | owner, editor, author member or platform admin |
| `private-user.{id}` | the user themselves |

### Events

| Event | Published when | UI effect |
| --- | --- | --- |
| `encyclopedia.updated` / `activity.created` | any tracked write | Dashboard refreshes activity/stats; Overview and Publishing refresh |
| `article.saved`, `article.status`, `article.restored`, `article.deleted` | explicit editor save, status change, restore, delete | A remote-editor banner appears in the editor for other editors |
| `publication.published` | a release is created | Publishing/Overview refresh release state |
| `export.updated` | export job starts/progresses/completes/fails | Export screen updates progress/download in place |

The authoring shell shows a green **Live** indicator while the Reverb WebSocket is connected.
Polling remains as a fallback on the export screen (10 s) if Reverb is unavailable.

### Environment

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=774321
REVERB_APP_KEY=alarife-dev-key
REVERB_APP_SECRET=alarife-dev-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

Docker overrides `REVERB_HOST=reverb` for server-to-server publishing; the browser uses
`window.location.hostname:8080`, so no build-time Vite variable is required. The public key
is exposed through a `<meta name="reverb-key">` tag only when Reverb is the broadcaster.

### Why Redis is optional

- Default cache/session/queue drivers are database-backed.
- Reverb single-node does not need Redis; start with `php artisan reverb:start`.
- Optional Redis can be enabled later by setting `CACHE_STORE=redis`,
  `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis` and `REDIS_CLIENT=predis` (the pure-PHP
  client is installed).

## 6. No JSX/TSX

The project contains **no `.jsx` or `.tsx` files**. Components are Vue Single File
Components (`.vue`) with TypeScript in `<script setup lang="ts">`; helpers are `.ts`.
Verify with:

```bash
find source_code -path '*/node_modules' -prune -o -path '*/vendor' -prune \
  -o -type f \( -name '*.jsx' -o -name '*.tsx' \) -print
```

## 7. No Keycloak / SSO

- Keycloak is **not** integrated, not referenced by the app, and not part of `compose.yaml`.
- Authentication remains Laravel's session-based starter-kit authentication.
- The existing Keycloak container may keep running in the shared environment; Alarife does
  not use it.
- Route authorization is handled by Laravel policies, not OIDC/SSO claims.

## 8. Verification

```bash
cd source_code
php artisan test                    # backend + broadcasting channel tests
pnpm test:unit                      # Vitest
pnpm exec vue-tsc --noEmit
pnpm build
vendor/bin/pint --test
```

Verified in this environment:

- Octane response header: `Server: FrankenPHP Caddy`, HTTP 200.
- Reverb connected, green **Live** indicator visible.
- Dashboard refreshed after a broadcast activity event.
- Remote-editor banner appeared in a second session.
- Export progress updated from 55% over the socket.
- Clean Docker launch with the external PostgreSQL, seed, and completed archives.

Visual proof: `Documentation/screenshots/admin-realtime-collaboration.png` and
`admin-realtime-export.png`.
