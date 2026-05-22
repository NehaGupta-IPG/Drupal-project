<?php

namespace Drupal\mongodb\Plugin\views\row;

/**
 * Overrides the views row plugin "entity:node".
 */
class NodeRow extends EntityRow {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['view_mode']['default'] = 'teaser';

    return $options;
  }

}
