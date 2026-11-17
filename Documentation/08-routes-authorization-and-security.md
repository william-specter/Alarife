# 08 — Routes, Authorization and Security

## 1. Route map

### Authoring (auth + verified middleware, Inertia)
| Method | URI | Name | Ability |
| --- | --- | --- | --- |
| GET | `/dashboard` | dashboard | member |
| GET | `/encyclopedias/create` | encyclopedias.create | create |
| POST | `/encyclopedias` | encyclopedias.store | create |
| GET | `/author/{encyclopedia}/overview` | author.overview | view |
| GET/PUT | `/author/{encyclopedia}/settings` | author.settings.* | update |
| DELETE | `/author/{encyclopedia}/encyclopedia` | author.encyclopedias.destroy | delete |
| GET | `/author/{encyclopedia}/covers` | author.covers.index | update |
| POST | `/author/{encyclopedia}/covers` | author.covers.store | update |
| PUT | `/author/{encyclopedia}/covers/reorder` | author.covers.reorder | update |
| DELETE | `/author/{encyclopedia}/covers/{cover}` | author.covers.destroy | update |
| GET | `/author/{encyclopedia}/topics` | author.topics.index | view |
| POST | `/author/{encyclopedia}/topics` | author.topics.store | manageContent |
| PUT | `/author/{encyclopedia}/topics/{topic}` | author.topics.update | manageContent |
| DELETE | `/author/{encyclopedia}/topics/{topic}` | author.topics.destroy | manageContent |
| PUT | `/author/{encyclopedia}/topics/reorder` | author.topics.reorder | manageContent |
| GET | `/author/{encyclopedia}/articles` | author.articles.index | view |
| POST | `/author/{encyclopedia}/articles` | author.articles.store | write |
| GET | `/author/{encyclopedia}/articles/{article}/edit` | author.articles.edit | write |
| PUT | `/author/{encyclopedia}/articles/{article}` | author.articles.update | write |
| PATCH | `/author/{encyclopedia}/articles/{article}/autosave` | author.articles.autosave | write |
| DELETE | `/author/{encyclopedia}/articles/{article}` | author.articles.destroy | delete |
| POST | `/author/{encyclopedia}/articles/{article}/status` | author.articles.status | publish* |
| GET | `/author/{encyclopedia}/articles/{article}/revisions` | author.articles.revisions | write |
| POST | `/author/{encyclopedia}/articles/{article}/revisions/{revision}/restore` | author.articles.restore | write |
| POST | `/author/{encyclopedia}/articles/{article}/duplicate` | author.articles.duplicate | write |
| GET | `/author/references/search` | author.references.search | any authenticated author |
| GET | `/author/{encyclopedia}/media` | author.media.index | view |
| POST | `/author/{encyclopedia}/media` | author.media.store | write |
| PUT | `/author/{encyclopedia}/media/{media}` | author.media.update | write |
| DELETE | `/author/{encyclopedia}/media/{media}` | author.media.destroy | write |
| GET | `/author/{encyclopedia}/appearance` | author.appearance.index | update |
| PUT | `/author/{encyclopedia}/appearance` | author.appearance.update | update |
| GET | `/author/{encyclopedia}/preview` | author.preview | view |
| GET | `/author/{encyclopedia}/publishing` | author.publishing.index | view |
| POST | `/author/{encyclopedia}/publishing` | author.publishing.store | publish |
| POST | `/author/{encyclopedia}/publishing/unpublish` | author.publishing.unpublish | publish |
| GET | `/author/{encyclopedia}/exports` | author.exports.index | view |
| POST | `/author/{encyclopedia}/exports` | author.exports.store | export |
| GET | `/author/{encyclopedia}/exports/{export}/status` | author.exports.status | view |
| GET | `/author/{encyclopedia}/exports/{export}/download` | author.exports.download | export |

`*` Article status changes require `publish` for published/archived transitions; authors may
create drafts and submit for review.

### Reader (public)
| Method | URI | Name |
| --- | --- | --- |
| GET | `/{encyclopedia:slug}` | reader.home |
| GET | `/{encyclopedia:slug}/topics/{topic:slug}` | reader.topic |
| GET | `/{encyclopedia:slug}/articles/{article:slug}` | reader.article |
| GET | `/{encyclopedia:slug}/search` | reader.search |
| GET | `/{encyclopedia:slug}/download` | reader.download |
| GET | `/themes/{theme}.css` | themes.css |

Reserved top-level slugs (validated on create/update): `dashboard`, `login`, `register`,
`logout`, `forgot-password`, `reset-password`, `verify-email`, `confirm-password`,
`password`, `settings`, `author`, `encyclopedias`, `themes`, `storage`, `build`, `api`,
`up`, `admin`.

Access rules:
- `public`: visible to everyone, indexable.
- `unlisted`: visible by direct link, `noindex`.
- `private`: only members; reader routes return 404 to everyone else. `archived` behaves like
  private.
