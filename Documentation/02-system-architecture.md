# 02 — System Architecture

## 1. Style: modular monolith

Alarife is a **Laravel modular monolith**. One deployable application serves the authoring
SPA (Inertia/Vue), the public reader (server-rendered Blade) and the static export worker
(queued jobs). Modules own their models, actions, policies, requests and tests; they
communicate through explicit services, not by reaching across into another module's
internals.

```
┌────────────────────────────────────────────────────────────────────────────┐
│ Presentation                                                               │
│  Vue 3 + Inertia authoring UI   │   Blade reader themes   │  Export output  │
└───────────────┬─────────────────┴───────────┬─────────────┴─────────────────┘
                │ Inertia/JSON                 │ HTML
┌───────────────▼─────────────────────────────▼──────────────────────────────┐
│ Application (Laravel HTTP)                                                 │
│  Controllers · FormRequests · Middleware · Policies · Rate limiting        │
└───────────────┬────────────────────────────────────────────────────────────┘
│ Domain modules                                                             │
│  Encyclopedias · Topics · Articles · Media · Themes · Publishing · Exports │
│  Actions (use-cases) · Domain services · Events · Jobs                     │
└───────────────┬────────────────────────────────────────────────────────────┘
│ Infrastructure                                                             │
│  PostgreSQL · Redis (cache/queues) · S3-compatible storage · ZIP builder   │
└────────────────────────────────────────────────────────────────────────────┘
```

Reference diagrams: `Docs/development/mermaid-architecture-diagram.png`,
`Docs/development/export-generation-workflow.png`,
`Docs/development/future-horizontal-scaling.png`.

## 2. Module boundaries

| Module | Owns | Key services |
| --- | --- | --- |
| Identity | users, platform role, auth | Laravel starter kit auth |
| Encyclopedias | encyclopedias, covers, members, settings | `RequestExport` consumers, `EncyclopediaWorkspace` presenter |
| References | cross-encyclopedia links to published works | `CrossReferenceRegistry` |
| Topics | topic hierarchy and ordering | `TopicTree` |
| Articles | articles, revisions, editorial workflow | `SaveArticle`, `RestoreRevision`, `ArticlePublisher` |
| Media | media assets, uploads, variants | `StoreMedia`, `MediaUrlResolver` |
| Themes | registry, schemas, tokens, CSS | `ThemeRegistry`, `ThemeSettings`, `ThemeStylesheet` |
| Rendering | normalised site context, content→HTML | `SiteBuilder`, `ContentHtmlRenderer`, `LinkRewriter` |
| Publishing | releases, snapshots, article workflow | `PublishEncyclopedia`, `SnapshotBuilder` |
| Exports | export records, jobs, archive | `RequestExport`, `GenerateEncyclopediaExport`, `StaticSiteWriter`, `LinkValidator` |
| Search | online and offline search | `EncyclopediaSearch`, `SearchIndexBuilder` |
| Activity | dashboard feed | `RecordActivity` |

Dependency direction is **inward**: Exports → Publishing → Rendering → Themes/Content →
Encyclopedias. A module may depend on a lower module; never the reverse. There is no
cross-module Eloquent relation access from controllers.

## 3. Runtime paths

### 3.1 Authoring request
```
Vue page ──Inertia──► Controller ──► FormRequest ──► Policy ──► Action ──► Model
                                                            └──► Inertia props
```
Every mutating route runs authorization **before** the action. The action wraps writes in a
transaction, records activity, and dispatches queued side effects only after commit.

### 3.2 Public reader
```
GET /{encyclopedia}/articles/{slug}
  → ReaderController
  → resolves published encyclopedia (visibility + status)
  → SiteBuilder builds normalised context (memoised per request)
  → Blade theme view + ContentHtmlRenderer
  → HTML response with cache headers keyed by release version
```
Readers never use the authoring JSON endpoints. Reader pages are server rendered so that
the same Blade theme can be rendered to static files by the exporter.

