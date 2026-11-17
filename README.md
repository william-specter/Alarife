# Alarife

Thematic encyclopedia platform: author independent reference works, dress them in
distinct themes, publish versioned editions and export complete offline websites.

- **Documentation:** [`Documentation/`](Documentation/README.md)
- **Implementation:** [`source_code/`](source_code/README.md)
- **Reference material:** [`Docs/`](Docs) — architecture diagrams, ERD, UI/UX references

## Start here

1. Read [01 — Product Requirements](Documentation/01-product-requirements.md).
2. Read [02 — System Architecture](Documentation/02-system-architecture.md).
3. Follow the setup instructions in [source_code/README.md](source_code/README.md).
4. Review the implementation status and deviations in
   [11 — Roadmap](Documentation/11-implementation-roadmap.md).

## What is delivered

- Laravel + Inertia + Vue 3 authoring workspace: dashboard, wizard, overview, settings,
  covers, topics, articles, Tiptap editor, media library, appearance designer, publishing,
  exports.
- Five versioned themes, one shared Blade renderer for dynamic and static output.
- Octane + FrankenPHP serving and Reverb WebSockets for real-time collaboration (Redis optional).
- PostgreSQL schema with JSONB content, publication snapshots, revisions, exports and
  activity logs; policies for tenant isolation.
- Queued static export pipeline with media collection, relative links, offline search,
  link validation, ZIP checksums and reader downloads.
- 57 backend tests, Vitest utility tests, typed frontend and Playwright-reviewed UI.
- Cross-encyclopedia references to published articles/topics, validated and pinned in releases.
- `composer run dev` (pnpm-powered: Octane, Reverb, queue, Vite) and a Docker Compose stack that
  reuses the existing PostgreSQL/Redis infrastructure rather than pulling service images.

Execution details: [`Documentation/13-runtime-realtime-and-execution.md`](Documentation/13-runtime-realtime-and-execution.md).

See the roadmap for documented deviations from the original brief (notably the
Laravel 12 / Inertia 2 / Tailwind 3.4 starter-kit baseline).
