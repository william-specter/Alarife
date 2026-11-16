# 05 — Theme System

## 1. Goals

- Themes are **packages**, not a pile of `v-if`s.
- Every theme renders the same normalised data contract.
- Theme settings are data, validated against a schema; authors cannot inject CSS/JS.
- Themes are versioned; exports pin the version so a later theme update never silently
  changes an old archive.
- One Blade implementation serves the live reader and the static exporter.

## 2. Package layout

```
resources/views/themes/{key}/
├── theme.json           # metadata, version, defaults, schema
├── theme.css            # theme-specific CSS (no remote imports)
├── layout.blade.php     # <html> shell, header/footer, tokens
├── home.blade.php
├── topic.blade.php
├── article.blade.php
├── search.blade.php
└── partials/
    ├── header.blade.php
    ├── footer.blade.php
    ├── cards.blade.php
    └── pagination.blade.php
```

`app/Domain/Themes/ThemeRegistry.php` loads `theme.json` manifests, validates settings and
returns a `Theme` value object (`key`, `name`, `version`, `description`, `defaults`,
`settingsSchema`, `preview`). The registry is source of truth for the Appearance UI.

## 3. Initial library

| Key | Name | Direction |
| --- | --- | --- |
| `classic` | Classic Reference | Dense serif reference work, compact nav, alphabetical index feel |
| `minimal` | Modern Minimalist | Spacious, mono-accented, strong grid, restrained chrome |
| `editorial` | Editorial Magazine | Oversized serif display, asymmetric covers, warm paper |
| `atlas` | Illustrated Atlas | Cartographic palette, map-like texture, sidebar legend |
| `nocturne` | Contemporary Dark | Deep navy/black, luminous accents, high contrast |

Each theme defines its own CSS variables and composition while consuming shared partials
for behaviour (carousel, search, pagination).

## 4. Settings contract

```json
{
  "template": "editorial",
  "version": "1.0.0",
  "settings": {
    "colors": { "primary": "#8f2f23", "accent": "#c49b51", "background": "#f6efe3", "text": "#211a15" },
    "typography": { "headingFont": "serif", "bodyFont": "sans", "baseSize": 18, "lineHeight": 1.7 },
    "layout": { "contentWidth": 1120, "sidebar": true, "density": "comfortable" },
    "carousel": { "autoplay": false, "transition": "fade", "showCaptions": true },
    "accessibility": { "reduceMotion": true, "contrast": "normal" }
  }
}
```

### Validation rules (shared + per theme)
| Group | Rule |
| --- | --- |
| colors | `#rrggbb`, contrast checked against text/background (warn + clamp) |
| typography | font key from theme's allowed stacks (`serif`, `sans`, `mono`); size 14–22; line-height 1.3–2.0 |
| layout | width 880–1400; density `compact`/`comfortable`/`spacious` |
| carousel | autoplay bool; transition `fade`/`slide`; max 3 covers |
| accessibility | reduceMotion bool; contrast `normal`/`high` |

Servers reject unknown keys and out-of-range values with 422. The Appearance UI mirrors the
schema to render controls and shows the same validation messages.

## 5. Token generation

`ThemeStylesheet` produces:

1. A CSS reset/base (box-sizing, typography scale, focus rings, print styles).
2. Theme CSS from `resources/views/themes/{key}/theme.css`.
3. Inline `:root` custom properties derived from validated settings:

```css
:root {
  --site-primary: #8f2f23;
  --site-accent: #c49b51;
  --site-bg: #f6efe3;
  --site-text: #211a15;
  --site-content-width: 1120px;
  --site-font-heading: Georgia, 'Times New Roman', serif;
  --site-font-body: ui-sans-serif, system-ui, sans-serif;
  --site-base-size: 18px;
  --site-line-height: 1.7;
  --site-radius: 2px;
}
```

Dynamic reader: `GET /themes/{key}.css` returns the composed CSS with a long cache header.
Static export: the same string is written to `assets/css/style.css`; `theme.css` in the
export contains only tokens + theme rules, never remote `@import`s.

## 6. Theme data contract

All themes receive the same `site` array from `SiteBuilder`:

```php
[
  'encyclopedia' => [id, title, subtitle, slug, description, language, visibility, cover_style, theme_key, published_at],
  'theme' => [key, name, version, settings, tokens],
  'covers' => [ [url, alt, caption, position], ... ],
  'navigation' => [ [title, slug, url, children[]], ... ],
  'featured_topics' => [...],
  'recent_articles' => [...],
  'page' => [...],          // page-specific payload
  'assetBase' => '',         // '' online, '../../' in export
  'static' => false,         // toggles links/assets
  'searchUrl' => '...',
  'downloadUrl' => '...' | null,
]
```

A theme must not query the database. It renders the context and calls `content_html()` for
article bodies.

## 7. Theme CSS and JS rules

- No remote fonts, CDNs, analytics or third-party scripts.
- System font stacks only (export-safe), with future optional self-hosted woff2.
- All interactive JavaScript is vanilla, progressively enhanced:
  carousel (buttons, dots, keyboard, swipe, autoplay pause), search, nav toggle,
  copy-link, print.
- CSS uses logical properties where practical and supports `prefers-reduced-motion`,
  `prefers-contrast`, dark mode where the theme defines it.
- Images use responsive `srcset` only when variants exist; otherwise width/height attributes
  prevent layout shift.

## 8. Preview and activation

- The Appearance screen renders an **iframe** of the reader preview using the draft theme
  settings (`/author/{e}/preview?theme=...&settings=...`), updated after a 300 ms debounce.
- Theme changes are staged client-side and only persisted on **Save theme**; navigation away
  warns about unsaved changes.
- Switching theme keeps settings keys that are valid for the new theme, drops the rest, and
  shows a diff summary before saving.

## 9. Versioning policy

- `theme.json.version` is semver-ish (`1.0.0`). Breaking markup/CSS changes bump minor;
  contract changes bump major.
- `publications.theme_version` stores the version used at release time.
- Export pinning: the exporter checks that the running theme version is **greater than or
  equal** to the pinned version and records both. A theme downgrade is refused with a clear
  error rather than producing a mismatched archive.
- Theme asset URLs include the version as a cache-busting query in dynamic mode.
