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
  `['ok' => TRUE]` or `['ok' => FALSE, 'code' => …, 'message' => …]`.
- `getAncestorIds(string $tree_id, string $parent_id): array` — the root path
  of a prospective parent, for the manager's cycle check.
- `applyMove(string $tree_id, MoveIntent $intent): void` — persist the move
  and fire any domain-specific reactions (for example, your own post-move
  hook).

**Run moves through `TreeReorderManager`**: `validateMove()` for a
record-time check (drag drop, keyboard move-mode confirm, Shift+Arrow), and
`commitWorkingCopy()` to replay a working copy's ops on Save — it validates
the whole batch first, then applies in order, re-validating each move
immediately before its write so one move invalidated by an earlier one in the
same batch (a newly formed cycle) is caught rather than silently applied.

**`MoveIntent`** is the shared move vocabulary: `{ id, newParent, beforeId }`
— the same shape the client POSTs and the working copy records.

**To buffer moves for Save/Cancel**, instantiate `TreegridWorkingCopy` with a
tempstore collection name unique to your module, so two consumers editing
same-named trees (for example, two menus) do not collide.

**On the client**, load the `treegrid_reorder/treegrid_reorder` library and
pass a configuration object naming your own CSS classes and selectors — for
example a drag handle selector, a "locked" class for non-movable rows, a
"dragging" class, the drop-line and as-child highlight classes, and a
row-title selector used in announcements — so the gesture code (`controller.js`,
`move-mode.js`, `indicator.js`, `reorder-keys.js`) carries no markup knowledge
of its own.

## No UI of its own

Tree grid reorder ships no admin pages, forms, or routes. It only activates
once a consumer wires its move endpoint, `TreeMoveHandlerInterface`
implementation, and (optionally) a `TreegridWorkingCopy` collection together.