- Draft articles return 404 even on a published encyclopedia.

## 1.1 Broadcasting endpoints

| Method | URI | Name | Access |
| --- | --- | --- | --- |
| POST | `/broadcasting/auth` | broadcasting.auth | private channel authorization |

Channels are authorized server-side in `routes/channels.php`:

- `private-encyclopedia.{id}`: owner/editor/author member or platform admin.
- `private-user.{id}`: the authenticated user only.

Realtime events only contain IDs and safe metadata, never article bodies or private asset
URLs. If Reverb is unavailable, broadcasting failures are logged and writes still succeed.

## 2. Authorization model

Laravel policies + `Gate::before` for platform admins.

| Ability | Owner | Editor | Author | Reader |
| --- | --- | --- | --- | --- |
| view | ✓ | ✓ | ✓ | public only |
| update settings | ✓ | ✓ | — | — |
| manage covers | ✓ | ✓ | — | — |
| manageContent (topics) | ✓ | ✓ | — | — |
| write (articles) | ✓ | ✓ | ✓ | — |
| delete articles | ✓ | ✓ | own drafts | — |
| publish | ✓ | ✓ | — | — |
| export draft | ✓ | ✓ | ✓ (if enabled) | — |
| export release | ✓ | ✓ | ✓ | if enabled |
| manage members | ✓ | — | — | — |
| delete encyclopedia | ✓ | — | — | — |

Implementation notes:
- `EncyclopediaPolicy` resolves membership with a single query; results are memoised per
  request.
- Nested resources are validated as children of the route encyclopedia (`scopeBindings` or
  explicit `where('encyclopedia_id', ...)` lookups) to prevent cross-tenant IDOR.
- Controllers call `$this->authorize(...)`; FormRequests do not grant access on their own.
- Frontend hiding is cosmetic only; every mutation re-checks server-side.

## 3. Threat model and controls

| Threat | Control |
| --- | --- |
| IDOR across encyclopedias | route-model binding constrained to the encyclopedia; policy checks; feature tests with two tenants |
| Cross-reference to private/unpublished content | server validates target encyclopedia visibility/status and target article/topic status; snapshots pin only readable targets |
| XSS via editor/link | allow-listed JSON schema; escaped renderer; URL scheme allow-list; CSP on reader pages |
| Stored XSS via media SVG | SVG uploads rejected; MIME sniffing + image validation; served with `X-Content-Type-Options: nosniff` |
| Malicious ZIP entries | generated filenames only; traversal check on every entry; no user-supplied paths |
| Export data leak | exports authorized by policy; reader downloads resolve only the current public release; random archive names |
| CSRF | Laravel CSRF tokens; Inertia sends `X-XSRF-TOKEN` |
| Brute force | auth rate limiting; export/upload rate limits (`throttle:exports`, `throttle:media`) |
| File upload bombs | max 10 MB/image, 20 images/request, pixel-dimension cap, queued variant generation |
| SSRF | no server-side fetch of author URLs in v1 |
| Path traversal | slug validation `^[a-z0-9]+(?:-[a-z0-9]+)*$`; reserved words; storage paths generated |
| Snapshot tampering | snapshots written to private disk; read-only; checksummed archives |
| Race on revision | optimistic lock + `409`, transaction on save |
| Queue flooding | unique export jobs + artifact cache + per-encyclopedia concurrency cap |

## 4. Headers and content security

Reader responses set:
```
Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
```
Exports cannot set headers; their HTML uses no inline event handlers and no external
resources. Download responses use `Content-Disposition: attachment` and `X-Content-Type-Options`.

## 5. Validation rules (representative)

- Encyclopedia slug: required, lowercase, 3–120, regex above, reserved list, unique.
- Theme settings: schema-validated; unknown keys rejected; colours hex; numeric ranges.
- Article: title 3–200; slug unique per encyclopedia; excerpt ≤ 500; content JSON ≤ 2 MB
  after validation; image count ≤ 500.
- Topic: title 2–160; parent must be root and same encyclopedia; max depth 2.
- Covers: max 3; media must belong to the encyclopedia; positions 1–3 unique.
- Media: `image/jpeg|png|webp|avif` only; ≤ 10 MB; min 200×200; max 8000×8000.
- Export options: enum/boolean only; source=draft requires `export` ability.

## 6. Audit and observability

`activity_logs` records: encyclopedia created/updated/deleted, cover changes, topic changes,
article created/saved/published/restored/deleted, theme changed, release published/unpublished,
export requested/completed/failed, member changes. Logs include user, encyclopedia, subject and
a small metadata payload (no article bodies). Sensitive values (tokens, passwords) never logged.

## 7. Rate limits

| Action | Limit |
| --- | --- |
| Login/register | Laravel defaults + IP throttle |
| Media upload | 30/min/user |
| Autosave | 120/min/user/article (soft) |
| Export request | 5/hour/encyclopedia, 20/day/user |
| Reader search | 60/min/IP |
| Download | 10/min/IP |
