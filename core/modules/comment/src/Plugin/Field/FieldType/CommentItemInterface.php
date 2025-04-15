<?php

namespace Drupal\comment\Plugin\Field\FieldType;

/**
 * Interface definition for Comment items.
 */
interface CommentItemInterface {

  /**
   * Comments for this entity are hidden.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0.
   * Use \Drupal\comment\CommentingStatus::Hidden instead.
   *
   * @see https://www.drupal.org/project/drupal/issues/3518993
   */
  const HIDDEN = 0;

  /**
   * Comments for this entity are closed.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0.
   * Use \Drupal\comment\CommentingStatus::Closed instead.
   *
   * @see https://www.drupal.org/project/drupal/issues/3518993
   */
  const CLOSED = 1;

  /**
   * Comments for this entity are open.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0.
   * Use \Drupal\comment\CommentingStatus::Open instead.
   *
   * @see https://www.drupal.org/project/drupal/issues/3518993
   */
  const OPEN = 2;

  /**
   * Comment form should be displayed on a separate page.
   */
  const FORM_SEPARATE_PAGE = 0;

  /**
   * Comment form should be shown below post or list of comments.
   */
  const FORM_BELOW = 1;

}
