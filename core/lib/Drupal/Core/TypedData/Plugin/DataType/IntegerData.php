<?php

namespace Drupal\Core\TypedData\Plugin\DataType;

use Drupal\Core\Serialization\Attribute\JsonSchema;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\PrimitiveBase;
use Drupal\Core\TypedData\Type\IntegerInterface;
use \Drupal\Core\Config\UnsupportedDataTypeConfigException;

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
    $min = constant('PHP_INT_MIN');
    $max = constant('PHP_INT_MAX');
    if( $this->value < $min || $this->value > $max) {
      throw new UnsupportedDataTypeConfigException("The value is out of the range of a PHP integer ($min to $max).");
    }
    return (int) $this->value;
  }

}
