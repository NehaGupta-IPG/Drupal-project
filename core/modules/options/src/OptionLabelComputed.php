<?php

namespace Drupal\options;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\TypedData\TypedData;

/**
 * A computed property for option labels.
 */
class OptionLabelComputed extends TypedData {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $item = $this->getParent();
    if (!$item instanceof FieldItemInterface) {
      throw new \LogicException('The parent item must be a field item.');
    }
    $field_storage = $item->getFieldDefinition()->getFieldStorageDefinition();
    $main_property = $field_storage->getMainPropertyName();
    if (!$options_provider = $field_storage->getOptionsProvider($main_property, $item->getEntity())) {
      throw new \LogicException('The field storage definition must have an options provider.');
    }
    return $options_provider->getPossibleOptions()[$item->get($main_property)->getValue()] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE) {
    // Is read-only.
  }

}
