/**
 * @file
 * A minimal treegrid consumer that wires only the reorder gestures.
 *
 * A minimal reorder consumer: it registers a row renderer as a
 * <drupal-treegrid> provider and, on each upgraded element, instantiates the
 * generic Drupal.treegridReorder controllers with this module's own CSS class
 * names. It exists so the reorder gesture JS is tested within treegrid_reorder
 * itself, with no other consuming module involved.
 */

((Drupal, drupalSettings, once) => {
  const rr = Drupal.treegridReorder;
  const { rowOf, setRowLevel, childRows, descendantRows } =
    Drupal.treegrid.treeNav;

  // This consumer's CSS class/selector names, handed to the generic gesture
  // controllers so they carry no markup knowledge of their own.
  const REORDER_CONFIG = {
    handleSelector: '.reorder-test-handle',
    lockedClass: 'reorder-test-locked',
    draggingClass: 'reorder-test-dragging',
    grabbedClass: 'reorder-test-grabbed',
    lineClass: 'reorder-test-drop-indicator',
    dropAsChildClass: 'reorder-test-drop-as-child',
    titleSelector: '.reorder-test-title',
    rowControlsSelector: '.reorder-test-control',
    flashClass: 'reorder-test-flash',
    applyParentMetadata: (parentId) => applyParentMetadata(parentId),
    onCommitted: () => {},
  };

  /**
   * Builds the expand toggle for a parent, or the spacer for a leaf.
   *
   * @param {boolean} hasChildren
   *   Whether the row has children.
   *
   * @return {HTMLElement}
   *   The toggle button or the spacer.
   */
  function buildToggle(hasChildren) {
    if (!hasChildren) {
      const spacer = document.createElement('span');
      spacer.className = 'reorder-test-spacer';
      spacer.setAttribute('aria-hidden', 'true');
      return spacer;
    }
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'reorder-test-toggle';
    toggle.setAttribute('data-treegrid-toggle', '');
    toggle.tabIndex = -1;
    return toggle;
  }

  /**
   * Builds one <tr role="row"> from a child item.
   *
   * @param {object} item
   *   The child item.
   * @param {number} depth
   *   The 1-based depth.
   * @param {number} posInSet
   *   The 1-based position among siblings.
   * @param {number} setSize
   *   The number of siblings.
   *
   * @return {HTMLTableRowElement}
   *   The row element.
   */
  function buildRow(item, depth, posInSet, setSize) {
    const tr = document.createElement('tr');
    tr.setAttribute('role', 'row');
    tr.dataset.id = item.id;
    tr.dataset.parentId = item.parent_id ?? '';
    tr.dataset.hasChildren = String(item.has_children);
    if (item.movable !== null && item.movable !== undefined) {
      tr.dataset.movable = String(item.movable);
    }
    tr.setAttribute('aria-posinset', String(posInSet));
    tr.setAttribute('aria-setsize', String(setSize));
    if (item.has_children) {
      tr.setAttribute('aria-expanded', 'false');
    }
    tr.setAttribute('tabindex', '-1');

    const cell = document.createElement('td');
    cell.setAttribute('role', 'gridcell');
    cell.dataset.treegridNameCell = '';
    const handle = document.createElement('span');
    handle.className = 'reorder-test-handle';
    handle.setAttribute('role', 'button');
    handle.tabIndex = -1;
    const title = document.createElement('span');
    title.className = 'reorder-test-title';
    title.textContent = item.title;
    const control = document.createElement('input');
    control.type = 'checkbox';
    control.className = 'reorder-test-control';
    control.dataset.treegridControl = '';
    cell.appendChild(handle);
    cell.appendChild(buildToggle(item.has_children));
    cell.appendChild(title);
    cell.appendChild(control);
    tr.appendChild(cell);

    setRowLevel(tr, depth);
    return tr;
  }

  /**
   * Syncs a parent row's toggle and open state to its current children.
   *
   * @param {string} parentId
   *   The parent id, or '' for the top level.
   */
  function applyParentMetadata(parentId) {
    if (parentId === '') {
      return;
    }
    const row = document.querySelector(
      `tr[role="row"][data-id="${CSS.escape(parentId)}"]`,
    );
    if (!row) {
      return;
    }
    const hasChildren = childRows(row).length > 0;
    row.dataset.hasChildren = String(hasChildren);
    const cell = row.querySelector('[data-treegrid-name-cell]');
    const toggle = cell.querySelector(
      '.reorder-test-toggle, .reorder-test-spacer',
    );
    const isButton = toggle.tagName === 'BUTTON';
    if (hasChildren) {
      row.setAttribute('aria-expanded', 'true');
      if (!isButton) {
        toggle.replaceWith(buildToggle(true));
      }
    } else {
      row.removeAttribute('aria-expanded');
      if (isButton) {
        toggle.replaceWith(buildToggle(false));
      }
      descendantRows(row).forEach((descendant) => descendant.remove());
      delete row.dataset.treegridLoaded;
    }
  }

  /**
   * Builds a fragment of sibling rows.
   *
   * @param {Array<object>} items
   *   The child items.
   * @param {number} depth
   *   The depth to render at.
   *
   * @return {DocumentFragment}
   *   The rows.
   */
  function buildRows(items, depth) {
    const fragment = document.createDocumentFragment();
    const setSize = items.length;
    items.forEach((item, i) => {
      fragment.appendChild(buildRow(item, depth, i + 1, setSize));
    });
    return fragment;
  }

  /**
   * Wires the reorder gestures onto an upgraded <drupal-treegrid>.
   *
   * @param {HTMLElement} element
   *   The connected element (element.tree / element.expand are set).
   */
  function wireFeatures(element) {
    const { tree, expand } = element;
    const { moveUrl } = drupalSettings.treegridReorderTest ?? {};
    if (!moveUrl) {
      return;
    }
    const getToken = () => '';

    const indicator = new rr.DropIndicator(element, REORDER_CONFIG);
    const moveMode = new rr.MoveModeController(
      tree,
      moveUrl,
      expand,
      indicator,
      getToken,
      REORDER_CONFIG,
    );

    const onHandleTap = (row) => {
      if (moveMode.isActive()) {
        if (moveMode.isGrabbed(row)) {
          moveMode.commit();
        }
        return;
      }
      moveMode.tryGrab(row);
    };

    element.addClickHandler((event) => {
      if (moveMode.isActive() && moveMode.handlePointer(event)) {
        event.preventDefault();
        return true;
      }
      return false;
    });

    element.addKeyHandler((event) => {
      if (moveMode.isActive()) {
        if (moveMode.handleKey(event)) {
          event.preventDefault();
        }
        return true;
      }
      if (event.key === ' ') {
        const row = rowOf(event.target);
        if (row && moveMode.tryGrab(row)) {
          event.preventDefault();
          return true;
        }
        return false;
      }
      if (
        event.shiftKey &&
        (event.key === 'ArrowUp' || event.key === 'ArrowDown')
      ) {
        const row = rowOf(event.target);
        if (row) {
          rr.reorderSibling(row, event.key === 'ArrowUp' ? 'up' : 'down', {
            moveUrl,
            csrfToken: '',
            flashClass: REORDER_CONFIG.flashClass,
          });
          event.preventDefault();
          return true;
        }
      }
      return false;
    });

    const drag = new rr.DragController(
      tree,
      moveUrl,
      expand,
      '',
      indicator,
      () => moveMode.cancel(),
      (row) => onHandleTap(row),
      REORDER_CONFIG,
    );
    drag.attach();

    element.addEventListener('treegrid:disconnected', () => {
      moveMode.cancel();
    });

    // A key handler of the consumer's own, registered after the reorder ones,
    // so a test can see which key events reach it.
    element.addKeyHandler(() => {
      window.reorderTestKeyEvents = (window.reorderTestKeyEvents ?? 0) + 1;
      return false;
    });
  }

  // Register the renderer before the behavior runs; registerProvider adopts the
  // already-connected element and initializes it.
  Drupal.treegrid.registerProvider('treegrid_reorder_test', {
    buildRows,
    buildRow,
  });

  Drupal.behaviors.treegridReorderTest = {
    attach(context) {
      once(
        'treegrid-reorder-test',
        'drupal-treegrid[data-provider="treegrid_reorder_test"]',
        context,
      ).forEach(async (element) => {
        await element.ready;
        wireFeatures(element);
      });
    },
  };
})(Drupal, drupalSettings, once);
