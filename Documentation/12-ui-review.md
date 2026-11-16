# 12 — UI/UX Review Log (Playwright)

The interface was built against the references in `Docs/examples/` and reviewed in a real
browser with Playwright at desktop (1440×1000) and mobile (390×844) widths. This log records
what was compared, the issues found, and how they were resolved.

## Reference → implementation mapping

| Reference | Implemented screen |
| --- | --- |
| `main-dashboard.png` | Author dashboard with collection cards, statistics and activity rail |
| `enciclopedia-management-workspace.png` | Workspace overview, topics, articles and settings |
| `editor-interface.png` | Three-pane Tiptap editor with outline and properties |
| `cover-image-carousel.png` | Reader home hero with three-cover carousel and pause/arrow controls |
| `article-reader-experience.png` | Reader article with breadcrumbs, TOC, body, citations and related reading |
| `static-export-interface.png` | Export screen with source/options and background progress |
| `initial template library/*` | Theme gallery and five reader themes |

## Review findings and fixes

| Observation | Resolution |
| --- | --- |
| Theme cards were selectable but the live preview stayed on the saved theme | Preview iframe is re-rendered from uncommitted settings via `POST preview/frame` with a debounce |
| Workspace sidebar showed an empty theme/status chip on some pages | Every workspace controller now supplies `status` and `theme_name`; layout hides absent values |
| Platform admin saw only owned encyclopedias | `User::accessibleEncyclopedias()` returns all works for platform admins; policy `before` already allowed access |
| Editor crashed on seeded gallery/image nodes (`Invalid array passed to renderSpec`) | Editor API hydrates media URLs into image/gallery nodes; custom node views render safe DOM and fall back gracefully |
| Toolbar did not appear on first load | Editor emits a `ready` event; the page binds the toolbar only when the TipTap instance exists |
| Static export images broke at depth (missing `../../`) | Media map is resolved per page with its `assetBase`; search page uses `../` |
| Offline search returned no results | Search index is written as a JavaScript global (no `fetch` under `file://`) and the page runs prefix scoring |
| Theme colours were partly overridden by base defaults | Tokens are appended after base/theme CSS; stylesheet cache keys include file hashes and a CSS version |
| Surface colours inverted on dark themes | Fixed colour mixing so surfaces derive from the background, not the text colour |
| CSP blocked preview images/thumbnails when `APP_URL` host differed | `APP_URL` must match the serving host; verified with `127.0.0.1` |
| Mobile reader navigation was unreachable | Header menu toggle, focus return, and escape handling verified at 390 px |
| Reader download button produced no file | Verified streaming download of the current release (`ancient-civilizations.zip`) |
| Export cards remained “queued” in the UI | Client polls `/status` and watches refreshed Inertia props |
| Cross-encyclopedia references needed a safe authoring flow | Shared picker searches published works only; server re-validates every target and snapshots pin the selection |
| Draft content could accidentally trigger a draft export from the public download | Reader download requires a latest publication; `RequestExport(public: true)` fails without one |

## Verification flows exercised

1. Sign in → dashboard (desktop + mobile).
2. Workspace overview → editor → type content → wait for autosave → confirm saved state.
3. Appearance → change token → preview updates inside the sandboxed iframe.
4. Reader home → carousel → article → search → download.
5. Export → completed archive → extract → open from `file://` → carousel, article and
   offline search (`?q=pyramid`) → no console errors.
6. Mobile reader → open navigation menu.

## Screenshots

| Screen | File |
| --- | --- |
| Author dashboard | [`screenshots/admin-dashboard.png`](screenshots/admin-dashboard.png) |
| Workspace overview | [`screenshots/admin-overview.png`](screenshots/admin-overview.png) |
| Article editor | [`screenshots/admin-editor.png`](screenshots/admin-editor.png) |
| Appearance / live preview | [`screenshots/admin-appearance.png`](screenshots/admin-appearance.png) |
| Reader article (Editorial) | [`screenshots/reader-editorial-article.png`](screenshots/reader-editorial-article.png) |
| Reader home (Atlas) | [`screenshots/reader-atlas-home.png`](screenshots/reader-atlas-home.png) |
| Reader home (Nocturne) | [`screenshots/reader-nocturne-home.png`](screenshots/reader-nocturne-home.png) |
| Offline export, opened from disk | [`screenshots/export-offline-home.png`](screenshots/export-offline-home.png) |
| Dashboard (mobile) | [`screenshots/admin-dashboard-mobile.png`](screenshots/admin-dashboard-mobile.png) |
| Cross-references in the creation wizard | [`screenshots/admin-create-cross-references.png`](screenshots/admin-create-cross-references.png) |
| Cross-references in settings | [`screenshots/admin-cross-references-settings.png`](screenshots/admin-cross-references-settings.png) |
| Reader related works | [`screenshots/reader-cross-references.png`](screenshots/reader-cross-references.png) |

## Remaining UX improvements

- Member invitation management (schema/permissions exist; no screen yet).
- Richer revision diff (current view lists revisions with word counts).
- Image variant generation and `srcset` in exports.
- Scheduled publication control.
