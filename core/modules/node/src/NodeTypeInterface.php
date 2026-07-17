<?php

namespace Drupal\node;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;

/**
 * Provides an interface defining a node type entity.
 */
interface NodeTypeInterface extends ConfigEntityInterface, RevisionableEntityBundleInterface {

  /**
   * Determines whether the node type is locked.
   *
   * @return string|false
   *   The module name that locks the type or FALSE.
   */
  public function isLocked();

  /**
   * Sets whether a new revision should be created by default.
   *
   * @param bool $new_revision
   *   TRUE if a new revision should be created by default.
   */
  public function setNewRevision($new_revision);

  /**
   * Gets the preview mode.
   *
   * @param bool $returnAsInt
   *   (deprecated) Whether to return an integer or enum value. The $returnAsInt
   *   parameter is deprecated in drupal:11.3.0 and is removed from
   *   drupal:13.0.0.
   *
   * @return \Drupal\node\NodePreviewMode|int
   *   Returns the enum case if $returnAsInt is FALSE, otherwise returns the
   *   integer equivalent.
   */
  public function getPreviewMode(bool $returnAsInt = TRUE);

  /**
   * Sets the preview mode.
   *
   * @param \Drupal\node\NodePreviewMode|int $preview_mode
   *   A NodePreviewMode case, or the integer equivalent. Passing an integer
   *   is deprecated.
   */
  public function setPreviewMode(NodePreviewMode|int $preview_mode);

  /**
   * Gets the help information.
   *
   * @return string
   *   The help information of this node type.
   */
  public function getHelp();

  /**
   * Gets the description.
   *
   * @return string
   *   The description of this node type.
   */
  public function getDescription();

}
