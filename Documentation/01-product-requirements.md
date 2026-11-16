# 01 — Product Requirements

## 1. Vision

Alarife lets a small editorial team run **many independent encyclopedias** from one
workspace, publish them as continuously updated websites, and distribute them as
**complete offline websites**. Content is authored once in a structured format and rendered
through interchangeable themes; it is never duplicated for print, web or export.

## 2. Actors

| Actor | Description | Primary surface |
| --- | --- | --- |
| Guest | Anonymous visitor of a public encyclopedia | Reader |
| Reader | Signed-in user with no editorial role | Reader, downloads |
| Author | Writes articles for assigned encyclopedias | Workspace, editor |
| Editor | Manages topics, media, appearance and publishing | Workspace |
| Owner | Full control of an encyclopedia, including deletion and members | Workspace, settings |
| Platform admin | Operates the installation; can see and moderate all encyclopedias | Admin dashboard |

A user may hold different roles in different encyclopedias. The platform role is separate
from encyclopedia membership.

## 3. Capability map

### 3.1 Encyclopedia management
- Create any number of encyclopedias, each with title, subtitle, slug, description,
  language, visibility (`private`, `unlisted`, `public`) and lifecycle status.
- Up to **three cover images** per encyclopedia, ordered and captioned, with three layouts:
  full-width hero, editorial carousel, card carousel.
- Choose and configure a theme per encyclopedia without touching content.
- Duplicate an encyclopedia as a starting point (content + settings, new slug).
- Delete with explicit confirmation; soft-deleted encyclopedias stop resolving publicly.

### 3.2 Content organisation
- Topics form a two-level hierarchy (root + children) per encyclopedia.
- Articles belong to one primary topic; an article may be moved between topics.
- Ordering of topics and articles is explicit (`sort_order`) and editable.
- Slugs are unique inside an encyclopedia; changing a published slug creates a redirect.
- Encyclopedias can reference published articles and topics from other published works;
  references appear as related works and are pinned into releases.

### 3.3 Authoring
- Structured WYSIWYG editor (Tiptap) with headings, lists, quotes, code, tables, images,
  galleries, callouts, links, dividers and citations.
- Media library per encyclopedia with alt text, captions and safe storage.
- Autosave with visible state, optimistic concurrency via `revision_number`, and local
  crash recovery.
- Article revision history, comparison and restore.
- Draft → in review → published workflow, scheduled publishing, unpublish.
- Server-side schema validation and HTML sanitisation on every write and render.

### 3.4 Presentation
- Five initial themes, each a first-class, versioned package: **Classic Reference**,
  **Modern Minimalist**, **Editorial Magazine**, **Illustrated Atlas**, **Contemporary Dark**.
- Theme settings validated against a schema (colours, typography, layout, carousel,
  accessibility). No arbitrary author CSS/JS is executed.
- Live preview inside the designer and full-screen preview of draft content for members.
- Reader pages: home, topic, article, search, 404; responsive, keyboard accessible,
  printable, reduced-motion aware, WCAG 2.2 AA target.

### 3.5 Publishing
- Publication = immutable, versioned snapshot of published content + theme + settings.
- Publishing increments the release version and triggers a public export.
- History of releases; any release can be previewed and re-exported.
- Draft content is never exposed to readers or public archives.

### 3.6 Static export
- Generate a ZIP containing HTML, CSS, JS, all referenced media, search index, sitemap,
  robots file and README.
- Works from `file://` with no server, no remote fonts, no remote scripts, no APIs.
- Offline search via a JavaScript index (no `fetch()` of JSON).
- Author exports (draft or release) and reader downloads (release only, when enabled).
- Generated in background jobs with progress, checksum, retry and cached artifacts.
- Automatically validates internal links and referenced assets before packaging.

## 4. Non-negotiable requirements

1. **Content is independent of design.** Switching themes never mutates articles.
2. **Exports are genuine websites.** No Laravel, database, API, CDN or remote font needed.
3. **Snapshots are immutable.** A publication always resolves to one fixed content set.
4. **Editor content is structured.** JSON is the source of truth; HTML is derived.
5. **Author and reader capabilities are separate.** Readers can never obtain drafts or
   private assets through the export system.

## 5. Primary user journeys

### Create and publish an encyclopedia
1. Sign in → dashboard → *New encyclopedia*.
2. Wizard: identity → covers → theme → first topic → save draft.
3. Add topics, write articles in the editor, insert media.
4. Preview the site; adjust theme tokens and carousel style.
5. Publish release v1 → public site live → static archive generated.
6. Share link and/or download ZIP.

### Revise a published encyclopedia
1. Edit an article (autosave).
2. Publish the article; preview draft.
3. Publish release v2; previous release remains in history and its archive remains valid.
4. Reader downloads always point to the current release.

### Distribute offline
1. Author opens *Export* → chooses draft or release, options, sees estimate.
2. Job renders theme pages, collects assets, builds search index, validates links, zips.
3. Author downloads ZIP, extracts it, opens `index.html`, uses search and carousel offline.
4. Optionally uploads the folder to any static host.

## 6. Acceptance criteria (release gates)

| # | Criterion |
| --- | --- |
| AC-01 | A user can create and manage multiple independent encyclopedias. |
| AC-02 | An encyclopedia rejects a fourth cover image at validation **and** database level. |
| AC-03 | Every encyclopedia has its own theme and settings; switching preserves content. |
| AC-04 | Articles retain formatting and media after save, reload and revision restore. |
| AC-05 | Draft articles return 404 to readers; published articles render on the selected theme. |
| AC-06 | Publication snapshots are immutable and versioned; exports reference a snapshot. |
| AC-07 | Extracted exports open from `file://` with all pages, styles, scripts and images. |
| AC-08 | Offline search returns results without a server or network request. |
| AC-09 | Export visually matches the active theme (tokens, layout, carousel). |
| AC-10 | Authorization is enforced server-side for every mutating route. |
| AC-11 | Editors/authors cannot escape their encyclopedia tenant via IDs in requests. |
| AC-12 | Export archives have a SHA-256 checksum and are reproducible from a snapshot. |
| AC-13 | Cross-references only accept published targets in readable encyclopedias and are pinned in snapshots. |

## 7. Out of scope for the first release

PDF, EPUB, Markdown and JSON exports (architecture leaves room); real-time co-editing;
custom domains with automatic TLS; public API; mobile apps; AI-assisted writing; commerce.

## 8. Success metrics

- Time from "new encyclopedia" to first published article < 10 minutes.
- Export success rate ≥ 99% for encyclopedias under 1,000 articles.
- Reader LCP < 2.5 s on a mid-range mobile device for article pages.
- Zero high-severity findings in the security review of uploads, rendering and exports.
