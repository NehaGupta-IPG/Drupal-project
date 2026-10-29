<?php

declare(strict_types = 1);

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
  public function getValue(): ?int {
    return $this->value !== NULL ? (int) $this->value : NULL;
  }

}
