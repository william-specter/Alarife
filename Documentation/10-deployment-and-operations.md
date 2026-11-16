# 10 — Deployment and Operations

## 1. Local development

Recommended Docker services mirror production, but Alarife runs natively too.

```bash
# one command: server + queue + logs + Vite (pnpm)
cd source_code
composer run dev
```

`composer run dev` uses `pnpm exec concurrently` (never npm) and starts Laravel, the queue
listener, Pail and Vite together. For separate processes:

```bash
php artisan serve
php artisan queue:work --queue=default,exports,media
pnpm dev
```

Docker (application + queue + services):

```bash
docker compose up -d --build
docker compose exec -u www-data app php artisan db:seed --force
# http://localhost:8000
```

The Docker image builds assets with pnpm, installs Composer production dependencies and runs
PHP-FPM/CLI with a queue worker. `RUN_SEED=1 docker compose up` performs the seed on first
boot. Postgres/Redis are exposed on 55433/6381 to avoid conflicts with host services. MinIO is
optional and runs with `--profile object-storage`; the default stack uses local storage.

Environment essentials:

```dotenv
APP_URL=http://localhost:8000
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=alarife
DB_USERNAME=alarife
DB_PASSWORD=secret
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
FILESYSTEM_DISK=public
EXPORT_DISK=local
EXPORT_RETENTION_DAYS=30
```

`local` is used for snapshots and archives (private); `public`/S3 for media. In development,
`php artisan storage:link` exposes media. In production media is served from S3/CDN.

## 2. Production topology

```
Cloudflare (CDN/WAF)
        │
Load balancer
  ├── app containers (PHP-FPM + Nginx, stateless)
  ├── queue: default (notifications, activity)
  ├── queue: exports (dedicated workers)
  └── scheduler (php artisan schedule:work)
        │
Managed PostgreSQL (PITR)   Managed Redis   S3/R2 (media + archives)
```

- Application containers are immutable; assets built in CI.
- No local state: sessions/cache/queues in Redis, media/archives in object storage.
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
