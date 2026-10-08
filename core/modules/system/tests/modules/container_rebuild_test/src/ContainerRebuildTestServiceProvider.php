<?php

declare(strict_types=1);

namespace Drupal\container_rebuild_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * The service provider for testing container rebuilds.
 */
class ContainerRebuildTestServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $key_value = $container->get('keyvalue')->get('state');
    $count = $key_value->get('container_rebuild_test.count', 0);
    $key_value->set('container_rebuild_test.count', ++$count);
  }

}
