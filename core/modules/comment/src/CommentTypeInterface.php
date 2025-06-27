<?php

namespace Drupal\comment;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a comment type entity.
 */
interface CommentTypeInterface extends ConfigEntityInterface {

  /**
   * Returns the comment type description.
   *
   * @return string
   *   The comment-type description.
   */
  public function getDescription();

  /**
   * Sets the description of the comment type.
   *
   * @param string $description
   *   The new description.
   *
   * @return $this
   */
  public function setDescription($description);

  /**
   * Returns the comment type form heading.
   *
   * @return string
   *   The comment-type form heading.
   */
  public function getFormHeading();

  /**
   * Sets the form heading of the comment type.
   *
   * @param string $form_heading
   *   The new form heading.
   *
   * @return $this
   */
  public function setFormHeading($form_heading);

  /**
   * Gets the target entity type id for this comment type.
   *
   * @return string
   *   The target entity type id.
   */
  public function getTargetEntityTypeId();

}
