# Alarife — Project Documentation

**Alarife** is a multi-encyclopedia authoring, publishing and distribution platform.
Authors create independent reference works, organise them into topics and articles with a
structured rich-text editor, dress them in one of several themes, publish versioned
editions, and export a complete self-contained static website that works offline.

> "Alarife" is the Spanish word for a master builder / architect. The product builds
> reference works; the documentation follows the same craft metaphor.

---

## Documentation map

| Document | Contents |
| --- | --- |
| [00-delivery-report.md](00-delivery-report.md) | Delivery audit, execution checks and visual proof index |
| [01-product-requirements.md](01-product-requirements.md) | Vision, actors, capabilities, acceptance criteria, scope |
| [02-system-architecture.md](02-system-architecture.md) | Modular monolith, layers, module boundaries, runtime flows |
| [03-data-model.md](03-data-model.md) | ERD, tables, constraints, indexes, invariants |
| [04-content-model-and-editor.md](04-content-model-and-editor.md) | Tiptap JSON schema, renderer, sanitisation, autosave |
| [05-theme-system.md](05-theme-system.md) | Theme contract, tokens, settings validation, versioning |
| [06-publishing-and-static-export.md](06-publishing-and-static-export.md) | Snapshots, publication lifecycle, export pipeline, offline search |
| [07-ui-ux-and-design-system.md](07-ui-ux-and-design-system.md) | Product design language, screens, interaction, accessibility |
| [08-routes-authorization-and-security.md](08-routes-authorization-and-security.md) | Route map, policies, tenant isolation, threat model |
| [09-testing-and-quality.md](09-testing-and-quality.md) | Test pyramid, acceptance matrix, QA workflow |
| [10-deployment-and-operations.md](10-deployment-and-operations.md) | Local stack, production topology, CI/CD, operations |
| [11-implementation-roadmap.md](11-implementation-roadmap.md) | Phases, current status, known deviations, next work |
| [12-ui-review.md](12-ui-review.md) | Playwright UI/UX review log, findings, fixes and screenshots |

Reference material supplied with the brief lives in:

- `Docs/development/` — architecture, ERD, lifecycle and scaling diagrams.
- `Docs/examples/` — UI/UX references (dashboard, workspace, editor, reader, themes, export).

## Implementation status

The shipped implementation in `source_code/` is a Laravel 12 + Vue 3 + Inertia application.
Feature status is tracked in [11-implementation-roadmap.md](11-implementation-roadmap.md).
The documentation describes the target architecture and explicitly records where the
implementation resolves a version or tool differently from the original brief.

## Quick start

```bash
cd source_code
pnpm install --frozen-lockfile
cp .env.example .env            # if .env is absent
php artisan key:generate
php artisan migrate --seed
pnpm dev                        # Vite
php artisan serve               # in a second terminal
php artisan queue:work          # in a third terminal (exports)
```

Demo credentials are printed by the seeder (`admin@alarife.test` / `password`).

## Documentation conventions

- **Must** = non-negotiable invariant; breaking it is a defect.
- **Should** = strong default, deviation must be recorded in the roadmap.
- Diagram sources are Mermaid; rendered reference PNGs are kept in `Docs/development/`.
- API examples use route names from `php artisan route:list`.
