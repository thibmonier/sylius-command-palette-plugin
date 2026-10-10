# Functional specification — admin command palette

The Sylius 2 admin has no global search: the navbar field only posts to the products grid
(`sylius_admin_product_index`), the sidebar field only filters the menu items, and there is no
keyboard shortcut.

## 1. Scope (v1)

| # | Feature | Summary |
|---|---|---|
| 1 | Command palette | Modal dialog on every admin page, opened with Ctrl+K / ⌘K, `/` or a navbar button |
| 2 | Server-side sources | Orders, customers, products + variants, promotions + coupons (tagged providers) |
| 3 | Client-side sources | Admin menu pages and "create" actions, filtered in the browser |
| 4 | Endpoint | `GET /admin/command-palette/search?q=` (JSON) |
| 5 | Extensibility | `SearchProviderInterface` + `tmonier_command_palette.provider` tag |
| 6 | Translations | en, fr, de, es, it, nl, pl |

Out of scope: full-text search engines, search history / favourites, catalog promotions,
shipments, payments and taxons as searchable records, API Platform, Sylius Plus RBAC.

## 2. Dialog behaviour

| Input | Behaviour |
|---|---|
| Ctrl+K / ⌘K | Toggles the palette, from anywhere (also inside form fields) |
| `/` | Opens it when the focus is not in an input, textarea, select or editable element |
| Navbar button | *Search… ⌘K* (Ctrl K on other systems), before the Sylius product search, which is kept |
| Typing | Pages and actions filtered locally from 1 character; remote search from 2 characters, debounced 200 ms, previous request aborted (`AbortController`) |
| ↑ / ↓ | Move the active result (wraps); the first result is active after each render |
| Enter | Opens the active result; Ctrl/⌘+Enter opens it in a new tab |
| Esc, backdrop, close button | Close; the focus returns to the element focused before opening |
| Mouse | Hover activates, click follows the link (middle / Ctrl click open a new tab natively) |

States: empty query → hint; loading → spinner (and "Searching…" when nothing is shown yet);
no result → `No results for "<query>"`; error → retry message; expired session (redirect,
401/403 or non-JSON response) → "reload the page" message.

Accessibility: `role="dialog"` + `aria-modal`, input `role="combobox"` with `aria-expanded`,
`aria-controls`, `aria-activedescendant`; results in a `role="listbox"` with one `role="group"`
per section (labelled by its header) and `role="option"` + `aria-selected` items; Tab is
trapped in the dialog; result counts announced in a polite live region.

Rendering: every text with `textContent`; icons cloned from `<template data-icon>` rendered by
Twig with `ux_icon()` (built-in provider icons, menu icons, `icons` option); unknown icons fall
back to `tabler:arrow-right`. Colours from Tabler CSS variables (light and dark themes).

## 3. Sources

Common rules: query trimmed, whitespace collapsed, cut to 100 characters, ignored below 2
characters; user wildcards escaped (`LikePattern`, escape character `!`); parameters bound;
scalar (array-hydrated) queries, at most 2 per provider; `limit_per_group` results per group
(default 5); case-insensitive (`LOWER()`, `emailCanonical`).

| Group (priority) | Matching | Result |
|---|---|---|
| `orders` (500) | 1) number `IN` candidates: as typed without `#`, without leading zeros, padded to 9 digits (only when the query contains a digit and no space); 2) customer `emailCanonical` or last name prefix, latest checkout first, excluding the orders already found. `state != cart` | `#number`; customer name (e-mail) · total · state; badge payment state; → order page |
| `customers` (400) | e-mail, first name, last name prefix; phone prefix when the query looks like a phone number; "first last" prefixes | full name (or e-mail); e-mail · group; → customer page |
| `products` (300) | 1) translation name (admin locale) contains, or code prefix; 2) variant code prefix, excluding variants of the products found | product: name; code; badge *Disabled*; → product edit. Variant: code; product · variant name; badge *Variant*; → variant edit |
| `promotions` (200) | name contains or code prefix, by priority | name; code; → promotion edit |
| `promotion_coupons` (100) | code prefix | code; promotion name; → coupons page of the promotion |
| `pages` (client) | admin main menu (`sylius_admin.main`) items with an internal URI, translated with their `translation_domain`, parent label as subtitle | → page |
| `actions` (client) | New simple / configurable product, taxon, customer, cart promotion, catalog promotion (routes that exist) | → create page |

The admin locale comes from `sylius.context.locale` (administrator's locale), falling back to
`%locale%`. Labels of server groups: `tmonier_command_palette.group.<key>`, or the humanized key.

## 4. Endpoint

`GET /{admin path}/command-palette/search?q=` — route `tmonier_command_palette_admin_search`.

- Admin firewall: anonymous users and shop users are redirected to the admin login; the
  controller also checks `ROLE_ADMINISTRATION_ACCESS`.
- Response: `{"query": "<normalized>", "groups": [{"key", "label", "results": [{"title", "subtitle", "url", "icon", "badge"}]}]}`,
  empty groups omitted, `Cache-Control: private, no-store`.
- A non-string `q` is treated as empty. A failing provider is logged (channel
  `tmonier_command_palette`) and skipped.

## 5. Configuration

```yaml
tmonier_sylius_command_palette:
    limit_per_group: 5   # 1..50
    icons: []
    providers: { orders: true, customers: true, products: true, promotions: true, pages: true }
```

Disabled providers are removed from the container; `pages: false` removes the pages and actions.

## 6. Assets

`Resources/public/command-palette.js` (IIFE, ES5-compatible syntax, no dependency) and
`command-palette.css`, installed by `assets:install` and included through the
`sylius_admin.base#stylesheets` / `#javascripts` hooks with a `?v=` version
(`TmonierSyliusCommandPalettePlugin::ASSETS_VERSION`). The dialog, styles and script are only
rendered when the user has `ROLE_ADMINISTRATION_ACCESS` (not on the login page, never in the
shop).
