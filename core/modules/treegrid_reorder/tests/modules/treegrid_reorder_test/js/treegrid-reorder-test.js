/**
 * @file
 * A minimal treegrid consumer that turns on reordering.
 *
 * It registers a row renderer as a <drupal-treegrid> provider and, from a
 * behavior, calls Drupal.treegridReorder.attach() on each element, following
 * the documented pattern. It exists so the reorder JS is tested within
 * treegrid_reorder itself, with no other consuming module involved.
 */

((Drupal, once) => {
  const { setRowLevel } = Drupal.treegrid.treeNav;

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
   *
   * @return {HTMLTableRowElement}
   *   The row element.
   */
  function buildRow(item, depth) {
    const tr = document.createElement('tr');
    tr.setAttribute('role', 'row');
    tr.dataset.id = item.id;
    tr.dataset.parentId = item.parent_id ?? '';
    tr.dataset.hasChildren = String(item.has_children);
    if (item.movable !== null && item.movable !== undefined) {
      tr.dataset.movable = String(item.movable);
    }
    if (item.has_children) {
      tr.setAttribute('aria-expanded', 'false');
    }
    tr.setAttribute('tabindex', '-1');

    const cell = document.createElement('td');
    cell.setAttribute('role', 'gridcell');
    cell.dataset.treegridNameCell = '';
    const handle = document.createElement('span');
    handle.className = 'reorder-test-handle';
    handle.setAttribute('data-treegrid-reorder-handle', '');
    handle.setAttribute('role', 'button');
    handle.tabIndex = -1;
    const title = document.createElement('span');
    title.className = 'reorder-test-title';
    title.setAttribute('data-treegrid-reorder-title', '');
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
   * Creates the callback that syncs a parent row's open state for one tree.
   *
   * The row's data-has-children decides which of the toggle and the spacer
   * shows (see the stylesheet).
   *
   * @param {HTMLElement} element
   *   The <drupal-treegrid> element the callback serves.
   *
   * @return {function(string): void}
   *   The callback, taking the parent id.
   */
  function createParentMetadataUpdater(element) {
    return (parentId) => {
      if (parentId === '') {
        return;
      }
      const row = element.tree.querySelector(
        `tr[role="row"][data-id="${CSS.escape(parentId)}"]`,
      );
      if (!row) {
        return;
      }
      if (row.dataset.hasChildren === 'true') {
        row.setAttribute('aria-expanded', 'true');
      } else {
        row.removeAttribute('aria-expanded');
        element.clearChildren(row);
      }
    };
  }

  const reorders = new WeakMap();

  // ?delay_provider=<ms> registers the provider late, so a test can attach
  // reordering to an element that is not ready yet.
  const delay = Number(
    new URLSearchParams(window.location.search).get('delay_provider'),
  );
  const registerProvider = () =>
    Drupal.treegrid.registerProvider('treegrid_reorder_test', { buildRow });
  if (delay > 0) {
    window.setTimeout(registerProvider, delay);
  } else {
    registerProvider();
  }

  Drupal.behaviors.treegridReorderTest = {
    attach(context) {
      once(
        'treegrid-reorder-test',
        'drupal-treegrid[data-provider="treegrid_reorder_test"]',
        context,
      ).forEach(async (element) => {
        reorders.set(
          element,
          Drupal.treegridReorder.attach(element, {
            applyParentMetadata: createParentMetadataUpdater(element),
            container: element.closest('.reorder-test-container'),
          }),
        );
        // A key handler of the consumer's own, registered after attach(), so a
        // test can see which key events reach it.
        element.addKeyHandler(() => {
          window.reorderTestKeyEvents = (window.reorderTestKeyEvents ?? 0) + 1;
          return false;
        });
        await element.ready;
        await Drupal.treegrid.csrfToken(element);
        if (!element.isConnected || !reorders.has(element)) {
          return;
        }
        element.setAttribute('data-test-reorder-wired', '');
      });
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once
        .remove(
          'treegrid-reorder-test',
          'drupal-treegrid[data-provider="treegrid_reorder_test"]',
          context,
        )
        .forEach((element) => {
          reorders.get(element)?.detach();
          reorders.delete(element);
        });
    },
  };
})(Drupal, once);
