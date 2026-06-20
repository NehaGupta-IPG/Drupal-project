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
 *     movable: boolean, operations_html: string,
 *     columns: Object<string, {type: 'toggle', value: boolean, label: string,
 *                              interactive: boolean, link_id: string}>
 *   }
 *
 * operations_html is the dropbutton, pre-rendered server-side with the active
 * admin theme so it matches the Twig-rendered top level under any theme. We
 * inject it verbatim and let Drupal.attachBehaviors upgrade it — see
 * MenuTreeController::renderOperationsHtml() for why the markup isn't rebuilt
 * here.
 *
 * Exposes Drupal.menuTreeUi.buildRows and Drupal.menuTreeUi.buildRow.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  const { setRowLevel } = Drupal.treegrid.treeNav;

  /**
   * Builds the drag handle for a row.
   *
   * The handle is the per-row keyboard focus target (a real tab stop), with a
   * visually-hidden level indicator. Movable rows grab to reorder; the rest are
   * focusable for navigation but aria-disabled. The server decides via
   * \Drupal\menu_tree_ui\MenuLinkMovability and sends item.movable. It lives
   * inside the name cell so it indents with the title (see the row template).
   *
   * @param {object} item
   *   The ChildItem.
   * @param {number} depth
   *   The row's 1-based depth.
   *
   * @return {HTMLSpanElement}
   *   The handle element.
   */
  function buildHandle(item, depth) {
    const handle = document.createElement('span');
    handle.setAttribute('role', 'button');
    handle.tabIndex = -1;

    const icon = document.createElement('span');
    icon.setAttribute('aria-hidden', 'true');
    icon.className = 'menu-tree-ui-row__handle-icon';

    const label = document.createElement('span');
    label.className = 'visually-hidden';

    if (item.movable) {
      handle.className = 'menu-tree-ui-row__handle';
      handle.title = Drupal.t('Drag to reorder');
      label.textContent = Drupal.t('Reorder @title, level @level', {
        '@title': item.title,
        '@level': depth,
      });
    } else {
      handle.className =
        'menu-tree-ui-row__handle menu-tree-ui-row__handle--locked';
      handle.setAttribute('aria-disabled', 'true');
      icon.textContent = '·';
      label.textContent = Drupal.t('@title, level @level (locked)', {
        '@title': item.title,
        '@level': depth,
      });
    }

    handle.appendChild(icon);
    handle.appendChild(label);
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
   * @param {number} depth
   *   The row's 1-based depth.
   *
   * @return {HTMLTableCellElement}
   *   The name <td>.
   */
  function buildNameCell(item, depth) {
    const cell = document.createElement('td');
    cell.setAttribute('role', 'gridcell');
    cell.className = 'menu-tree-ui-row__cell menu-tree-ui-row__cell--name';

    const name = document.createElement('div');
    name.className = 'menu-tree-ui-row__name';
    // The indent is applied by setRowLevel() once the row is assembled.

    name.appendChild(buildHandle(item, depth));
    name.appendChild(buildToggle(item));

    // Link the title to the menu link's destination (the classic admin
    // pattern); route-less links (<nolink> etc.) stay plain text.
    const title = document.createElement(item.linked ? 'a' : 'span');
    title.className = 'menu-tree-ui-row__title';
    if (item.linked) {
      title.href = item.url;
    }
    title.textContent = item.title;
    name.appendChild(title);

    cell.appendChild(name);
    return cell;
  }

  /**
   * Builds one column cell <td>.
   *
   * Only the 'toggle' cell type is supported in v1. Unknown types render an
   * empty cell so future column types can be added without breaking old JS.
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

    if (cell.type === 'toggle') {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.setAttribute('role', 'switch');
      btn.className = 'menu-tree-ui-row__column-toggle';
      btn.setAttribute('aria-checked', String(cell.value));
      btn.setAttribute('aria-label', cell.label);
      if (!cell.interactive) {
        btn.disabled = true;
      }
      btn.dataset.columnId = colId;
      btn.dataset.linkId = rowId;

      const sr = document.createElement('span');
      sr.className = 'visually-hidden';
      sr.textContent = cell.value ? Drupal.t('On') : Drupal.t('Off');
      btn.appendChild(sr);
      td.appendChild(btn);
    }

    return td;
  }

  /**
   * Builds the operations cell <td>.
   *
   * The dropbutton markup is rendered server-side with the active admin theme
   * (see MenuTreeController::renderOperationsHtml) and injected verbatim;
   * Drupal.attachBehaviors upgrades it to a working dropbutton.
   *
   * @param {object} item
   *   The ChildItem.
   *
   * @return {HTMLTableCellElement}
   *   The operations <td>.
   */
  function buildOperationsCell(item) {
    const cell = document.createElement('td');
    cell.setAttribute('role', 'gridcell');
    cell.className =
      'menu-tree-ui-row__cell menu-tree-ui-row__cell--operations';
    if (item.operations_html) {
      const wrapper = document.createElement('div');
      wrapper.className = 'menu-tree-ui-row__operations';
      wrapper.innerHTML = item.operations_html;
      cell.appendChild(wrapper);
    }
    return cell;
  }

  /**
   * Builds one <tr role="row"> row from a ChildItem.
   *
   * @param {object} item
   *   The ChildItem.
   * @param {number} depth
   *   The row's depth (1-based).
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
    if (item.has_children) {
      tr.setAttribute('aria-expanded', 'false');
    }
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

    tr.appendChild(buildNameCell(item, depth));
    // aria-level + indent in one place (the name cell now exists in the row).
    setRowLevel(tr, depth);
    Object.entries(item.columns).forEach(([colId, cell]) => {
      tr.appendChild(buildColumnCell(colId, cell, item.id));
    });
    tr.appendChild(buildOperationsCell(item));

    return tr;
  }

  /**
   * Builds a DocumentFragment of sibling rows from items.
   *
   * @param {Array<object>} items
   *   The ChildItems.
   * @param {number} depth
   *   The depth at which to render the rows.
   *
   * @return {DocumentFragment}
   *   The fragment of <tr> rows.
   */
  function buildRows(items, depth) {
    const fragment = document.createDocumentFragment();
    const setSize = items.length;
    items.forEach((item, i) => {
      fragment.appendChild(buildRow(item, depth, i + 1, setSize));
    });
    return fragment;
  }

  Drupal.menuTreeUi.buildRows = buildRows;
  Drupal.menuTreeUi.buildRow = buildRow;
  // Shared so drag/commit.js can rebuild a row's toggle after a refresh without
  // duplicating the markup (and its translated aria-label).
  Drupal.menuTreeUi.buildToggle = buildToggle;

  // Register the menu renderer with the generic treegrid element. registerProvider
  // adopts any <drupal-treegrid data-provider="menu_tree_ui"> already connected
  // (the treegrid library loads, and the element upgrades, before this runs).
  Drupal.treegrid.registerProvider('menu_tree_ui', { buildRows, buildRow });
})(Drupal);
