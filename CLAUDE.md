# CLAUDE.md

Sylius 2 plugin adding a command palette (Ctrl+K / ⌘K global search) to the admin.
Functional spec: `SPEC.md`. Public docs: `README.md`.

## Commands

PHP needs more than 128 MB to compile the test application container: prefix console, PHPStan
and functional PHPUnit calls with `php -d memory_limit=2G` when running locally.

```bash
docker compose up -d mysql                # ports come from compose.override.yml (gitignored)
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite non-unit   # functional + integration, need the test DB + Sylius fixtures
vendor/bin/phpstan analyse                # level max, src + tests
vendor/bin/ecs check
vendor/bin/console lint:container
node --check Resources/public/command-palette.js
vendor/bin/console assets:install --symlink --relative   # after changing the JS / CSS
```

Local env overrides (gitignored): `compose.override.yml` (MySQL published on 3317, MailHog on
8036/1036), `tests/TestApplication/.env.local` (DATABASE_URL + MAILER_DSN) and
`.env.test.local` (DATABASE_URL). Databases: `tmonier_sylius_command_palette_dev` / `_test`.
Dev server: `symfony server:start -d --port=8083 --no-tls`, admin at http://127.0.0.1:8083/admin.

## Architecture

- `src/Search/SearchProviderInterface` + `SearchResult` — the extension point; providers are
  tagged `tmonier_command_palette.provider` (priority = group order) and collected by
  `SearchProviderRegistry` (unique snake_case keys).
- `src/Search/CommandPaletteSearch` — normalizes the query (2..100 chars), calls every provider
  with `limit_per_group`, translates group labels, logs and skips failing providers.
- `src/Search/Provider/*` — built-in providers. Scalar DQL (`getArrayResult()`), at most two
  queries each, read through the `Row` helper; LIKE patterns only via `LikePattern`.
- `src/Controller/Admin/SearchController` — the JSON endpoint.
- `src/Page/AdminPageCatalog` — pages from the KnpMenu `sylius_admin.main` + "create" actions,
  rendered in the layout by `src/Twig/CommandPaletteExtension` (also lists the icons to render).
- `templates/admin/command_palette/` — navbar trigger, stylesheet, dialog + script (hooks in
  `config/twig_hooks/admin.yaml`).
- `Resources/public/` — the self-contained JS and CSS (no build step).

## Rules

- Assets live in `Resources/public/`, not `public/`: a root `public/` directory makes
  `assets:install` (run from the plugin root) copy the bundle into itself recursively.
- Bump `TmonierSyliusCommandPalettePlugin::ASSETS_VERSION` when the JS or CSS changes.
- JS: never `innerHTML` with data — `textContent` and cloned `<template>` icons only. Keep it
  dependency-free and ES5-compatible syntax (it is checked with `node --check` in CI).
- Only icons that exist in the Sylius UX Icons set (`vendor/sylius/sylius/src/Sylius/Bundle/UiBundle/Resources/assets/icons/tabler`)
  for built-in providers: other names would be fetched from Iconify.
- Every user value goes through bound parameters and `LikePattern`; no per-result queries.
- Every translation key must exist in en, fr, de, es, it, nl, pl (`TranslationsTest`, which also
  checks that keys used in `src/` and `templates/` exist). Clear the cache (dev and test) after
  adding a translation file.
- Functional and integration tests run in a transaction rolled back after each test.
