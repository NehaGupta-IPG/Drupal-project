<?php

namespace Drupal\mongodb\modules\views;

use Drupal\views\Entity\View as CoreView;

/**
 * Overriding the entity class \Drupal\views\Entity\View.
 */
class View extends CoreView {

  /**
   * {@inheritdoc}
   */
  public function isInstallable() {
    // For MongoDB use $this->get('base_table') instead of $this->base_table.
    $table_definition = \Drupal::service('views.views_data')->get($this->get('base_table'));

    // Check whether the base table definition exists and contains a base table
    // definition. For example, taxonomy_views_data_alter() defines
    // node_field_data even if it doesn't exist as a base table.
    return $table_definition && isset($table_definition['table']['base']);
  }

  /**
   * {@inheritdoc}
   */
  public function get($key) {
    if (($key == 'base_table') && isset($this->mongodb_base_table)) {
      return $this->mongodb_base_table;
    }
    return parent::get($key);
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    $properties = parent::toArray();

    if (!empty($this->original_base_table)) {
      $properties['base_table'] = $this->original_base_table;
    }

    return $properties;
  }

}
