/**
 * @file
 * Builds row DOM from the JSON children response.
 *
 * NB: Mirrors templates/menu-tree-ui-row.html.twig. If you change one, change
 * the other — there is no shared template source.
 *
 * Mirrors the JSON contract MenuTreeController::serializeElement() emits:
 *   ChildItem: {
 *     id: string, title: string, enabled: boolean, has_children: boolean,
 *     depth: number, url: string, weight: number, movable: boolean,
 *     operations_html: string,
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
 * Exposes Drupal.menuTreeUi.buildGroup and Drupal.menuTreeUi.buildRow.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};

  /**
   * Builds the drag handle span for a row.
   *
   * Movable rows (content links and static links that persist parent/weight
   * overrides) get a grab handle; the rest render a non-grab spacer. The server
   * decides via \Drupal\menu_tree_ui\MenuLinkMovability and sends item.movable.
   *
   * @param {object} item
   *   The ChildItem.
   *
   * @return {HTMLSpanElement}
   *   The handle element.
   */
  function buildHandle(item) {
    const handle = document.createElement('span');
    if (item.movable) {
      handle.className = 'menu-tree-ui-row__handle';
      handle.setAttribute('role', 'button');
      handle.tabIndex = -1;
      handle.setAttribute('aria-label', `Drag to reorder ${item.title}`);
      handle.title = 'Drag to reorder';
      handle.textContent = '⋮⋮';
    } else {
      handle.className =
        'menu-tree-ui-row__handle menu-tree-ui-row__handle--locked';
      handle.setAttribute('aria-hidden', 'true');
      handle.textContent = '·';
    }
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
    toggle.tabIndex = -1;
    toggle.setAttribute('aria-label', `Toggle children of ${item.title}`);
    const chevron = document.createElement('span');
    chevron.setAttribute('aria-hidden', 'true');
    chevron.className = 'menu-tree-ui-row__chevron';
    chevron.textContent = '▸';
    toggle.appendChild(chevron);
    return toggle;
  }

  /**
   * Builds the link title span.
   *
   * @param {string} text
   *   The link title.
   *
   * @return {HTMLSpanElement}
   *   The title element.
   */
  function buildTitle(text) {
    const title = document.createElement('span');
    title.className = 'menu-tree-ui-row__title';
    title.textContent = text;
    return title;
  }

  /**
   * Builds a small status badge (e.g. "disabled").
   *
   * @param {string} text
   *   The badge text.
   *
   * @return {HTMLSpanElement}
   *   The badge element.
   */
  function buildBadge(text) {
    const badge = document.createElement('span');
    badge.className = 'menu-tree-ui-row__badge';
    badge.textContent = text;
    return badge;
  }

  /**
   * Builds one column cell div.
   *
   * Only the 'toggle' cell type is supported in v1. Unknown types are silently
   * skipped so future column types can be added without breaking old JS.
   *
   * @param {string} colId
   *   The column plugin ID.
   * @param {object} cell
   *   The cell data.
   * @param {string} rowId
   *   The owning row's link ID.
   *
   * @return {HTMLDivElement}
   *   The cell wrapper.
   */
  function buildColumnCell(colId, cell, rowId) {
    const wrapper = document.createElement('div');
    wrapper.className = 'menu-tree-ui-row__column';
    wrapper.dataset.column = colId;

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
      sr.textContent = cell.value ? 'On' : 'Off';
      btn.appendChild(sr);
      wrapper.appendChild(btn);
    }

    return wrapper;
  }

  /**
   * Builds one <li role="treeitem"> row from a ChildItem.
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
   * @return {HTMLLIElement}
   *   The row element.
   */
  function buildRow(item, depth, posInSet, setSize) {
    const li = document.createElement('li');
    li.setAttribute('role', 'treeitem');
    li.dataset.id = item.id;
    li.dataset.hasChildren = String(item.has_children);
    li.dataset.movable = String(item.movable);
    li.setAttribute('aria-level', String(depth));
    li.setAttribute('aria-posinset', String(posInSet));
    li.setAttribute('aria-setsize', String(setSize));
    if (item.has_children) {
      li.setAttribute('aria-expanded', 'false');
    }
    li.tabIndex = -1;
    li.className = [
      'menu-tree-ui-row',
      item.enabled ? '' : 'menu-tree-ui-row--disabled',
      item.has_children ? 'menu-tree-ui-row--parent' : 'menu-tree-ui-row--leaf',
    ]
      .filter(Boolean)
      .join(' ');

    const content = document.createElement('div');
    content.className = 'menu-tree-ui-row__content';
    content.style.paddingInlineStart = `${(depth - 1) * 1.25}rem`;

    content.appendChild(buildHandle(item));
    content.appendChild(buildToggle(item));
    content.appendChild(buildTitle(item.title));

    if (!item.enabled) {
      content.appendChild(buildBadge('disabled'));
    }

    // Column cells.
    Object.entries(item.columns).forEach(([colId, cell]) => {
      content.appendChild(buildColumnCell(colId, cell, item.id));
    });

    // Operations dropbutton. The markup is rendered server-side with the active
    // admin theme (see MenuTreeController::renderOperationsHtml) and injected
    // verbatim; Drupal.attachBehaviors upgrades it to a working dropbutton.
    if (item.operations_html) {
      const opsWrapper = document.createElement('div');
      opsWrapper.className = 'menu-tree-ui-row__operations';
      opsWrapper.innerHTML = item.operations_html;
      content.appendChild(opsWrapper);
    }

    li.appendChild(content);
    return li;
  }

  /**
   * Builds a <ul role="group"> populated with rows from items.
   *
   * @param {Array<object>} items
   *   The ChildItems.
   * @param {number} depth
   *   The depth at which to render the rows.
   *
   * @return {HTMLUListElement}
   *   The populated group.
   */
  function buildGroup(items, depth) {
    const ul = document.createElement('ul');
    ul.setAttribute('role', 'group');
    ul.className = 'menu-tree-ui__list';
    const setSize = items.length;
    items.forEach((item, i) => {
      ul.appendChild(buildRow(item, depth, i + 1, setSize));
    });
    return ul;
  }

  Drupal.menuTreeUi.buildGroup = buildGroup;
  Drupal.menuTreeUi.buildRow = buildRow;
})(Drupal);
