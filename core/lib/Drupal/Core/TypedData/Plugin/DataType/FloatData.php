<?php

namespace Drupal\Core\TypedData\Plugin\DataType;

use Drupal\Core\Serialization\Attribute\JsonSchema;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\PrimitiveBase;
use Drupal\Core\TypedData\Type\FloatInterface;

/**
 * The float data type.
 *
 * The plain value of a float is a regular PHP float. For setting the value
 * any PHP variable that casts to a float may be passed.
 */
#[DataType(
  id: "float",
  label: new TranslatableMarkup("Float")
)]
class FloatData extends PrimitiveBase implements FloatInterface {

  /**
   * {@inheritdoc}
   */
  #[JsonSchema(['type' => 'number'])]
  public function getCastedValue() {
    // Special handling for floats since the configuration system is primarily
    // concerned with saving values from the Form API we have to special-case
    // the meaning of an empty string for numeric types. In PHP this would be
    // casted to a 0 but for the purposes of configuration we need to treat this
    // as a NULL.
    if ($this->value === '') {
      return NULL;
    }

    return (float) $this->value;
  }

}
