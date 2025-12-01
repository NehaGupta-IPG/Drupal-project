<?php

declare(strict_types=1);

namespace Drupal\router_test;

use Drupal\router_test\Access\TestAccessCheck;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Registers a dynamic route provider.
 */
class RouterTestServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $container->register('router_test.subscriber', RouteTestSubscriber::class)
      ->addTag('event_subscriber');
    $container->register('access_check.router_test', TestAccessCheck::class)
      ->addTag('access_check', ['applies_to' => '_access_router_test']);
    $container->register('router_test.early_exception.subscriber', RouterTestEarlyExceptionSubscriber::class)
      ->addTag('event_subscriber');
  }

}
