<?php

namespace Drupal\comment;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\comment\Entity\CommentType;

/**
 * Provides dynamic permissions for different type comments.
 */
class CommentPermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of comment type permissions.
   *
   * @return array
   *   The comment type permissions.
   * @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function commentTypePermissions() {
    $perms = [];
    // Generate comment permissions for all comment types.
    foreach (CommentType::loadMultiple() as $type) {
      $perms += $this->buildPermissions($type);
    }
    return $perms;
  }

  /**
   * Returns a list of comment permissions for a given comment type.
   *
   * @param \Drupal\comment\Entity\CommentType $type
   *   The comment type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(CommentType $type) {
    $type_id = $type->id();
    $args = ['%type_name' => $type->label()];

    return [
      'access comments ' . $type_id => [
        'title' => $this->t('%type_name: View comments', $args),
      ],
      'post comments ' . $type_id => [
        'title' => $this->t('%type_name: Post comments', $args),
      ],
      'reply comments ' . $type_id => [
        'title' => $this->t('%type_name: Reply comments', $args),
      ],
      'skip comment approval ' . $type_id => [
        'title' => $this->t('%type_name: Skip comment approval', $args),
      ],
      'edit own comments ' . $type_id => [
        'title' => $this->t('%type_name: Edit own comments', $args),
      ],
    ];
  }

}
