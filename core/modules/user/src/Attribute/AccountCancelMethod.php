<?php

declare(strict_types=1);

namespace Drupal\user\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Defines an AccountCancelMethod attribute.
 *
 * Plugin Namespace: Plugin\user\CancelMethod
 *
 * @see \Drupal\user\AccountCancelMethodManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AccountCancelMethod extends Plugin {

  /**
   * Constructs an AccountCancelMethod attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param string|\Stringable $label
   *   The human-readable name of the plugin.
   * @param string|\Stringable|null $description
   *   (optional) The human-readable description of the plugin.
   */
  public function __construct(
    public readonly string $id,
    public readonly string|\Stringable $label,
    public readonly string|\Stringable|null $description = NULL,
  ) {}

}
