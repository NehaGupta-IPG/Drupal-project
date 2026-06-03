# Menu tree UI

Experimental, scalable replacement for core's `menu_ui` admin form, designed to
handle menus with thousands of links without falling over.

This is an experimental core module. It coexists with `menu_ui`: enabling it adds
a **Menu tree** operation to every menu on `/admin/structure/menu` (sitting just
below `menu_ui`'s "Edit menu", which stays the primary action) and a **Menu tree**
link under Administration » Structure. `menu_ui` remains the favoured UI while
this module matures.

## What works

- Lazy-loaded tree: only the top level loads on page open; each subtree fetches on expand.
- Browser-native row virtualisation via `content-visibility: auto`, plus an `IntersectionObserver` fallback that flips deep off-screen rows to `visibility: hidden` with a reserved height.
- Full WAI-ARIA tree keyboard navigation (Up/Down/Left/Right/Home/End/Enter, roving `tabindex`).
- Pointer drag-and-drop reorder and reparent, plus two keyboard equivalents: Shift+Up / Shift+Down for a quick sibling reorder, and a grab/select move mode (Space to pick up, arrows to choose a before/after/child position, Enter to drop, Escape to cancel) that steps through the same drop-intent space as drag and commits through the same path.
- A "Keyboard commands" dialog, linked at the top of the page so assistive-technology users meet it early.
- htmx-driven search panel returning HTML partials, with jump-to-result.
- Edits delegated to core's existing per-link form.

## Architecture

Routes, by intent:

| Route | Returns | Used by |
| --- | --- | --- |
| `/admin/structure/menu-tree-ui/{menu}` | Full HTML page with top level rendered | Direct browser visit (linked from the "Menu tree" operation on `/admin/structure/menu`) |
| `/admin/structure/menu-tree-ui/{menu}/children?parent={id}` | JSON `{ parent, items[] }` | `<menu-tree>` element on expand |
| `/admin/structure/menu-tree-ui/{menu}/search?q=…` | HTML fragment | htmx swap from the search input |
| `/admin/structure/menu-tree-ui/{menu}/move` | JSON `{ ok, … }` | Drag/keyboard reorder commit (POST) |
| `/admin/structure/menu-tree-ui/{menu}/column-update` | JSON `{ ok, … }` | Column switch toggle (POST) |

`MenuTreeLoader::loadLevel()` wraps `MenuLinkTreeInterface::load()` with
`MenuTreeParameters::setRoot()->excludeRoot()->setMaxDepth(1)` to fetch exactly one
level — never the whole menu.

### JavaScript

The frontend is hand-authored, no-build classic JavaScript, matching core's
convention (no bundler, no TypeScript, no ESM). It is **not** a `Drupal.behaviors`
implementation: the UI is a `<menu-tree>` custom element, whose `connectedCallback`
fires automatically when the element enters the DOM — including htmx-swapped
content — so no `once()`/`attach` re-init plumbing is needed.

Files load in dependency order (see `menu_tree_ui.libraries.yml`) and share helpers
through a single `Drupal.menuTreeUi` namespace:

- `js/tree-nav.js` — visible-row enumeration + roving-tabindex focus.
- `js/windowing.js` — `IntersectionObserver` row virtualisation (`RowWindow`).
- `js/row-builder.js` — JSON → DOM (`buildGroup`, `buildRow`).
- `js/expand.js` — lazy-load + collapse/expand + group refresh (`ExpandManager`).
- `js/keyboard.js` — WAI-ARIA tree navigation keys.
- `js/jump-to.js` — document-level jump-from-search-result.
- `js/reorder-keys.js` — Shift+Arrow sibling reorder.
- `js/move-mode.js` — keyboard grab/select move (`MoveModeController`); reuses the drag drop-intent, `DropIndicator`, and `commitMove`.
- `js/drag/*.js` — pointer-event drag (`intent`, `indicator`, `commit`, `controller`).
- `js/menu-tree.js` — the `<menu-tree>` custom element; defines and registers it.

The `<menu-tree>` element upgrades the server-rendered top level (from
`templates/menu-tree-ui-page.html.twig`) rather than rebuilding from scratch:

- First paint is the real tree, not a loading spinner.
- No-JS users see at least the top level.
- Row markup for children loaded via JS mirrors
  `templates/menu-tree-ui-row.html.twig`. They must be kept in sync — see the
  `NB:` comment in `js/row-builder.js`.

## Constraints inherited from core

- **9-level depth cap.** `menu_tree.p1`–`p9` is the materialised path columns in core. Links deeper than 9 don't work in Drupal at all — not specific to this module.

## Generating a test dataset

The hidden `menu_tree_ui_test` submodule ships a Drush command for seeding and
purging large test menus (handy for manually exercising the UI at scale; not for
production). It requires Drush to be installed in your environment.

```bash
drush en menu_tree_ui_test
drush mtu:seed main                          # default: 5 levels, 15,045 links, lorem titles
drush mtu:seed main --counts=10,10,10,10,10  # balanced 5-level tree (111,110 links)
drush mtu:seed main --counts=5000            # wide single-level stress test
drush mtu:seed main --seed=42 --dry-run      # reproducible run, plan only
```

Cleaning up — by default only deletes links the seeder created (titles starting
with `mtu`):

```bash
drush mtu:purge main
drush mtu:purge main --prefix=""             # nuke ALL menu_link_content links — careful
```

The seeder batches saves and resets entity storage statics every 500 writes to
keep peak memory bounded.

## Why the duplication between Twig and JS row rendering?

`templates/menu-tree-ui-row.html.twig` renders the initial top level.
`js/row-builder.js` renders children fetched at runtime. They produce identical DOM
from different sources.

The alternative — returning HTML partials from the children endpoint instead of
JSON — was rejected because the client needs the structured data for drag-and-drop,
optimistic updates, and ordering arithmetic. Building that on top of an
HTML-partial endpoint is harder than living with a small render duplication.
