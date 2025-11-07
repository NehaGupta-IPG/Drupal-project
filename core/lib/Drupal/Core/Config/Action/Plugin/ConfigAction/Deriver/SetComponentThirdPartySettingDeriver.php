<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Action\Plugin\ConfigAction\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Defines a pluralized version of the setComponentThirdPartySetting action.
 *
 * @internal
 *    This API is experimental.
 */
final class SetComponentThirdPartySettingDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives['setComponentThirdPartySetting'] = $base_plugin_definition;
    $this->derivatives['setComponentThirdPartySettings'] = $base_plugin_definition;
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
