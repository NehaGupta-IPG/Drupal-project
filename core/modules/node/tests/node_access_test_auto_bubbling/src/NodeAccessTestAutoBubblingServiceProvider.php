<?php

declare(strict_types=1);

namespace Drupal\node_access_test_auto_bubbling;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\node_access_test_auto_bubbling\EventSubscriber\TestEarlyRenderingControllerWrapperSubscriber;

/**
 * Service provider that alters the early rendering controller subscriber.
 */
class NodeAccessTestAutoBubblingServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // Replace the subscriber with a test class that allows early rendering to
    // be disabled.
    $definition = $container->getDefinition('early_rendering_controller_wrapper_subscriber');
    $definition->setClass(TestEarlyRenderingControllerWrapperSubscriber::class)
      ->setAutowired(TRUE);
  }

}
