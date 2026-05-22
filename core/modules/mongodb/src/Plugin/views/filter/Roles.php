<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\user\Plugin\views\filter\Roles as CoreRoles;

/**
 * Overriding the views filter plugin "user_roles".
 */
class Roles extends CoreRoles {

  /**
   * The MongoDB field.
   *
   * @var string
   */
  protected $mongodbField;

  /**
   * {@inheritdoc}
   */
  public function query() {
    // The role field is located in an embedded table.
    $this->realField = 'user_translations.user_translations__roles.roles_target_id';

    if ($this->table == $this->view->storage->get('base_table')) {
      $this->mongodbField = $this->realField;
    }
    elseif (!empty($this->relationship)) {
      $this->mongodbField = "$this->relationship.$this->realField";
    }
    else {
      // Throw an exception.
      $this->mongodbField = $this->realField;
    }

    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}();
    }
  }

}
