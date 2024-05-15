<?php

declare(strict_types=1);

namespace Drupal\options;

use Drupal\Core\TypedData\TypedData;
use Drupal\options\Plugin\Field\FieldType\ListItemBase;

class OptionValueLabel extends TypedData {

  /**
   * Option value label.
   */
  protected ?string $valueLabel = NULL;

  /**
   * {@inheritdoc}
   */
  public function getValue(): ?string {
    if ($this->valueLabel !== NULL) {
      return $this->valueLabel;
    }

    $item = $this->getParent();
    assert($item instanceof ListItemBase);
    $value = $item->getValue()['value'];

    // Avoid doing unnecessary work on empty strings.
    if (empty($value) && $value !== '0') {
      $this->valueLabel = '';
    }
    else {
      $options = $item->getSettableOptions();
      $this->valueLabel = $options[$value];
    }
    return $this->valueLabel;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE): void {
    $this->valueLabel = $value;
    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

}
