<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views_test_data\Plugin\views\filter\FilterTest as CoreFilterTest;

/**
 * Overriding the views filter plugin "test_filter".
 */
class FilterTest extends CoreFilterTest {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Call the parent if this option is enabled.
    if ($this->options['test_enable']) {
      $this->ensureMyTable();
      if ($this->table == $this->view->storage->get('base_table')) {
        $field = $this->realField;
      }
      else {
        $field = "$this->tableAlias.$this->realField";
      }
      $this->query->addCondition($this->options['group'], $field, $this->value, $this->operator);
    }
  }

}
