# 04 — Content Model and Editor

## 1. Principles

- The editor document (Tiptap JSON) is the **source of truth**.
- Rendering to HTML is a pure, server-side, allow-listed transformation.
- Unknown nodes are never rendered as raw HTML; they are dropped and logged.
- Every document carries `content_schema_version` so block changes can be migrated.
- HTML is sanitised at write time **and** at render time (defence in depth).

## 2. Document envelope

```json
{
  "type": "doc",
  "attrs": { "schema": 1 },
  "content": [ { "type": "paragraph", "content": [ ... ] } ]
}
```

Allowed top-level nodes: `paragraph`, `heading`, `bulletList`, `orderedList`, `blockquote`,
`codeBlock`, `horizontalRule`, `image`, `gallery`, `table`, `callout`, `citation`,
`timeline`.

## 3. Node reference

| Node | Attrs | Notes |
| --- | --- | --- |
| `paragraph` | — | |
| `heading` | `level` 1–4 | H1 is reserved for the article title in themes; authoring uses H2–H4 by default. |
| `text` | marks | Marks: `bold`, `italic`, `underline`, `strike`, `code`, `link`, `highlight` |
| `bulletList` / `orderedList` / `listItem` | — | nestable |
| `blockquote` | — | |
| `codeBlock` | `language` | rendered `<pre><code class="language-x">` |
| `horizontalRule` | — | |
| `image` | `mediaId`, `src`, `alt`, `caption`, `width` | `mediaId` preferred; `src` is a fallback route. |
| `gallery` | `images[]`, `layout` (`grid`,`strip`) | each image has `mediaId`, `alt`, `caption` |
| `table` / `tableRow` / `tableHeader` / `tableCell` | `colspan`, `rowspan` | header cells render `<th scope="col">` |
| `callout` | `tone` (`note`,`tip`,`warning`,`important`), `title` | |
| `citation` | `source`, `url`, `accessed` | bibliography entry |
| `timeline` | `items[]` (`date`,`title`,`body`) | chronology block |

Rationale: these cover the brief's block list without introducing arbitrary HTML. Videos and
audio are represented by `image`-like media embeds in a later schema version.

## 4. Rendering pipeline

```
article.content (JSONB)
   → ContentValidator   (schema + attribute allow-list, size limits)
   → ContentHtmlRenderer (deterministic HTML, escaped)
   → LinkRewriter        (internal links, media URLs; export-relative mode)
   → Blade theme
```

`ContentHtmlRenderer` is framework-independent PHP and is covered by unit tests. It:
1. Rejects docs larger than 2 MB or deeper than 40 levels.
2. Escapes all text with `htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE)`.
3. Allows only `http`, `https`, `mailto` and same-site relative URLs for links.
4. Resolves images through `MediaUrlResolver`, never by trusting `src`.
5. Emits semantic HTML (`<figure>`, `<figcaption>`, `<blockquote>`, `<table>`).
6. Adds `loading="lazy"` and `decoding="async"` to non-featured images.

## 5. Sanitisation rules

| Vector | Control |
| --- | --- |
| Script tags | Not a node type; raw HTML is not stored or rendered. |
| Event handlers | Not representable. |
| `javascript:` / `data:` URLs | Scheme allow-list. |
| SVG / HTML uploads | Rejected; MIME + extension + image header validation. |
| Remote embeds | Not in schema v1; future embeds use an approved provider allow-list. |
| Oversized docs | 2 MB cap; 500 image nodes per article. |
| Formula injection in exports | CSV not generated; HTML is escaped. |

## 6. Editor UX

Three regions, matching the reference layout:

1. **Left rail** — document outline generated from headings, plus article metadata
   (topic, status, SEO fields, revision).
2. **Canvas** — title, subtitle/excerpt, Tiptap content, floating block toolbar, slash menu
   for inserting blocks.
3. **Right properties panel** — status, featured image, excerpt, publication controls,
   preview and save actions.

### Toolbar
Headings 2–4, bold, italic, underline, strike, inline code, link, bullet/ordered/task list,
quote, code block, table, image, gallery, callout, citation, divider, undo/redo.

### Media insertion
Image and gallery blocks open the **media picker**: a searchable grid of the encyclopedia's
library, with upload dropzone, alt/caption fields and a "use" action. The inserted node keeps
`mediaId`; the URL is resolved at render time.

### Autosave and concurrency
- Debounced 1.2 s after last change; also on blur and every 20 s while dirty.
- `PATCH /author/{e}/articles/{a}/autosave` sends `{ title, excerpt, content, revision_number }`.
- Server compares `revision_number`; mismatch → `409` with the current revision and a merge
  prompt. Successful save bumps the revision and returns it.
- The latest draft is mirrored to `localStorage` (`alarife:draft:{id}`) every 5 s; on load, if
  the local revision is newer than the server revision, the author is offered recovery.
- The UI exposes `Saving…`, `Saved 14:32`, `Offline — will retry`, `Conflict`.

### Save vs autosave
| Action | Revision row | Status change |
| --- | --- | --- |
| Autosave | No (coalesced) | No |
| Save draft | Yes | stays draft |
| Submit for review | Yes | draft → in_review |
| Publish | Yes | in_review/draft → published |
| Restore | Yes (new revision) | stays current |

## 7. Revision history

Every explicit save stores `(title, excerpt, content, revision_number, editor_id)`. The UI
shows a timeline with author, time, word count and a diff summary (added/removed blocks).
Restoring creates a **new** revision from the old content rather than rewinding the counter,
so history is append-only.

## 8. Internal links and slugs

- The link dialog offers both external URLs and internal article/topic search.
- Internal hrefs are stored as site-relative paths (`/ancient-civilizations/articles/x`).
- The exporter rewrites them to relative paths (`../../articles/x/index.html`).
- Renaming a published article slug writes an `article_redirects` row; the reader returns a
  301 to the new URL and the exporter emits a meta-refresh page at the old path.

## 9. Seed document example

```json
{
  "type": "doc",
  "content": [
    { "type": "heading", "attrs": { "level": 2 }, "content": [{ "type": "text", "text": "Origins" }] },
    { "type": "paragraph", "content": [{ "type": "text", "text": "Settlement began along the Nile." }] },
    { "type": "image", "attrs": { "mediaId": 12, "alt": "Nile at dusk", "caption": "The Nile floodplain" } },
    { "type": "callout", "attrs": { "tone": "note", "title": "Dating" },
      "content": [{ "type": "paragraph", "content": [{ "type": "text", "text": "Dates follow the conventional chronology." }] }] }
  ]
}
```
