<?php

declare(strict_types=1);

namespace Drupal\treegrid\Plugin\TreegridColumn;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Interface for TreegridColumn plugins.
 *
 * A column plugin contributes one column to a treegrid admin UI. The same cell
 * data shape must be renderable by both the Twig template (server-rendered top
 * level) and a JS cell renderer (lazy-loaded children). Cells declare a 'type'
 * key naming the widget; a matching JS renderer is registered with
 * Drupal.treegrid.registerCellRenderer(type, fn), and the Twig template draws
 * the same markup. treegrid ships these cell types:
 *   - 'checkbox': a boolean control, optionally interactive.
 *   - 'markup': an opaque server-rendered HTML string, injected verbatim. It
 *     MUST be trusted server-rendered markup — it is written through |raw and
 *     innerHTML, so user-supplied input in a markup cell is an XSS vector.
 *
 * Read-only columns return FALSE from handleUpdate().
 *
 * @api
 */
interface TreegridColumnInterface extends PluginInspectionInterface {

  /**
   * Returns the column header label.
   */
  public function getHeader(): TranslatableMarkup;

  /**
   * Returns structured cell data for a given row.
   *
   * Return an empty array to indicate that this column has no cell for the
   * given row (e.g. a column that only applies to parent items should return
   * [] for leaves). Callers filter empty returns and do not render a cell at
   * all — no wrapper is emitted.
   *
   * When a non-empty array is returned it MUST contain a 'type' key naming the
   * cell widget. All other keys are type-specific. Example for type 'checkbox':
   * @code
   * [
   *   'type'        => 'checkbox',
   *   'value'       => bool,
   *   'label'       => string,   // aria-label for the control
   *   'interactive' => bool,     // FALSE → render disabled
   *   'link_id'     => string,   // plugin ID sent back on update
   * ]
   * @endcode
   *
   * @param object $context
   *   The row context. Its concrete type is defined by the consumer module
   *   that registers the column and calls this method — treegrid passes it
   *   through opaquely. For menu_tree_ui it is a MenuLinkTreeElement.
   *
   * @return array<string, mixed>
   *   Cell data array with a 'type' key, or [] to skip this cell entirely.
   */
  public function getCellData(object $context): array;

  /**
   * Applies a value change to the underlying entity.
   *
   * Called by the consumer's column-update controller when the client POSTs an
   * interactive cell change. treegrid defines this contract but ships no route
   * or controller for it — the consumer owns the persistence endpoint (e.g.
   * menu_tree_ui's MenuTreeController::columnUpdate). Return FALSE for
   * read-only columns — the controller responds with a 400 rather than saving.
   * Implementations should also return FALSE when $entity is not the expected
   * type.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity backing the link that was toggled.
   * @param mixed $value
   *   The new value sent from the client (e.g. TRUE/FALSE for a toggle).
   *
   * @return bool
   *   TRUE if the update was applied, FALSE if this column is read-only or
   *   the entity type is not supported by this column.
   */
  public function handleUpdate(EntityInterface $entity, mixed $value): bool;

}
