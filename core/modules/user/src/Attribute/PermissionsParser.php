<?php

declare(strict_types=1);

namespace Drupal\user\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a PermissionsParser attribute for plugin discovery.
 *
 * Plugin Namespace: Plugin\PermissionsParser
 *
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class PermissionsParser extends Plugin {

  /**
   * Constructs a PermissionsParser object.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The title of the parser.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   The description of the parser.
   * @param int $weight
   *   The ordering of the parser in lists.
   * @param bool $legacy
   *   If this is legacy data or not.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly int $weight = 0,
    public readonly bool $legacy = FALSE,
  ) {}

}
