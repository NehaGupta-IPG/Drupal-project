# Tree grid reorder

Experimental drag-and-drop and keyboard reordering layer for
[Tree grid](../treegrid/README.md) screens. It supplies the generic gesture
handling, universal move validation, and an optional Save/Cancel buffer; a
consuming module supplies the domain rules and the write. It has no
user-facing UI of its own.

## What it adds

- **Pointer drag-and-drop** reorder and re-parent, with a drop-line indicator
  between rows and an "as-child" outline highlight, plus an aria-live
  announcement of what would happen on release.
- **Keyboard move mode**: Space to pick a row up, arrows to step through the
  same before/after/child drop-intent space as drag, Enter to drop, Escape to
  cancel.
- **Shift+Arrow** as a quick keyboard equivalent for a same-level sibling
  reorder, without entering move mode.
- **Universal move validation**, shared by drag, keyboard move mode, and
  commit: a node cannot be dropped onto itself, nor into one of its own
  descendants (a cycle). `TreeReorderManager` enforces these two structural
  rules before delegating to the consumer's domain rules.
- **Optional `TreegridWorkingCopy`** Save/Cancel buffer: instead of writing
  each move immediately, moves are recorded as an ordered op list in a shared
  tempstore and overlaid at read time onto lazily-loaded levels. Save replays
  the recorded ops through the same validate-then-apply path used for a single
  move; Cancel discards them. Cost is proportional to the number of pending
  edits, not the size of the tree. Uses the *shared* (not private) tempstore so
  a second editor sees a lock notice naming the current owner.

## Consumer contract

**Implement `TreeMoveHandlerInterface`** to supply the domain seam:
- `validateConstraints(string $tree_id, MoveIntent $intent): array` — your
  rules (movability, scope, depth limits, vetoes), called after
  `TreeReorderManager`'s structural checks pass. Return
  `['ok' => TRUE]` or `['ok' => FALSE, 'code' => …, 'message' => …]`. The
  `message` is markup built with `t()` and its placeholders, which the client
  shows unchanged.
- `getAncestorIds(string $tree_id, string $parent_id): array` — the root path
  of a prospective parent, for the manager's cycle check.
- `applyMove(string $tree_id, MoveIntent $intent): void` — persist the move
  and fire any domain-specific reactions (for example, your own post-move
  hook).

**Run moves through `TreeReorderManager`**: `validateMove()` for a
record-time check (drag drop, keyboard move-mode confirm, Shift+Arrow), and
`commitWorkingCopy()` to replay a working copy's ops on Save. It applies the
moves in order, validating each against the current state immediately before
its write, and does not pre-validate the batch. A handler that wants nothing
written when any move is invalid validates the sequence itself before calling
it. On the first failure it returns `failed_op` and the `applied_ops` already
written, so the caller can reconcile the working copy with storage.

**`MoveIntent`** is the shared move vocabulary: `{ id, newParent, beforeId }`
— the same shape the client POSTs and the working copy records.

**To buffer moves for Save/Cancel**, instantiate `TreegridWorkingCopy` with a
tempstore collection name unique to your module, so two consumers editing
same-named trees do not collide.

**To read a tree with its pending moves applied**, call
`TreegridWorkingCopy::view($tree_id)`. It returns a
`WorkingCopyViewInterface` over the recorded moves:
- `levelIds(string $parent_id, array $live_ids, array $existing_ids): array`:
  the ids the editor sees in one level, in order, including ids moved in
  from other levels. `$live_ids` is the level's live ids and `$existing_ids`
  the moved ids that still exist; a move of an id that is neither is skipped.
- `pendingParent(string $id): ?string`: the parent the editor sees for a
  link with a pending move (`''` for the top), or NULL when it has none.
- `pendingIds(): array`: the ids of every link with a pending move.

The view works on id lists, so a consumer slices and loads only what it shows.
It is built on `MoveLogReplay`, a stateless `@api` helper you instantiate with
`new` for the same replay without a working copy: `pendingParents($ops)`,
`spliceBefore($order, $moved, $before_id)` and `replayLevel($parent_id,
$live_ids, $ops, $existing_ids)`. In all of them a parent id of `''` means the
top of the tree.

## The JavaScript surface

Load the `treegrid_reorder/treegrid_reorder` library and call
`Drupal.treegridReorder.attach(element, options)` from a behavior. It returns
`{ isActive(), cancel(), aimAt(row), detach() }`, and its internals
(`DragController`, `MoveModeController`, `DropIndicator`, `commitMove`,
`resolveNewParentId`, `resolveBeforeId`, `computeIntent`, `sameIntent`,
`rowHeaderRect`, `reorderSibling`) are internal.

```js
const reorders = new WeakMap();
Drupal.behaviors.myTree = {
  attach(context) {
    once('my-tree', 'drupal-treegrid[data-provider="my_tree"]', context).forEach(async (element) => {
      reorders.set(element, Drupal.treegridReorder.attach(element, {
        applyParentMetadata,
        container: element.closest('.my-tree'),
      }));
      await element.ready;
      // The tree may have been detached while this waited.
      if (!element.isConnected || !reorders.has(element)) {
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
      reorders.get(element)?.detach();
      reorders.delete(element);
    });
  },
};
```

Options:

- `moveUrl`: the move endpoint. Wins over the element's
  `data-treegrid-reorder-move-url` attribute.
- `applyParentMetadata(parentId)`: called with the old and the new parent after
  a move's refresh, so the consumer can update a parent's toggle and open
  state. The element's `data-has-children` on the parent row is already
  current.
- `container`: a pointer press outside it cancels an active move. Defaults to
  the element. A page that searches for a destination outside the tree passes
  an element around both.

