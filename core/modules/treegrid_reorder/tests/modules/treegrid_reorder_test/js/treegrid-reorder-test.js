/**
 * @file
 * A minimal treegrid consumer that wires only the reorder gestures.
 *
 * Mirrors menu_tree_ui's menu-tree.js, stripped to reorder: it registers a row
 * renderer as a <drupal-treegrid> provider and, on each upgraded element,
 * instantiates the generic Drupal.treegridReorder controllers with this
 * module's own CSS class names. It exists so the reorder gesture JS is tested
 * within treegrid_reorder, not only through menu_tree_ui.
 */

((Drupal, drupalSettings, once) => {
  const rr = Drupal.treegridReorder;
  const { rowOf, setRowLevel } = Drupal.treegrid.treeNav;

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
    applyParentMetadata: () => {},
    onCommitted: () => {},
  };

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
    tr.dataset.movable = String(item.movable);
    tr.setAttribute('aria-posinset', String(posInSet));
    tr.setAttribute('aria-setsize', String(setSize));
    tr.setAttribute('tabindex', '-1');

    const cell = document.createElement('td');
    cell.setAttribute('role', 'gridcell');
    const handle = document.createElement('span');
    handle.className = 'reorder-test-handle';
    handle.setAttribute('role', 'button');
    handle.tabIndex = -1;
    const title = document.createElement('span');
    title.className = 'reorder-test-title';
    title.textContent = item.title;
    cell.appendChild(handle);
    cell.appendChild(title);
    tr.appendChild(cell);

    setRowLevel(tr, depth);
    return tr;
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
    if (!tree || !expand) {
      return;
    }
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

    element.addEventListener(
      'treegrid:disconnect',
      () => {
        moveMode.cancel();
        drag.detach();
        indicator.destroy();
      },
      { once: true },
    );
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
      ).forEach(wireFeatures);
    },
  };
})(Drupal, drupalSettings, once);
