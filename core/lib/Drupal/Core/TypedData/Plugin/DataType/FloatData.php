<?php

declare(strict_types = 1);

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
  public function getValue(): ?float {
    return $this->value !== NULL ? (float) $this->value : NULL;
   }

}
