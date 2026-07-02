/**
 * @file
 * The treegrid cell-renderer registry and built-in cell widgets.
 *
 * A column plugin's getCellData() returns a cell data array with a 'type' key
 * (see TreegridColumnInterface). Each type has a JS renderer registered here
 * that turns the cell data into the widget element a row builder drops into a
 * gridcell. The Twig template renders the server-side top level by hand and
 * MUST emit the same markup — keep the two in lockstep when changing a widget.
 *
 * treegrid ships:
 *  - 'checkbox': a boolean control. Non-interactive cells render disabled;
 *    interactive ones carry data-column-id / data-link-id so the consumer's
 *    column-update endpoint can persist the change — treegrid ships the cell
 *    type, not the route or controller.
 *  - 'markup': an opaque server-rendered HTML string, injected verbatim — the
 *    escape hatch for cells a client renderer cannot reproduce (e.g. a themed
 *    dropbutton). It MUST be trusted server-rendered markup: it is written via
 *    innerHTML, so user-supplied input in a markup cell is an XSS vector.
 *
 * Exposes Drupal.treegrid.registerCellRenderer and Drupal.treegrid.renderCell.
 */

((Drupal) => {
  Drupal.treegrid = Drupal.treegrid || {};

  const renderers = {};

  /**
   * Registers a cell renderer for a cell 'type'.
   *
   * @param {string} type
   *   The cell type, matched against a cell data array's 'type' key.
   * @param {function(object, object): (HTMLElement|null)} renderer
   *   Builds the widget from (cell, context); context carries
   *   { colId, rowId }. Returns the element to insert, or null to render
   *   nothing.
   */
  Drupal.treegrid.registerCellRenderer = (type, renderer) => {
    renderers[type] = renderer;
  };

  /**
   * Renders one cell's widget by dispatching on its 'type'.
   *
   * @param {object} cell
   *   The cell data array; must carry a 'type' key.
   * @param {object} context
   *   { colId, rowId } naming the owning column and row.
   *
   * @return {HTMLElement|null}
   *   The widget element, or null for an unregistered type — the caller then
   *   renders an empty cell, so an older client tolerates a new cell type.
   */
  Drupal.treegrid.renderCell = (cell, context) => {
    const renderer = renderers[cell.type];
    return renderer ? renderer(cell, context) : null;
  };

  // Boolean control. Native checkbox: the browser owns the checked state and
  // its keyboard/AT behavior, so the consumer's handler only reads .checked
  // and persists.
  Drupal.treegrid.registerCellRenderer('checkbox', (cell, { colId, rowId }) => {
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.className = 'treegrid-cell-checkbox';
    input.checked = cell.value;
    input.setAttribute('aria-label', cell.label);
    if (!cell.interactive) {
      input.disabled = true;
    }
    input.dataset.columnId = colId;
    input.dataset.linkId = rowId;
    // An interactive cell control joins the active row's roving tab order.
    input.dataset.treegridControl = '';
    return input;
  });

  // Opaque server-rendered markup, injected verbatim.
  Drupal.treegrid.registerCellRenderer('markup', (cell) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'treegrid-cell-markup';
    // cell.html must be trusted server-rendered markup (see the @file note);
    // never a client- or user-supplied string.
    wrapper.innerHTML = cell.html;
    return wrapper;
  });
})(Drupal);
