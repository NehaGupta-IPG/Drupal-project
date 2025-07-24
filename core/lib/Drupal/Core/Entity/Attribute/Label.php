<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Attribute;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Attribute class to add a single label to an entity type definition.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Label extends EntityTypeProperty {

  /**
   * Constructs a Label attribute.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable label value.
   * @param string $type
   *   The type of label, such as 'collection', 'singular', 'plural', or
   *   'bundle'. Defaults to empty string.
   */
  public function __construct(
    public readonly TranslatableMarkup $label,
    public readonly string $type = '',
  ) {
    $key = match ($type) {
      '' => 'label',
      'bundle' => 'bundle_label',
      default => 'label_' . $type,
    };
    parent::__construct($key, $label);
  }

}
