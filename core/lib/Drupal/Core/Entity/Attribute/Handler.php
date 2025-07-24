<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Attribute;

/**
 * Attribute class to add handler properties to entity type definition.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Handler extends EntityTypeProperty {

  /**
   * Constructs a Handler attribute.
   *
   * @param string $type
   *   The type of handler to set.
   * @param class-string|array $value
   *   The value for a handler type.
   * @param string|false $nested
   *   (optional) The nested handler definition key, or FALSE if the handler
   *   does not have a nested definition. Defaults to FALSE.
   */
  public function __construct(
    public readonly string $type,
    mixed $value,
    public readonly string|false $nested = FALSE,
  ) {
    $key = ['handlers', $type];
    if ($nested !== FALSE) {
      $key[] = $nested;
    }
    parent::__construct($key, $value);
  }

}
