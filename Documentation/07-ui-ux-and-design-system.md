# 07 — UI/UX and Design System

## 1. Product design language

Alarife should feel like a **publisher's desk**, not a generic admin panel: warm paper,
ink-dark type, restrained terracotta accent, generous whitespace, and typographic hierarchy.
The authoring UI is calm and functional; reader themes are expressive and distinct.

Reference material: `Docs/examples/` (dashboard, workspace, editor, reader, carousel,
themes, export dialog).

### Brand tokens (authoring UI)

| Token | Value | Usage |
| --- | --- | --- |
| `--ink` | `#1b1712` | primary text, dark buttons |
| `--ink-soft` | `#4a4239` | secondary text |
| `--paper` | `#faf7f1` | app background |
| `--surface` | `#ffffff` | cards, panels |
| `--line` | `#e7e0d4` | borders, dividers |
| `--clay` | `#b4512e` | accent, active nav, focus |
| `--clay-soft` | `#f3e3da` | accent tint |
| `--gold` | `#c2943c` | publishing/highlights |
| `--sage` | `#5d6d5a` | success |
| `--danger` | `#a3372c` | destructive |
| `--radius` | `10px` | cards (6px controls) |

Type: **Instrument Sans** for UI (starter kit), **Fraunces/Georgia** fallback for editorial
display moments. Numerals tabular in stats. Chinese/Japanese/Cyrillic fallback through system
stacks.

Spacing scale: 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64. Grid: 12-column, 1280 max content in the
workspace, fluid below 1024. Shadows are soft and warm (`0 1px 2px rgba(27,23,18,.06)`).

### Surface hierarchy
- App background: paper.
- Sidebar: slightly deeper paper with a hairline border.
- Content: white cards on paper; 1px borders; 10px radius; no heavy shadows.
- Active state: ink pill (primary nav) or clay underline/tint (tabs).
- Status colours: draft = stone, in review = gold, published = sage, failed = danger.

## 2. Information architecture

```
/dashboard                         All encyclopedias + activity
/author/{e}/overview               Workspace home
/author/{e}/settings               Identity, visibility, slug, export flag, danger zone
/author/{e}/covers                 Up to 3 covers, order, captions, style
/author/{e}/topics                 Tree + inline editor
/author/{e}/articles               Table with filters
/author/{e}/articles/{a}/edit      Three-pane editor
/author/{e}/media                  Library grid + details
/author/{e}/appearance             Theme library + settings + live preview
/author/{e}/publishing             Releases, readiness, publish/unpublish
/author/{e}/exports                History, request, progress, download
```

The workspace sidebar keeps the encyclopedia context visible at all times (title, status,
template) and provides a "View site" action.

## 3. Key screens

### 3.1 Dashboard
- Header: "Your encyclopedias", search, primary **New encyclopedia**.
- Aggregate strip: encyclopedias, articles published, drafts in review, exports ready.
- Cards: cover thumbnail, title, subtitle, status badge, theme chip, counts, last edited,
  overflow menu (open, preview, export, delete).
- Recent activity rail from `activity_logs`.
- Empty state with a strong call to action and a short product explanation.

### 3.2 Creation wizard
Three steps with a persistent summary: **Identity** (title, subtitle, slug, language,
description, visibility) → **Covers** (dropzone, up to 3, captions) → **Design** (theme
cards, cover style, accent). "Skip for now" everywhere; final action creates the draft and
routes to the workspace. Inline slug preview `alarife.test/{slug}`.

### 3.3 Workspace overview
- Editorial hero: cover strip, title, status, primary actions (Write article, Preview,
  Publish).
- Readiness checklist (theme selected, at least one topic, one published article, cover).
- Stats: topics, articles, drafts, words.
- Recent articles table and release card.

### 3.4 Topics
Split view: tree on the left (drag handle, nesting, counts), editor on the right (title,
slug, description, parent, status, order). Create inline. Delete requires confirmation and
shows affected article count.

### 3.5 Articles index
Table: title + slug, topic, status badge, words, updated, author. Filters: status, topic,
search. Bulk actions limited to status changes the user may perform. Row menu: edit,
preview, duplicate, archive, delete.

### 3.6 Editor (signature screen)
Reference: `Docs/examples/editor-interface.png`.
- Top bar: breadcrumb, article title (editable inline), save state, **Preview**, split
  **Save draft / Publish**.
- Left: outline (H2–H4) + document stats.
- Center: title, excerpt, Tiptap canvas with comfortable 68ch measure, floating toolbar on
  selection, slash menu on empty line.
