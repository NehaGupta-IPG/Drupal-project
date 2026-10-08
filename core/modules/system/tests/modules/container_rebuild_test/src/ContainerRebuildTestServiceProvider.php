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
    $key_value = $container->get('keyvalue')
      ->get('container_rebuild_test');
    $count = $key_value->get('count', 0);
    $key_value->set('count', ++$count);
  }

}
