# 10 — Deployment and Operations

## 1. Local development

There are two supported execution paths. The full command reference is in
[13-runtime-realtime-and-execution.md](13-runtime-realtime-and-execution.md).

### Composer (one command)

```bash
cd source_code
php artisan octane:install --server=frankenphp   # one-time local binary
composer run dev
```

`composer run dev` starts Octane/FrankenPHP, the queue listener, Reverb, Pail and Vite with
`pnpm exec concurrently` (never npm). For separate processes:

```bash
php artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8000
php artisan queue:work --queue=default,exports,media
php artisan reverb:start --host=127.0.0.1 --port=8080
pnpm dev
```

### Docker (existing shared services, no service image pulls)

The environment already provides PostgreSQL 18, Redis and Keycloak containers through the
`sourcecode` Docker project. The Alarife compose file:

- joins the existing `sourcecode_default` network;
- uses the existing PostgreSQL (`dev_user` / `dev_password`, database `alarife`);
- does **not** define or pull PostgreSQL, Redis, Keycloak or MinIO images;
- runs `app` (Octane + FrankenPHP), `queue` and `reverb` from the Alarife image.

```bash
docker start sourcecode-postgres-1               # existing container
docker exec sourcecode-postgres-1 psql -U dev_user -d chargman \
  -c "CREATE DATABASE alarife OWNER dev_user;"    # first time only
docker compose up -d --build
docker compose exec -u www-data app php artisan migrate:fresh --seed
```

App: `http://localhost:8000` · Reverb: `http://localhost:8080`. Keycloak and SSO are not used;
Redis is not required by the default configuration.

For an air-gapped/self-contained host, use `compose.standalone.yaml` (bundled PostgreSQL,
local app image, no external network and no runtime internet). Build/export online, then:

```bash
docker save alarife-app:dev postgres:18-alpine -o alarife-images.tar
# offline host
docker load -i alarife-images.tar
docker compose -f compose.standalone.yaml up -d
docker compose -f compose.standalone.yaml exec -u www-data app \
  php artisan migrate:fresh --seed
```

The admin font is self-hosted in `public/fonts`; reader themes use system fonts. No runtime
font/CDN request is required.

### Environment essentials

```dotenv
APP_URL=http://localhost:8000
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5434
DB_DATABASE=alarife
DB_USERNAME=dev_user
DB_PASSWORD=dev_password
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
BROADCAST_CONNECTION=reverb
OCTANE_SERVER=frankenphp
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SERVER_HOST=0.0.0.0
```

Redis is optional. To opt in later, set `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`,
`SESSION_DRIVER=redis`, `REDIS_CLIENT=predis` and the `REDIS_*` variables. Reverb works
without Redis for a single node.

`local` is used for snapshots and archives (private); `public`/S3 for media. In development,
`php artisan storage:link` exposes media. In production media is served from S3/CDN.

## 2. Production topology

```
Cloudflare (CDN/WAF)
        │
Load balancer
  ├── Octane/FrankenPHP app containers (stateless)
  ├── Reverb WebSocket containers (port 8080)
  ├── queue: default (notifications, activity)
  ├── queue: exports (dedicated workers)
  └── scheduler (php artisan schedule:work)
        │
Managed PostgreSQL (PITR)   Optional Redis   S3/R2 (media + archives)
```

- Application containers are immutable; assets built in CI.
- Octane workers are stateless; no request-scoped state is cached between requests.
- Sessions/cache/queue default to PostgreSQL; Redis is an optional scaling layer.
- Reverb is the broadcasting transport; Redis is only needed for multi-node Reverb scaling.
- Export workers have larger disk/temp space and a 15-minute job timeout.
- Reader traffic never touches the export queue.

## 3. CI/CD pipeline

```
push / PR
 ├── php -l, composer validate, composer install --no-dev? (CI uses dev)
 ├── vendor/bin/pint --test
 ├── phpstan analyse
 ├── php artisan test
 ├── pnpm install --frozen-lockfile
 ├── pnpm run lint && pnpm run format:check
 ├── vue-tsc --noEmit && pnpm build
 ├── Playwright visual/offline review (nightly or pre-release)
 └── build Docker image (tag = commit sha)
deploy
 ├── run migrations --force (pre-deploy job)
 ├── switch app containers (zero downtime)
 ├── restart queue workers gracefully
 └── warm caches / verify /up and a reader page
```

Rollback: deploy previous image; migrations are written to be backward compatible for one
release. Export renderer version prevents mixing artifacts across releases.

## 4. Scheduled jobs

| Command | Schedule | Purpose |
| --- | --- | --- |
| `exports:prune` | daily 03:00 | delete expired draft archives and failed temp dirs |
| `publications:verify` | hourly | ensure current release archive exists; re-dispatch if missing |
| `media:prune-orphans` | weekly | remove unreferenced variants (never originals in use) |
| `activity:prune` | monthly | trim logs older than 180 days |
| `queue:prune-failed` | weekly | retain failures 30 days |

## 5. Backups and recovery

- **PostgreSQL**: daily full + continuous WAL; restore drill quarterly.
- **Object storage**: versioning on media and archives; lifecycle rules for drafts.
- **Snapshots**: release snapshots are small JSON and are backed up with the DB bucket;
  archives can be regenerated from snapshots even if storage is lost.
- **RPO 15 min / RTO 4 h** target for the first production year.

## 6. Monitoring

- Laravel Pulse for request/queue/database health; Sentry for exceptions.
- Alerting: export failure rate > 2% (15 min), queue wait > 10 min, 5xx > 1%, disk > 80%.
- Structured logs include `encyclopedia_id`, `export_id`, `publication_id`, `job_id`.
- Reader cache hit ratio and LCP reported from the CDN/RUM.

## 7. Security operations

- Secrets in the platform secret manager; `.env` never committed.
- Dependency updates weekly; `composer audit` and `pnpm audit` in CI.
- TLS everywhere; HSTS at the edge; private S3 buckets with signed URLs.
- Least-privilege database user (no superuser), separate migration user in production.
- Quarterly access review of platform admins and encyclopedia members.

## 8. Static hosting of exports

Exports are plain files. Recommended delivery:
- Upload to S3/R2 + CloudFront/Cloudflare; cache HTML briefly, assets immutably.
- `404.html` configured as the error document.
- No rewrite rules needed: directories contain `index.html`.
- For offline media (USB), simply copy the extracted folder; no install required.

## 9. Capacity notes

- A 1,000-article encyclopedia produces roughly 1,000 HTML files plus media; the exporter
  streams page-by-page and keeps memory bounded.
- Export time is dominated by media copying and ZIP compression; workers can run 2–4 jobs
  concurrently per node.
- Reader pages are cached by release version, so a release invalidates only the version key,
  not individual pages.
