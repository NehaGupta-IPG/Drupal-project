<?php

namespace Drupal\mongodb\Plugin\views\sort;

/**
 * Trait for overriding the class \Drupal\views\Plugin\views\sort\Date.
 */
trait DateTrait {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    switch ($this->options['granularity']) {
      case 'second':
      default:
        $this->query->addOrderBy($this->tableAlias, $this->realField, $this->options['order']);
        return;

      case 'minute':
        $formula = $this->getDateFormat('YmdHi');
        break;

      case 'hour':
        $formula = $this->getDateFormat('YmdH');
        break;

      case 'day':
        $formula = $this->getDateFormat('Ymd');
        break;

      case 'month':
        $formula = $this->getDateFormat('Ym');
        break;

      case 'year':
        $formula = $this->getDateFormat('Y');
        break;
    }

    // Add the field.
    $placeholder = $this->placeholder();
    if ($this->getPluginId() == 'datetime') {
      $this->query->addDateStringFormattedField($placeholder, $this->realField, $formula);
    }
    else {
      $this->query->addDateDateFormattedField($placeholder, $this->realField, $formula);
    }
    $this->query->addOrderBy($this->tableAlias, $placeholder, $this->options['order']);
  }

}
