<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Action\Plugin\ConfigAction\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Generates derivatives for simpleConfigArray config action.
 *
 * @internal
 *   This API is experimental.
 */
final class SimpleConfigArrayDeriver extends DeriverBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives['append'] = [
      'admin_label' => $this->t('Append values to an array in simple config'),
      'function' => 'array_push',
    ] + $base_plugin_definition;

    $this->derivatives['prepend'] = [
      'admin_label' => $this->t('Prepend values to an array in simple config'),
      'function' => 'array_unshift',
    ] + $base_plugin_definition;

    $this->derivatives['splice'] = [
      'admin_label' => $this->t('Splice values into an array in simple config'),
      'function' => 'array_splice',
    ] + $base_plugin_definition;

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
