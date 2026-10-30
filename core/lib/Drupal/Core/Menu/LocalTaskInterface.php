<?php

namespace Drupal\Core\Menu;

/**
 * Defines an interface for menu local tasks.
 *
 * Menu local tasks are typically rendered as navigation tabs above the content
 * region, though other presentations are possible. It is convention that the
 * titles of these tasks should be short verbs if possible.
 *
 * @see \Drupal\Core\Menu\LocalTaskManagerInterface
 */
interface LocalTaskInterface extends LocalLinkInterface {

  /**
   * Sets the active status.
   *
   * @param bool $active
   *   Sets whether this tab is active (e.g. a parent of the current tab).
   *
   * @return $this
   *   The called object for chaining.
   */
  public function setActive($active = TRUE);

  /**
   * Gets the active status.
   *
   * @return bool
   *   TRUE if the local task is active, FALSE otherwise.
   *
   * @see \Drupal\system\Plugin\MenuLocalTaskInterface::setActive()
   */
  public function getActive();

}
