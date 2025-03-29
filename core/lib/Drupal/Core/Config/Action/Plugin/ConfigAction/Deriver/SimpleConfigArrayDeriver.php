<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Action\Plugin\ConfigAction\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Generates derivatives for simpleConfigArray config action.
 *
 * @internal
 *   This API is experimental.
 */
final class SimpleConfigArrayDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives['append'] = $base_plugin_definition + [
      'function' => 'array_push',
    ];
    $this->derivatives['prepend'] = $base_plugin_definition + [
      'function' => 'array_unshift',
    ];
    $this->derivatives['splice'] = $base_plugin_definition + [
      'function' => 'array_splice',
    ];

    return $this->derivatives;
  }

}
