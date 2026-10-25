# Tree grid

Experimental, reusable spine for building scalable hierarchical admin
screens: the `<drupal-treegrid>` custom element plus a server-side column
plugin system. It has no user-facing UI of its own — it is a library that
other modules build on.

## What it provides

- **`<drupal-treegrid>` custom element.** Upgrades a server-rendered
  `<table role="treegrid">` into an interactive tree: one-level-at-a-time lazy
  loading, row windowing (virtualization), and full WAI-ARIA tree keyboard
  navigation (Up/Down/Left/Right/Home/End/Enter) with a roving `tabindex`.
  `connectedCallback` fires automatically when the element enters the DOM,
  including htmx-swapped content — no `Drupal.behaviors` needed to upgrade it.
- **Row windowing.** An `IntersectionObserver` marks rows far outside the
  viewport with `data-treegrid-virtualized`. They keep a reserved height
  (`--treegrid-virtualized-row-height`, default `2.5rem`), so a large expanded
  tree does not pay full paint cost. Table rows can't use
  `content-visibility`, so every row is still laid out.
- **A request queue, a ready signal, lifecycle events and interaction hooks**,
  so features (reorder, columns, search) attach without the element knowing
  about them. See "The JavaScript surface" below.
- **`TreegridColumn` plugin system.** Modules contribute columns by placing a
  class implementing `TreegridColumnInterface` under
  `src/Plugin/TreegridColumn/`, decorated with `#[TreegridColumn(id, title,
  weight, deriver)]`. `TreegridColumnManager::getSortedColumns()` discovers and
  sorts them by weight; `hook_treegrid_column_info_alter()` lets other modules
  alter the discovered definitions.
- **JS cell-renderer registry.** A column's `getCellData()` returns an array
  with a `type` key (plus type-specific keys); the type is drawn client-side by
  a renderer registered with `Drupal.treegrid.registerCellRenderer(type, fn)`.
  Shipped types: `checkbox` (a boolean control, optionally interactive) and
  `markup` (an opaque, trusted, server-rendered HTML string injected verbatim
  — never put user-supplied input in a `markup` cell).

## DOM contract

```html
<drupal-treegrid data-provider="…" data-children-url="…">
  <table role="treegrid">
    <tbody>
      <tr role="row" data-id="…" data-parent-id="…" aria-level="1" data-has-children="true">…</tr>
    </tbody>
  </table>
</drupal-treegrid>
```

Rows are flat siblings in document order — there is no nested
`<ul role="group">` structure. Hierarchy is encoded entirely by `aria-level`
and `data-parent-id`; a row's children are the sibling `<tr>` elements
immediately following it whose `data-parent-id` matches its `data-id`. The
disclosure toggle inside a row carries `data-treegrid-toggle`.

`data-provider` names the renderer registered for this element (see below).
`data-children-url` is the endpoint the element fetches one level of children
from, on first expand of a row.

## Plugging in a consumer

**Register a row renderer.** Call
`Drupal.treegrid.registerProvider(name, { buildRow })` before (or after,
pending elements are adopted once their provider arrives) the element
upgrades. `buildRow(item, depth)` returns the `<tr>` for one item of a fetched
level; treegrid loops it over the level and sets `aria-posinset` and
`aria-setsize` on every row it inserts. The provider has no lifecycle hooks:
set up each tree from a behavior (see below). The provider is selected by the
element's `data-provider` attribute.

**The children endpoint.** `data-children-url` must return JSON shaped
`{ parent, items: [...] }`, where each item at minimum carries enough for the
generic engine to place it (an id, a parent id, whether it has children) plus
whatever consumer-specific fields its row renderer and column plugins expect.
The consumer defines and controls this shape; treegrid does not validate it.

**Column plugins.** Implement `TreegridColumnInterface`
(`getHeader()`, `getCellData(object $context)`, `handleUpdate(string $row_id,
mixed $value)`) or extend `TreegridColumnBase`, which supplies a read-only
default `handleUpdate()`. `getCellData()` receives an opaque row context object
whose concrete type is defined by the consumer; return `[]` to render no cell
for a row (for example, a column that only applies to parent rows). A
non-empty return must include a `type` key naming the cell widget so the
matching JS renderer can draw it. `handleUpdate()` is called by the consumer's
own update endpoint, which receives `{ column_id, row_id, value }` from
treegrid's column-update client (see below). `$row_id` is the row's `data-id`.
treegrid defines the contract but ships no route or controller for it.