### 3.3 Publication
```
POST /author/{e}/publishing
  → PublishEncyclopedia action (transaction)
     → validate content
     → SnapshotBuilder::forRelease() writes immutable JSON to storage
     → publications row (version++, theme + settings + counts)
     → encyclopedia.status=published, published_at
  → after commit: GenerateEncyclopediaExport (public archive)
```

### 3.4 Export
```
POST /author/{e}/exports → RequestExport
   → authorize + validate options
   → cache lookup (publication + theme + renderer + options)
   → create encyclopedia_exports row (queued)
   → dispatch GenerateEncyclopediaExport on "exports" queue
Worker
   → mark processing → load snapshot → build context
   → render pages with Blade → write CSS/JS/search/sitemap/README
   → collect + copy media → rewrite URLs → validate links/assets
   → ZIP → checksum/size → store → mark completed
   → broadcast/poll status to UI
```

## 4. Why these choices

| Decision | Rationale |
| --- | --- |
| Modular monolith over microservices | One deploy, transactional consistency, low operational cost; boundaries are explicit and extractable later. |
| Blade themes shared by reader and exporter | One template implementation avoids online/offline drift; the highest-risk requirement is visual parity. |
| Inertia + Vue for authoring | SPA-quality editor without duplicating an API; server-side authorization stays authoritative. |
| JSON content source of truth | Enables multiple themes and future formats; HTML is disposable output. |
| Immutable snapshots | Reproducible exports, stable archives and safe caching. |
| PostgreSQL JSONB | Structured documents + relational integrity + full-text search in one store. |
| Redis queues | Export work is bursty and must never block web requests. |
| S3-compatible storage | Media and archives outlive application containers and can be CDN-fronted. |

## 5. Version matrix (as implemented)

The brief targets "Laravel 13 / Inertia 3 / Tailwind 4". The current official
`laravel/vue-starter-kit` v1.0.2 pins Laravel 12, Inertia 2 and Tailwind 3.4. The
implementation follows the starter kit's supported stack to avoid an unsupported
combination. This deviation is recorded in the roadmap.

| Layer | Documented target | Implemented | Note |
| --- | --- | --- | --- |
| PHP | 8.3+ | 8.5 runtime, requires ^8.2 | Laravel 12 supports it |
| Laravel | 13 | 12.72 | Latest starter-kit-supported major |
| Inertia | 3 | 2 | Starter kit pin |
| Vue | 3 | 3.5 | Same |
| TypeScript | — | 5.x | Same |
| Tailwind | 4 | 3.4 | Starter kit pin; utilities + CSS variables |
| shadcn-vue | — | radix-vue primitives | Starter kit components, extended locally |
| Editor | Tiptap | Tiptap 3 | JSON storage |
| Database | PostgreSQL | PostgreSQL 18 | JSONB, FTS |
| Cache/queue | Redis | Redis (database fallback in tests) | Configurable |
| Package manager | pnpm | pnpm 10 (pinned) | `packageManager` field |
| Tests | Pest/PHPUnit + Vitest | PHPUnit + Vitest | Starter kit defaults |

## 6. Cross-cutting concerns

- **Authorization** — policies per aggregate; tenant checks resolve the encyclopedia from
  the route and verify membership; never trust nested IDs.
- **Transactions** — writes that span aggregates (publish, reorder, revision restore) run in
  a transaction; jobs dispatched `afterCommit`.
- **Caching** — reader pages keyed by `publication version + theme version`; theme CSS
  keyed by theme + settings hash; exports keyed by artifact cache key.
- **Observability** — structured logs with encyclopedia/export IDs; queued job failures
  surface on the export screen with retry; Laravel Pulse/Sentry in production.
- **Security** — see doc 08; HTML is sanitised at write and render time; exports are
  sandboxed to generated filenames.

## 7. Scaling path

The monolith scales horizontally behind a load balancer. Sessions/cache/queues live in
Redis; media and archives in object storage; exports run on dedicated workers
(`exports` queue). PostgreSQL read replicas can serve reader traffic. The theme renderer is
stateless, so export workers scale independently. Custom domains would be added as a
routing lookup before the reader controller, not by forking the content model.
