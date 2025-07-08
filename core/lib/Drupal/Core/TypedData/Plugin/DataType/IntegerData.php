<?php

namespace Drupal\Core\TypedData\Plugin\DataType;

use Drupal\Core\Serialization\Attribute\JsonSchema;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\PrimitiveBase;
use Drupal\Core\TypedData\Type\IntegerInterface;

/**
 * The integer data type.
 *
 * The plain value of an integer is a regular PHP integer. For setting the value
 * any PHP variable that casts to an integer may be passed.
 */
#[DataType(
  id: "integer",
  label: new TranslatableMarkup("Integer")
)]
class IntegerData extends PrimitiveBase implements IntegerInterface {

  /**
   * {@inheritdoc}
   */
  #[JsonSchema(['type' => 'integer'])]
  public function getCastedValue() {
    // Special handling for integers since the configuration system is primarily
    // concerned with saving values from the Form API we have to special-case
    // the meaning of an empty string for numeric types. In PHP this would be
    // casted to a 0 but for the purposes of configuration we need to treat this
    // as a NULL.
    if ($this->value === '') {
      return NULL;
    }

    return (int) $this->value;
  }

}
