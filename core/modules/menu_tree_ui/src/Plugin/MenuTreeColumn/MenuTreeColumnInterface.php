<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Plugin\MenuTreeColumn;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Interface for MenuTreeColumn plugins.
 *
 * A column plugin contributes one column to the menu tree admin UI. The same
 * data shape must be renderable by both the Twig template (server-rendered top
 * level) and row-builder.js (JS-rendered lazy-loaded children). Cells declare
 * a 'type' key so both renderers know how to draw them.
 *
 * Supported cell types in v1:
 *   - 'toggle': a boolean switch, optionally interactive.
 *
 * Read-only columns return FALSE from handleUpdate().
 *
 * @api
 */
interface MenuTreeColumnInterface extends PluginInspectionInterface {

  /**
   * Returns the column header label.
   */
  public function getHeader(): TranslatableMarkup;

  /**
   * Returns structured cell data for a given tree element.
   *
   * Return an empty array to indicate that this column has no cell for the
   * given element (e.g. a column that only applies to parent items should
   * return [] for leaves). Callers filter empty returns and do not render a
   * cell at all — no wrapper div is emitted.
   *
   * When a non-empty array is returned it MUST contain a 'type' key. All
   * other keys are type-specific. Example for type 'toggle':
   * @code
   * [
   *   'type'        => 'toggle',
   *   'value'       => bool,
   *   'label'       => string,   // aria-label for the control
   *   'interactive' => bool,     // FALSE → render as read-only indicator
   *   'link_id'     => string,   // plugin ID sent back on update
   * ]
   * @endcode
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement $element
   *   The tree element being rendered.
   *
   * @return array<string, mixed>
   *   Cell data array with a 'type' key, or [] to skip this cell entirely.
   */
  public function getCellData(MenuLinkTreeElement $element): array;

  /**
   * Applies a value change to the underlying entity.
   *
   * Called by MenuTreeController::columnUpdate() when the client POSTs a
   * toggle interaction. Return FALSE for read-only columns — the controller
   * will respond with a 400 rather than saving. Implementations should also
   * return FALSE when $entity is not the expected type.
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