`attach()` registers its key and click handlers before it returns, whether or
not the element is ready, so they run ahead of handlers a consumer registers
afterwards. Call it before registering your own key handlers: move mode owns
every key while a row is picked up. It waits for `element.ready` only for the
rest, then sets `data-treegrid-reorder-ready` on the element. Outside a move a
key pressed on an in-row control other than the toggle (`a`, `button`,
`input`, `select`, `textarea`) is left to the control.

### Markers

The module finds its parts by attributes the consumer renders:

| Attribute | On | Meaning |
| --- | --- | --- |
| `data-movable="true"` | row | The only thing that makes a row movable, for every gesture |
| `data-treegrid-reorder-handle` | drag handle | Where a drag starts and a tap picks the row up |
| `data-treegrid-reorder-title` | the row's visible name | Read for announcements; the row's `aria-label` is the fallback |
| `data-treegrid-reorder-move-url` | element | The move endpoint |
| `data-treegrid-reorder-revision` | element | The working copy's revision; the request helper updates it |

The module writes `data-treegrid-reorder-ready`, `-moving` (from the moment a
move is accepted until its unit ends), `-drop` (move mode's target as
`kind:id`) and `-needs-reload` (`stale` or `refresh-failed`)
on the element, and `-dragging`, `-grabbed`,
`-drop-target="child"` and `-flash` on rows. It creates
`.treegrid-reorder-indicator`. `aria-selected="true"` stays on the grabbed row
for assistive technology, not as a styling hook.

The library's stylesheet draws all of these with no specificity, so a
consumer's own rule wins. The custom properties `--treegrid-reorder-color`
(default `Highlight`) and `--treegrid-reorder-indicator-size` (default `3px`)
set its colors and the indicator's thickness.

### Moves

Every move runs as one unit in the treegrid element's queue (see the treegrid
README, including the rule against calling the element's own queued methods
from inside a unit). A move is accepted only when no other move is accepted
and unfinished and no write through the request helper is running or waiting.
Picking up, dropping, dragging and Shift+Arrow are refused otherwise, while
the tree needs a reload too. A refusal is announced once per busy period; a
refused drop or Shift+Arrow fires `treegrid-reorder:failed` with the code
`refused`, and a refused drop leaves the carry in place.

A drop on a row as its child opens that row first if its level isn't loaded
(a leaf's level counts as loaded when it comes back empty), and marks it open
before the refresh so the moved row shows.

Events, all bubbling on the element:

| Event | Detail |
| --- | --- |
| `treegrid-reorder:moved` | `{ id, parentId, beforeId, previousParentId, gesture, refreshed, result }`; `gesture` is `drag`, `move-mode` or `shift-arrow`; `result` is the request helper's result; `refreshed` is `false` when the redraw failed |
| `treegrid-reorder:failed` | `{ id, parentId, beforeId, gesture, status, code, message, sent }`; the module's own codes are `refused` and `unavailable` (a row, target or destination that is gone or couldn't be opened) |
| `treegrid-reorder:needs-reload` | `{ reason }`; fired when the state is entered or gets stricter |

A move whose redraw fails is recorded: `moved` fires with `refreshed: false`,
then the tree enters the needs-reload state with the reason `refresh-failed`
(the order is `moved`, then `needs-reload`). A stale refusal fires
`needs-reload` with the reason `stale`, then `failed`.

The element's row methods (`expandRow`, `collapseRow`, `refreshLevel` and the
rest) need `element.ready` to have resolved; await it before calling them.
The state only gets stricter: `stale` blocks every write and every move, the
other reasons block moves only.

A carry or a drag reacts to `treegrid:before-rows-change`: move mode puts the
row back where it started and ends the move when a level is reconciled, and
re-places it against its target once added rows are in; a drag drops its
target when a level is reconciled. The row goes back right after the previous
item at its level (and that item's block), right after its parent when it was
the first child, or to the start of the tree body when it was the first
top-level row.

### The request helper

`Drupal.treegridReorder.request(element, url, { method = 'POST', body, unit })`
sends a JSON request and resolves `{ ok, status, code, message, body, sent }`.
Without `unit` it runs as its own unit in the element's queue; with the unit a
running move passes it runs inside that unit. POST, PUT, PATCH and DELETE are
writes; GET is a read.

It sends `Content-Type` and `Accept: application/json`, `X-CSRF-Token` from
`Drupal.treegrid.csrfToken()` unless that is empty, and
`X-Treegrid-Reorder-Revision` with the value of
`data-treegrid-reorder-revision` whenever the element has that attribute. A
response carrying that header writes it back to the attribute, whatever its
status. `ok` is true only for a 2xx response whose JSON body has `ok: true`;
`code` and `message` come from the body; a network error resolves
`{ ok: false, status: 0, code: null, message: null, body: null, sent: true }`.

A 409 with `code: 'stale'` puts the tree into the needs-reload state: from then
on every write through the helper, including those already queued, resolves
`{ ok: false, status: 0, code: 'stale', sent: false }` without being sent.
Reads still go out. Other failures (409 `busy`, `locked`, `save_incomplete` or
`save_conflict`, 403, 400, 500) are ordinary: nothing else changes.

A move request is `POST { id, new_parent, before_id }` and the response is
`{ ok, code?, message? }` plus whatever fields the consumer adds (menu_tree_ui
adds `dirty`). A response `message` is safe HTML, escaped by the server; the
client passes it through unchanged and escapes only text it takes from the DOM.
A row's `aria-label` is plain text.

## No UI of its own

Tree grid reorder ships no admin pages, forms, or routes. It only activates
once a consumer wires its move endpoint, `TreeMoveHandlerInterface`
implementation, and (optionally) a `TreegridWorkingCopy` collection together.
