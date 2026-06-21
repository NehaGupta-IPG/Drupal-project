# Menu tree UI

Experimental, scalable replacement for core's `menu_ui` admin form, designed to
handle menus with thousands of links without falling over.

This is an experimental core module. It coexists with `menu_ui`: enabling it makes
the tree the primary **Edit links** action and the "Links" tab for each menu (under
Administration » Structure » Menus), and demotes `menu_ui`'s classic tabledrag
overview to an "Edit links (no js)" action and a "Links (no js)" tab alongside it.
The classic UI stays available as the no-JS fallback while this module matures.

## What works

- Lazy-loaded tree: only the top level loads on page open; each subtree fetches on expand.
- Browser-native row virtualization via `content-visibility: auto`, plus an `IntersectionObserver` fallback that flips deep off-screen rows to `visibility: hidden` with a reserved height.
- Full WAI-ARIA tree keyboard navigation (Up/Down/Left/Right/Home/End/Enter, roving `tabindex`).
- Pointer drag-and-drop reorder and re-parent, plus two keyboard equivalents: Shift+Up / Shift+Down for a quick sibling reorder, and a grab/select move mode (Space to pick up, arrows to choose a before/after/child position, Enter to drop, Escape to cancel) that steps through the same drop-intent space as drag and commits through the same path.
- A "Keyboard commands" dialog, linked at the top of the page so assistive-technology users meet it early.
- htmx-driven search panel returning HTML partials, with jump-to-result.
- Edits delegated to core's existing per-link form.
- Save / Cancel working copy: reorders (drag and keyboard) are recorded in a
  per-user shared tempstore rather than written immediately, and the tree
  overlays them so unsaved changes are visible. **Save** replays the recorded
  moves to storage; **Cancel** discards them. A second editor of the same menu
  sees a lock notice and cannot save over the first. Column toggles (e.g.
  "Expanded") and the per-row Edit/Delete forms still commit immediately —
  the working copy covers reorder structure only.

## Architecture

Menu tree UI is a **consumer** of two generic experimental modules that were
extracted from it:

- **Tree grid** (`treegrid`) — the reusable spine: the `<drupal-treegrid>`
  custom element, one-level-at-a-time lazy loading, row windowing, WAI-ARIA
  keyboard navigation with a roving `tabindex`, and a column-plugin system with
  a client-side cell-renderer registry.
- **Tree grid reorder** (`treegrid_reorder`) — the drag-and-drop and keyboard
  reordering layer: gesture handling, universal move validation (a node cannot
  be dropped onto itself or into one of its own descendants), and the per-user
  Save/Cancel working copy.

Menu tree UI supplies the menu-specific pieces on top: the row renderer, the
`Enabled` / `Expanded` / `Operations` column plugins, the move handler (which
links may move, the 9-level depth cap, and the write through the menu link
manager), and the routes below.

Routes, by intent:

| Route | Returns | Used by |
| --- | --- | --- |
| `/admin/structure/menu/manage/{menu}/tree` | Full HTML page with top level rendered | Direct browser visit (the "Edit links" operation / "Links" tab on a menu) |
| `/admin/structure/menu/manage/{menu}/tree/children?parent={id}` | JSON `{ parent, items[] }` | `<drupal-treegrid>` element on expand |
| `/admin/structure/menu/manage/{menu}/tree/search?q=…` | HTML fragment | htmx swap from the search input |
| `/admin/structure/menu/manage/{menu}/tree/move` | JSON `{ ok, … }` | Drag/keyboard reorder — records to the working copy (POST) |
| `/admin/structure/menu/manage/{menu}/tree/column-update` | JSON `{ ok, … }` | Column switch toggle (POST) |
| `/admin/structure/menu/manage/{menu}/tree/save` | JSON `{ ok, … }` | Commit the working copy's moves to storage (POST) |
| `/admin/structure/menu/manage/{menu}/tree/cancel` | JSON `{ ok }` | Discard the working copy (POST) |

`MenuTreeLoader::loadLevel()` wraps `MenuLinkTreeInterface::load()` with
`MenuTreeParameters::setRoot()->excludeRoot()->setMaxDepth(1)` to fetch exactly one
level — never the whole menu.

### JavaScript

The frontend is hand-authored, no-build classic JavaScript, matching core's
convention (no bundler, no TypeScript, no ESM).

The interactive element is `<drupal-treegrid>`, defined by the `treegrid`
module — not by this one. Its `connectedCallback` fires automatically when the
element enters the DOM (including htmx-swapped content), and it exposes
lifecycle events (`treegrid:connected` / `treegrid:disconnect`) and an
interaction-handler registry (`addClickHandler` / `addKeyHandler`). Menu tree UI
attaches its own features to those hooks.

Generic behavior lives in the two upstream modules, under the `Drupal.treegrid`
and `Drupal.treegridReorder` namespaces:

- `treegrid/js/` — `treegrid-element.js` (the custom element), `tree-nav.js`
  (row walking + roving-tabindex focus), `expand.js` (lazy load + collapse,
  `ExpandManager`), `windowing.js` (`IntersectionObserver` virtualization,
  `RowWindow`), `keyboard.js` (WAI-ARIA navigation keys), `cells.js` (the
  cell-renderer registry).
- `treegrid_reorder/js/` — `controller.js` / `intent.js` / `indicator.js` /
  `commit.js` (pointer drag), `move-mode.js` (keyboard grab/select), and
  `reorder-keys.js` (Shift+Arrow). These are parameterized by a config object
  so they carry no menu CSS knowledge of their own.

Menu tree UI's own JavaScript is a `Drupal.behaviors` attach (the element itself
is generic), under the `Drupal.menuTreeUi` namespace:

- `js/menu-tree.js` — the behaviors entry point: on each upgraded element, wires
  reorder, the column controls, search jump-to, and the keyboard-help dialog
  through the element's events and interaction registry.
- `js/row-builder.js` — turns the children JSON into row DOM (`buildRow` /
  `buildRows`) and registers the menu row renderer as a `<drupal-treegrid>`
  provider.
- `js/search.js` — the htmx search panel behavior.
- `js/jump-to.js` — document-level jump-from-search-result.
- `js/working-copy.js` — the Save / Cancel action bar.

The element upgrades the server-rendered top level (from
`templates/menu-tree-ui-page.html.twig`) rather than rebuilding from scratch:

- First paint is the real tree, not a loading spinner.
- No-JS users see at least the top level.
- Row markup for children built in JS mirrors
  `templates/menu-tree-ui-row.html.twig`, and the cell widgets mirror the
  renderers in `treegrid/js/cells.js`. They must be kept in sync — see the
  `NB:` comment in `js/row-builder.js`.

## Constraints inherited from core

- **9-level depth cap.** `menu_tree.p1`–`p9` is the materialized path columns in core. Links deeper than 9 don't work in Drupal at all — not specific to this module.

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
