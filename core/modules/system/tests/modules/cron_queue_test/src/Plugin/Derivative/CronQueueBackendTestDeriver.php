<?php

declare(strict_types=1);

namespace Drupal\cron_queue_test\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Provides a deriver for testing queue backends.
 */
class CronQueueBackendTestDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $examples = ['database', 'memory'];

    foreach ($examples as $backend) {
      $this->derivatives[$backend] = $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