- Right: tabs *Details* (topic, status, featured image, slug, SEO) / *History* (revisions).
- Media picker as a dialog with library grid and upload.
- Conflict banner is persistent and actionable (reload / keep mine as copy).

### 3.7 Appearance
- Theme gallery cards (reference previews) with active state and an inline live renderer below.
- Settings panel grouped by Colors, Typography, Layout, Carousel, Accessibility.
- Large live preview iframe with device toggle (desktop/tablet/mobile) and page toggle
  (home/topic/article).
- Sticky footer: reset to theme defaults, discard, **Save theme**.

### 3.8 Publishing
- Readiness panel with blocking vs warning items.
- Release form (notes), publish/unpublish, scheduled date.
- Release timeline with version, date, counts, theme version, archive status/download.

### 3.9 Exports
- Explain what the archive contains (offline promise).
- Source (current release / draft), options toggles, estimated pages and media count.
- Export list: status, progress bar, size, checksum, created by, download, retry.
- Reader-download toggle is a separate, clearly labelled setting.

### 3.10 Reader
Home: cover carousel (1–3 images; controls only when >1), title/description, featured topics,
recent articles, topic index, cross-encyclopedia related works, search, footer with release +
download.
Topic: breadcrumb, intro, child topics, article cards.
Article: breadcrumb, title, meta (topic, author, reading time, published), featured image,
auto TOC, structured body, citations, related articles, prev/next, print styles.
Search: query field, result count, highlighted excerpts, keyboard support.

## 4. Interaction details

- Every destructive action uses a dialog with the resource name and consequences.
- Buttons show optimistic pending state and disable during submission.
- Toasts: success (sage), error (danger) with retry when safe.
- Keyboard: `/` focuses search in workspace; `Cmd/Ctrl+S` saves editor; `Cmd/Ctrl+Enter`
  publishes draft in editor; `Esc` closes overlays; visible focus rings everywhere.
- Drag & drop: covers reorder, topic reorder, editor block move; always provide button
  alternatives.
- Empty states explain the next action, never just "No data".

## 5. Accessibility (WCAG 2.2 AA target)

- Semantic landmarks, one H1 per page, logical heading order.
- Contrast ≥ 4.5:1 for body text, ≥ 3:1 for large text/UI; theme settings validation warns.
- All controls reachable by keyboard; focus visible (`outline: 2px solid var(--clay)`).
- Carousel: pause control, no autoplay by default, respects reduced motion, arrow-key and
  swipe support, slide announcements via `aria-live="polite"`.
- TOC is a `nav` with `aria-label="Table of contents"`; current section indicated.
- Images require alt text in the media picker (decorative allowed via empty alt + label).
- Forms: labels, descriptions, inline errors tied via `aria-describedby`; no colour-only
  status.
- Print: reader pages print with navigation removed and URLs preserved.

## 6. Responsive rules

| Breakpoint | Workspace | Reader |
| --- | --- | --- |
| < 768 | Drawer sidebar; single column; editor panels become tabs | Single column; carousel touch; collapsible TOC |
| 768–1024 | Collapsed icon sidebar; 2-column editor | 2-column cards; TOC collapsible |
| 1024–1440 | Full sidebar; 3-pane editor | Sidebar + content |
| > 1440 | Max-width 1440 centered | Content max 1200–1280 |

## 7. UI quality bar (reviewed with Playwright)

- No horizontal scroll at 360 / 768 / 1280 / 1600.
- No overlapping text or clipped labels; buttons ≥ 36px tall, tap targets ≥ 44px on mobile.
- Skeletons for async panels; no layout shift when covers load.
- Screenshots are captured for every key screen in light mode; reader screens for each theme.
- Visual regression checks compare dashboard, editor, theme gallery and one reader article.

## 8. Component inventory (authoring UI)

`PageHeader`, `StatTile`, `EncyclopediaCard`, `StatusBadge`, `ThemeBadge`, `CoverUploader`,
`CoverCarouselEditor`, `TopicTree`, `ArticleTable`, `TiptapEditor`, `EditorToolbar`,
`OutlineNav`, `MediaPicker`, `MediaGrid`, `ThemeGallery`, `ThemeSettingsPanel`,
`CrossReferencePicker`, `PreviewFrame`, `ReleaseTimeline`, `ExportRow`, `ProgressBar`, `ConfirmDialog`, `Toast`,
`EmptyState`, `ReadinessList`, `Breadcrumbs`.

Components use shadcn-vue primitives where they exist and local compositions otherwise.
