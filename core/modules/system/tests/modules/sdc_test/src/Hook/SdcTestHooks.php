<?php

declare(strict_types=1);

namespace Drupal\sdc_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for sdc_test.
 */
class SdcTestHooks {

  /**
   * Implements hook_plugin_filter_TYPE__CONSUMER_alter().
   */
  #[Hook('plugin_filter_component__sdc_test_alter')]
  public function pluginFilterComponentSdcTestAlter(array &$definitions, array $extra): void {
    // Explicitly remove the "Banner" component from the list.
    unset($definitions['sdc_test:my-banner']);
  }

}
