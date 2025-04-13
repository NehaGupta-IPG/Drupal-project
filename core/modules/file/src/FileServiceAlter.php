<?php

namespace Drupal\file;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\StackMiddleware\NegotiationMiddleware;

/**
 * Adds 'application/octet-stream' as a known (bin) format.
 */
class FileServiceAlter implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->has('http_middleware.negotiation') && is_a($container->getDefinition('http_middleware.negotiation')->getClass(), NegotiationMiddleware::class, TRUE)) {
      $container->getDefinition('http_middleware.negotiation')->addMethodCall('registerFormat', ['bin', ['application/octet-stream']]);
    }
  }

}
