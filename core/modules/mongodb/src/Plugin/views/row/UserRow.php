<?php

namespace Drupal\mongodb\Plugin\views\row;

/**
 * Overrides the views row plugin "entity:user".
 */
class UserRow extends EntityRow {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['view_mode']['default'] = 'full';

    return $options;
  }

}
