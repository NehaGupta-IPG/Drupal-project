/**
 * @file
 * Builds row DOM from the JSON children response.
 *
 * NB: Mirrors templates/menu-tree-ui-row.html.twig. If you change one, change
 * the other — there is no shared template source. Rows are flat <tr role="row">
 * elements (see js/tree-nav.js); children are inserted as siblings after their
 * parent, never nested.
 *
 * Mirrors the JSON contract MenuTreeController::serializeElement() emits:
 *   ChildItem: {
 *     id: string, parent_id: string, title: string, enabled: boolean,
 *     has_children: boolean, depth: number, url: string, weight: number,
 *     movable: boolean,
 *     columns: Object<string, cell>
 *   }
 *
 * Each cell carries a 'type' and is drawn by the treegrid cell renderer
 * registered for it (see treegrid/js/cells.js). The operations dropbutton is
 * the last column — a 'markup' cell whose html is pre-rendered server-side with
 * the active admin theme, injected verbatim, and upgraded by
 * Drupal.attachBehaviors (see OperationsColumn for why it isn't rebuilt here).
 *
 * Registers the menu row renderer as a treegrid provider, and exposes
 * Drupal.menuTreeUi.buildToggle for the reorder feature's post-move refresh.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  const { setRowLevel } = Drupal.treegrid.treeNav;

  /**
   * Builds the drag handle for a row.
   *
   * The handle is a pointer/drag affordance only: pointer drag arms on it (see
   * the treegrid_reorder controller's handleSelector) and a click picks the row
   * up. Keyboard users reorder from the row itself (Space), so the handle is
   * hidden from assistive technology (aria-hidden) and the row's aria-label
   * carries the name. The server decides movability via
   * \Drupal\menu_tree_ui\MenuLinkMovability and sends item.movable; non-movable
   * rows keep the locked class so the controller skips them. It lives inside
   * the name cell so it indents with the title (see the row template).
   *
   * @param {object} item
   *   The ChildItem.
   *
   * @return {HTMLSpanElement}
   *   The handle element.
   */
  function buildHandle(item) {
    const handle = document.createElement('span');
    handle.setAttribute('aria-hidden', 'true');
    // The reorder feature finds the drag handle by this hook.
    handle.setAttribute('data-treegrid-reorder-handle', '');

    const icon = document.createElement('span');
    icon.className = 'menu-tree-ui-row__handle-icon';

    if (item.movable) {
      handle.className = 'menu-tree-ui-row__handle';
      handle.title = Drupal.t('Drag to reorder, or click to pick up');
    } else {
      handle.className =
        'menu-tree-ui-row__handle menu-tree-ui-row__handle--locked';
      icon.textContent = '·';
    }

    handle.appendChild(icon);
    return handle;
  }

  /**
   * Builds the expand/collapse toggle button, or a spacer for leaves.
   *
   * @param {object} item
   *   The ChildItem.
   *
   * @return {HTMLElement}
   *   A button for parents, or a spacer span for leaves.
   */
  function buildToggle(item) {
    if (!item.has_children) {
      const spacer = document.createElement('span');
      spacer.className =
        'menu-tree-ui-row__toggle menu-tree-ui-row__toggle--spacer';
      spacer.setAttribute('aria-hidden', 'true');
      return spacer;
    }
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'menu-tree-ui-row__toggle';
    // The generic <drupal-treegrid> element finds the disclosure control by this
    // hook, so it needs no knowledge of menu CSS classes.
    toggle.setAttribute('data-treegrid-toggle', '');
    // Operated from the row (Left/Right/Enter), not via Tab; mouse-clickable.
    toggle.tabIndex = -1;
    toggle.setAttribute(
      'aria-label',
      Drupal.t('Toggle children of @title', { '@title': item.title }),
    );
    const chevron = document.createElement('span');
    chevron.setAttribute('aria-hidden', 'true');
    chevron.className = 'menu-tree-ui-row__chevron';
    chevron.textContent = '▸';
    toggle.appendChild(chevron);
    return toggle;
  }

  /**
   * Builds the name cell — indent wrapper, handle, toggle, and title.
   *
   * @param {object} item
   *   The ChildItem.
   *
   * @return {HTMLTableCellElement}
   *   The name <td>.
   */
  function buildNameCell(item) {
    const cell = document.createElement('td');
    cell.setAttribute('role', 'gridcell');
    cell.className = 'menu-tree-ui-row__cell menu-tree-ui-row__cell--name';

    const name = document.createElement('div');
    name.className = 'menu-tree-ui-row__name';
    // The treegrid spine indents this cell (setRowLevel) via this hook.
    name.dataset.treegridNameCell = '';

    name.appendChild(buildHandle(item));
    name.appendChild(buildToggle(item));

    // Link the title to the menu link's destination (the classic admin
    // pattern); route-less links (<nolink> etc.) stay plain text.
    const title = document.createElement(item.linked ? 'a' : 'span');
    title.className = 'menu-tree-ui-row__title';
    // The reorder feature reads the row's visible name from this hook.
    title.setAttribute('data-treegrid-reorder-title', '');
    if (item.linked) {
      title.href = item.url;
      // The title link joins the active row's roving tab order.
      title.dataset.treegridControl = '';
    }
    title.textContent = item.title;
    name.appendChild(title);

    cell.appendChild(name);
    return cell;
  }

  /**
   * Builds one column cell <td>.
   *
   * The widget inside is built by the treegrid cell-renderer registered for
   * cell.type (see treegrid/js/cells.js). An unregistered type renders an empty
   * cell, so a new column type does not break an older client.
   *
   * @param {string} colId
   *   The column plugin ID.
   * @param {object} cell
   *   The cell data.
   * @param {string} rowId
   *   The owning row's link ID.
   *
   * @return {HTMLTableCellElement}
   *   The column <td>.
   */
  function buildColumnCell(colId, cell, rowId) {
    const td = document.createElement('td');
    td.setAttribute('role', 'gridcell');
    td.className = 'menu-tree-ui-row__cell menu-tree-ui-row__column';
    td.dataset.column = colId;

    const widget = Drupal.treegrid.renderCell(cell, { colId, rowId });
    if (widget) {
      td.appendChild(widget);
    }

    return td;
  }

  /**
   * Builds one <tr role="row"> row from a ChildItem.
   *
   * @param {object} item
   *   The ChildItem.
   * @param {number} depth
   *   The row's depth (1-based).
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
    tr.dataset.movable = String(item.movable);
    if (item.has_children) {
      tr.setAttribute('aria-expanded', 'false');
    }
    // The row's accessible name: the title, plus "(locked)" when it cannot be
    // reordered. An explicit name keeps a screen reader from flattening the
    // in-row controls into one utterance; per-column state is announced by each
    // cell's control on Tab, so this stays column-agnostic (see the template).
    tr.setAttribute(
      'aria-label',
      item.movable
        ? item.title
        : Drupal.t('@title (locked)', { '@title': item.title }),
    );
    // Lazy-loaded rows are never the initial tab stop; the active row is set as
    // the cursor moves (see treeNav.focusRow). aria-selected is added only while
    // a row is picked up for a move (see move-mode), so it is absent here.
    tr.setAttribute('tabindex', '-1');
    tr.className = [
      'menu-tree-ui-row',
      item.enabled ? '' : 'menu-tree-ui-row--disabled',
      item.has_children ? 'menu-tree-ui-row--parent' : 'menu-tree-ui-row--leaf',
    ]
      .filter(Boolean)
      .join(' ');

    tr.appendChild(buildNameCell(item));
    // aria-level + indent in one place (the name cell now exists in the row).
    setRowLevel(tr, depth);
    Object.entries(item.columns).forEach(([colId, cell]) => {
      tr.appendChild(buildColumnCell(colId, cell, item.id));
    });

    return tr;
  }

  // buildToggle is shared so menu-tree.js's applyParentMetadata can rebuild a
  // row's disclosure toggle after a move refresh without duplicating the markup
  // (and its translated aria-label). buildRow reaches the spine through the
  // registered provider object below, so it needs no namespace export.
  Drupal.menuTreeUi.buildToggle = buildToggle;

  // Register the menu renderer with the generic treegrid element. registerProvider
  // adopts any <drupal-treegrid data-provider="menu_tree_ui"> already connected
  // (the treegrid library loads, and the element upgrades, before this runs).
  Drupal.treegrid.registerProvider('menu_tree_ui', { buildRow });
})(Drupal);
