/**
 * @file
 * A minimal treegrid consumer that exercises the spine in isolation.
 *
 * Registers a row renderer as a <drupal-treegrid> provider so the element,
 * lazy expand, keyboard navigation, and the cell-renderer registry can be
 * tested within the treegrid module rather than only through menu_tree_ui. It
 * adds no behavior of its own: the spine (element, tree-nav, keyboard, expand,
 * cells) is all treegrid's. Column cells go through Drupal.treegrid.renderCell
 * so the checkbox/markup renderers are covered on lazily-built rows.
 */

((Drupal) => {
  const { setRowLevel } = Drupal.treegrid.treeNav;

  /**
   * Builds one <tr role="row"> from a child item, matching the server markup.
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
    tr.setAttribute('aria-posinset', String(posInSet));
    tr.setAttribute('aria-setsize', String(setSize));
    if (item.has_children) {
      tr.setAttribute('aria-expanded', 'false');
    }
    tr.setAttribute('tabindex', '-1');

    const name = document.createElement('td');
    name.setAttribute('role', 'gridcell');
    name.className = 'tgu-name';
    if (item.has_children) {
      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'tgu-toggle';
      toggle.setAttribute('data-treegrid-toggle', '');
      toggle.tabIndex = -1;
      toggle.setAttribute('aria-label', `Toggle ${item.id}`);
      toggle.textContent = '▸';
      name.appendChild(toggle);
    } else {
      const spacer = document.createElement('span');
      spacer.className = 'tgu-toggle-spacer';
      spacer.setAttribute('aria-hidden', 'true');
      name.appendChild(spacer);
    }
    const title = document.createElement('span');
    title.className = 'tgu-title';
    title.textContent = item.title;
    name.appendChild(title);
    tr.appendChild(name);

    Object.entries(item.columns).forEach(([colId, cell]) => {
      const td = document.createElement('td');
      td.setAttribute('role', 'gridcell');
      td.dataset.column = colId;
      const widget = Drupal.treegrid.renderCell(cell, {
        colId,
        rowId: item.id,
      });
      if (widget) {
        td.appendChild(widget);
      }
      tr.appendChild(td);
    });

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

  Drupal.treegrid.registerProvider('treegrid_test_ui', { buildRows, buildRow });
})(Drupal);
