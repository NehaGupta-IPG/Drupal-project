<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views\FieldAPIHandlerTrait;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Overriding the views filter plugin "list_field".
 */
class ListField extends ManyToOne {

  use FieldAPIHandlerTrait;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);

    $field_storage = $this->getFieldStorageDefinition();
    // Set valueOptions here so getValueOptions() will just return it.
    $this->valueOptions = options_allowed_values($field_storage);
  }

}
