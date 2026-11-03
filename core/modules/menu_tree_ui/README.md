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
- htmx-driven search panel with jump-to-result.
- Edits delegated to core's existing per-link form.
- Save / Cancel working copy: reorders (drag and keyboard) are recorded in a
  per-user shared tempstore rather than written immediately, and the tree
  overlays them so unsaved changes are visible. **Save** replays the recorded
  moves to storage; **Cancel** discards them. A second editor of the same menu
  sees a lock notice and cannot save over the first. Column toggles (e.g.
  "Enabled") and the per-row Edit/Delete forms still commit immediately —
  the working copy covers reorder structure only.

## Architecture

Menu tree UI is a **consumer** of two generic experimental modules that were
extracted from it:

- **Tree grid** (`treegrid`) — the reusable spine: the `<drupal-treegrid>`
  custom element, one-level-at-a-time lazy loading, row windowing, WAI-ARIA
  keyboard navigation with a roving `tabindex`, a rows response loaded through
  core's htmx pipeline, and a column-plugin system whose cells are render
  arrays.
- **Tree grid reorder** (`treegrid_reorder`) — the drag-and-drop and keyboard
  reordering layer: gesture handling, universal move validation (a node cannot
  be dropped onto itself or into one of its own descendants), and the per-user
  Save/Cancel working copy.

Menu tree UI supplies the menu-specific pieces on top: the row template, the
`Enabled` / `Operations` column plugins, the move handler (which
links may move, the 9-level depth cap, and the write through the menu link
manager), and the routes below.

Routes, by intent:

| Route | Returns | Used by |
| --- | --- | --- |
| `/admin/structure/menu/manage/{menu}/tree` | Full HTML page with top level rendered | Direct browser visit (the "Edit links" operation / "Links" tab on a menu) |
| `/admin/structure/menu/manage/{menu}/tree/children?parent={id}` | A rows response (`TreegridRows::build()`), with the paging headers | `<drupal-treegrid>` element on expand, and top-level paging |
| `/admin/structure/menu/manage/{menu}/tree/search?q=…` | A whole htmx document; the search input keeps the results with `hx-select` | htmx swap from the search input |
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
element enters the DOM (including htmx-swapped content), and it exposes a ready
signal (`element.ready`), lifecycle events (`treegrid:connected` /
`treegrid:disconnected`) and an interaction-handler registry (`addClickHandler`
/ `addKeyHandler`). Menu tree UI attaches its own features to those hooks, from
a behavior that calls `Drupal.treegridReorder.attach()` and cleans up in
`detach`.

Generic behavior lives in the two upstream modules, under the `Drupal.treegrid`
and `Drupal.treegridReorder` namespaces:

- `treegrid/js/` — `treegrid-element.js` (the custom element), `tree-nav.js`
  (row walking + roving-tabindex focus), `expand.js` (lazy load + collapse,
  `ExpandManager`), `windowing.js` (`IntersectionObserver` virtualization,
  `RowWindow`), `keyboard.js` (WAI-ARIA navigation keys).
- `treegrid_reorder/js/` — `attach.js` (the entry point), `controller.js` /
  `intent.js` / `indicator.js` / `commit.js` (pointer drag), `move-mode.js`
  (keyboard grab/select), `reorder-keys.js` (Shift+Arrow) and `request.js`
  (the request helper and the needs-reload state). They find their parts by
  marker attributes the row markup renders, so they carry no menu CSS
  knowledge of their own.

Menu tree UI's own JavaScript is a `Drupal.behaviors` attach (the element itself
is generic), under the `Drupal.menuTreeUi` namespace:

- `js/menu-tree.js` — the behaviors entry point: on each upgraded element, turns
  reorder on and wires search jump-to and the keyboard-help dialog through the
  element's events. The column controls are persisted by treegrid's
  column-update client.
- `js/search.js` (the htmx search panel behavior, and the gate that refuses a
  response that isn't search results).
- `js/jump-to.js` — document-level jump-from-search-result.
- `js/working-copy.js` — the Save / Cancel action bar, the unsaved notice (it
  follows the `dirty` flag the move, Save and Cancel responses carry) and the
  needs-reload message with its Reload button. Save and Cancel go through the
  reorder request helper, so they take their turn in the element's queue.

The element upgrades the server-rendered top level (from
`templates/menu-tree-ui-page.html.twig`) rather than rebuilding from scratch:

- First paint is the real tree, not a loading spinner.
- No-JS users see at least the top level.
- Rows loaded later come from the same `templates/menu-tree-ui-row.html.twig`,
  through the children route, so the first page and every loaded level render
  the same markup.
- Requests for rows and for search results take any status messages queued in
  the session, as every htmx response does.

## Constraints inherited from core

- **9-level depth cap.** `menu_tree.p1`–`p9` is the materialized path columns in core. Links deeper than 9 don't work in Drupal at all — not specific to this module.

## Generating a test dataset

The `menu_tree_ui_test` submodule (under `tests/modules/`) ships a Drush
command for seeding and purging large test menus (handy for manually
exercising the UI at scale; not for production). It requires Drush to be
installed in your environment.

Because it is a test module, it is not discovered on a normal site. To enable
it on a development site, allow test extensions to be scanned first — add to
`settings.php` (or `settings.local.php`):

```php
$settings['extension_discovery_scan_tests'] = TRUE;
```

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

## Why loaded rows are rendered by Twig

`templates/menu-tree-ui-row.html.twig` renders every row: the first page of the
top level and every level loaded after it. The children route returns rows
rendered through that template, and core's htmx integration attaches the
libraries, settings and behaviors a row brings (a dropbutton, say). The client
sets only what depends on where a row ends up: its position in its level, its
`aria-level` and indent, and its tab stop.

A row's toggle and spacer are both rendered, and `data-has-children` decides
which one shows, so a row that gains or loses children changes no markup.
