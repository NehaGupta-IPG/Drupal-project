<?php

namespace Drupal\Core\TypedData\Plugin\DataType;

use Drupal\Core\Serialization\Attribute\JsonSchema;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\PrimitiveBase;
use Drupal\Core\TypedData\Type\FloatInterface;
use Drupal\Core\Config\UnsupportedDataTypeConfigException;

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
    $min = constant('PHP_FLOAT_MIN');
    $max = constant('PHP_FLOAT_MAX');
    if ($this->value < -$max || 
        $this->value > -$min && $this->value < $min ||
        $this->value > $max
    ) {
      throw new UnsupportedDataTypeConfigException("The value is out of the range of a PHP float ($min to $max).");
    }
    return (float) $this->value;
  }

}
