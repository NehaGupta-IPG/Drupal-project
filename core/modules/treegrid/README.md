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
- **Row windowing.** `content-visibility: auto` plus an `IntersectionObserver`
  fallback flip deep off-screen rows to `visibility: hidden` with a reserved
  height, so a large expanded tree does not pay full render cost.
- **Lifecycle events and interaction hooks**, so features (reorder, columns,
  search) attach without the element knowing about them: `treegrid:connected`
  (detail `{ element, tree, expand, rowWindow }`), `treegrid:disconnect`
  (detail `{ element }`), and an interaction-handler registry —
  `addClickHandler(fn)` / `addKeyHandler(fn)` — where handlers run before the
  element's built-in navigation and return `true` to consume the event.
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
`Drupal.treegrid.registerProvider(name, { buildRows, buildRow })` before (or
after — pending elements are adopted once their provider arrives) the element
upgrades. `buildRows(items, depth)` returns a `DocumentFragment` of sibling
`<tr>` rows for a fetched level; `buildRow(item, depth, posInSet, setSize)`
builds a single row. The provider is selected by the element's
`data-provider` attribute.

**The children endpoint.** `data-children-url` must return JSON shaped
`{ parent, items: [...] }`, where each item at minimum carries enough for the
generic engine to place it (an id, a parent id, whether it has children) plus
whatever consumer-specific fields its row renderer and column plugins expect.
The consumer defines and controls this shape; treegrid does not validate it.

**Column plugins.** Implement `TreegridColumnInterface`
(`getHeader()`, `getCellData(object $context)`, `handleUpdate(string $link_id,
mixed $value)`) or extend `TreegridColumnBase`, which supplies a read-only
default `handleUpdate()`. `getCellData()` receives an opaque row context object
whose concrete type is defined by the consumer; return `[]` to render no cell
for a row (for example, a column that only applies to parent rows). A
non-empty return must include a `type` key naming the cell widget so the
matching JS renderer can draw it. `handleUpdate()` is called by the consumer's
own update endpoint — treegrid defines the contract but ships no route or
controller for it.

## No UI of its own

Tree grid ships no admin pages, forms, or routes. Enabling it does nothing
visible until a consumer module registers a provider and requests the element.
