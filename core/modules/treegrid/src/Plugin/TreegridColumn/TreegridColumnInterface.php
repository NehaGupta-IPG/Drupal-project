<?php

declare(strict_types=1);

namespace Drupal\treegrid\Plugin\TreegridColumn;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Interface for TreegridColumn plugins.
 *
 * A column plugin contributes one column to a treegrid admin UI. Each cell is a
 * render array, so the first page of rows and rows loaded later render it the
 * same way, with its attached assets. treegrid ships the
 * 'treegrid_cell_checkbox' theme hook for a boolean control, optionally
 * interactive.
 *
 * A column-update response's 'message' is safe HTML, escaped by the server,
 * announced as is.
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
   * Builds the render array for this column's cell in a row.
   *
   * @param object $context
   *   The row context. Its concrete type is defined by the consumer module
   *   that registers the column and calls this method — treegrid passes it
   *   through opaquely (e.g. a consumer editing taxonomy terms would pass a
   *   term tree element).
   *
   * @return array
   *   A render array for the cell's content, or an empty array for an empty
   *   cell.
   */
  public function buildCell(object $context): array;

  /**
   * Applies a value change to the row identified by $row_id.
   *
   * Called by the consumer's column-update controller when the client POSTs an
   * interactive cell change. treegrid defines this contract but ships no route
   * or controller for it — the consumer owns the persistence endpoint. Return
   * FALSE for read-only columns — the controller responds with a 400 rather
   * than saving.
   * Implementations should also return FALSE when the row is not one this
   * column can update.
   *
   * @param string $row_id
   *   The row identifier, the row's data-id. Its meaning (e.g. an entity ID or
   *   plugin ID) is defined by the consumer module that registers the column;
   *   treegrid passes it through opaquely.
   * @param mixed $value
   *   The new value sent from the client (e.g. TRUE/FALSE for a toggle).
   *
   * @return bool
   *   TRUE if the update was applied, FALSE if this column is read-only or
   *   the row is not supported by this column.
   */
  public function handleUpdate(string $row_id, mixed $value): bool;

}