## The JavaScript surface

Public members are kept stable. Members marked internal may change.

### Attributes

| Attribute | Written by | Meaning |
| --- | --- | --- |
| `data-provider` | consumer | The provider name. Required. |
| `data-children-url` | consumer | The children endpoint. Required. |
| `data-auto-expand-threshold` | consumer | A tree with at most this many top-level rows opens a bounded extra level on load. Default 2. |
| `data-treegrid-csrf-token` | consumer | The CSRF header token, empty when none is needed. |
| `data-treegrid-column-update-url` | consumer | The column-update endpoint. |
| `data-treegrid-ready` | treegrid | Present once the element has initialized. |

### Element properties and methods

- `element.tree`: the `<table role="treegrid">` (read-only).
- `element.ready`: a promise that resolves once the element has its provider
  and has initialized.
- `element.addClickHandler(fn)` and `element.addKeyHandler(fn)`: handlers run
  in registration order before the built-in navigation, and return `true` to
  consume the event. Each returns a function that removes the handler. They
  can be registered before the element is ready.
- `element.exclusive(fn)`: runs `fn(unit)` in the element's request queue and
  returns a promise of its result.
- `element.expandRow(row)`: resolves `true` once the row is open and its level
  is in, `false` when the load failed or was canceled, or the row has no
  children (a leaf's level is marked loaded, with no `aria-expanded`).
- `element.collapseRow(row)`: returns `false` when a descendant marked
  `data-treegrid-keep-visible="true"` blocks it. Never queued.
- `element.refreshLevel(parentId, options = {})`: reloads a level and
  reconciles its rows; resolves `false` when the fetch failed. `options` is
  reserved. A level that was never loaded only updates the parent row's
  `data-has-children`; a loaded but collapsed level gets any new rows hidden.
- `element.clearChildren(row)`: removes a row's descendant rows, detaching
  their behaviors, and forgets they were loaded. DOM only, never queued.

`element.rowWindow` is internal. So are `Drupal.treegrid.providers`,
`handleKeyDown`, `ExpandManager`, `RowWindow`, `Queue`,
`attachColumnUpdates` and the `treeNav` helpers `firstVisibleRow`,
`lastVisibleRow`, `nextVisibleRow`, `previousVisibleRow`,
`setRowControlsActive` and `initRovingTabindex`.

### The request queue

Level loads, `refreshLevel()` and the writes of features such as
treegrid_reorder run one at a time, first in first out, as units in the
element's queue. The next unit starts when the previous one settles, resolved
or thrown. Expanding or collapsing a row whose children are already loaded,
and `clearChildren()`, touch only the DOM and run at once. Column updates go
out at once, outside the queue.

A unit receives a unit object with `expandRow()`, `refreshLevel()` and
`exclusive()`. They run inside the unit. `unit.exclusive(fn2)` calls
`fn2(unit)` at once. Every unit method throws once its unit has settled.

Calling `element.expandRow()` or `element.refreshLevel()` from inside a unit
queues behind that unit and never finishes. Use the unit object's methods
there.

A load waiting in the queue marks its row with `data-treegrid-loading="waiting"`
and `aria-busy="true"`, and does not set `aria-expanded="true"` until it runs;
then the value is `loading`. A second `expandRow()` on the row returns the same
promise. Collapsing the row, pressing its toggle again or pressing Left on it
cancels a load that is still waiting, and its `expandRow()` resolves `false`.

### Events

All are `CustomEvent`s dispatched on the element.

| Event | Bubbles | Detail |
| --- | --- | --- |
| `treegrid:connected` | yes | `{ element, tree, reconnected }`, after init and after each reconnect |
| `treegrid:disconnected` | no (the element is detached) | `{ element }`; listen on the element |
| `treegrid:level-loaded` | yes | `{ parentId }` |
| `treegrid:level-load-failed` | yes | `{ parentId, status }`, status 0 for a network error |
| `treegrid:before-rows-change` | no | `{ parentId, change }`, `change` is `insert` or `reconcile` |
| `treegrid:cell-updated` | yes | `{ rowId, columnId, value, status, code, message }` |
| `treegrid:cell-update-failed` | yes | the same |

`treegrid:before-rows-change` is synchronous and not cancelable. It fires
inside a unit: first thing in a refresh (`reconcile`, before any row is
looked up or snapshotted) and before a level load works out where to insert
(`insert`). A listener may change its own state, but must not start units or
change rows other than its own.

An element initializes once. Moving it in the DOM pauses its own listeners
and windowing on disconnect and resumes them on reconnect, firing
`treegrid:connected` with `reconnected: true`. Handlers, the queue and
anything waiting stay.

### Consumer pattern

Set up each tree from a behavior, awaiting `element.ready` for anything that
needs a ready tree, and clean up in `detach`:

```js
const records = new WeakMap();
Drupal.behaviors.myTree = {
  attach(context) {
    once('my-tree', 'drupal-treegrid[data-provider="my_tree"]', context).forEach(async (element) => {
      records.set(element, {});
      await element.ready;
      // The tree may have been detached while this waited.
      if (!element.isConnected || !records.has(element)) {
        return;
      }
      // Setup that needs a ready tree.
    });
  },
  detach(context, settings, trigger) {
    if (trigger !== 'unload') {
      return;
    }
    once.remove('my-tree', 'drupal-treegrid[data-provider="my_tree"]', context).forEach((element) => {
      records.delete(element);
    });
  },
};
```

A DOM move is not a removal: no `detach` runs and `once()` won't attach
again. Page-level listeners are removed on `treegrid:disconnected` and
restored on `treegrid:connected` with `reconnected: true`, both listened for
on the element.

### Row contract

A row is `<tr role="row">` with `data-id`, `data-parent-id` (empty for the top
level), `aria-level`, `data-has-children` (`true` or `false`),
`aria-expanded` on open or closed parents, and `tabindex` (exactly one row
`0`). `aria-label` is optional and announced on expand and collapse. Markers:
`data-treegrid-toggle` on the expand button, `data-treegrid-name-cell` on the
element that indents (by `--treegrid-indent`, default `1.25rem` per level), and
`data-treegrid-control` on controls that join the active row's tab order.
`data-treegrid-keep-visible="true"` on a row stops any ancestor from
collapsing. treegrid calls `Drupal.attachBehaviors()` on every row it inserts
and `Drupal.detachBehaviors()` on every row it removes.

State attributes treegrid writes on rows: `data-treegrid-hidden`,
`data-treegrid-loaded`, `data-treegrid-loading` and
`data-treegrid-virtualized`.

### The CSRF token

A consumer prints `TreegridCsrfToken::addPlaceholder($build)` into
`data-treegrid-csrf-token`. The placeholder is replaced at the last moment of
rendering with the token for routes requiring `X-CSRF-Token` (empty for an
anonymous user), so the page stays cacheable.
`Drupal.treegrid.csrfToken(element)` resolves the token: the attribute's value
when present, otherwise `''` for an anonymous visitor, otherwise a fetch of
`/session/token`, once per page. Callers send no header when it is `''`. A 403
is an ordinary failure and is not retried.

### The column-update client

With `data-treegrid-column-update-url` on the element, a change to an
interactive `.treegrid-cell-checkbox` is POSTed there as
`{ column_id, row_id, value }` with the CSRF token. The checkbox is disabled
while the request is in flight. On a non-2xx status, `ok: false` or a network
error the checkbox goes back to its earlier value and the failure is
announced. The endpoint answers `{ ok, code?, message? }`. Without the
attribute a checkbox changes on screen only and nothing is sent. The checkbox
markup is `<input type="checkbox" class="treegrid-cell-checkbox"
aria-label="…" data-column-id="…" data-treegrid-control>`; a consumer that
renders its first page in Twig must emit the same markup.

## No UI of its own

Tree grid ships no admin pages, forms, or routes. Enabling it does nothing
visible until a consumer module registers a provider and requests the element.
