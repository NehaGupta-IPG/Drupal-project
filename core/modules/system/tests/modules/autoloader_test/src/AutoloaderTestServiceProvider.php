<?php

declare(strict_types=1);

namespace Drupal\autoloader_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Service provider for service provider testing module.
 */
final class AutoloaderTestServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $container->setParameter('autoloader_test.class', [new AutoloaderTestClass()]);
    $container->setParameter('autoloader_test.enum', [AutoloaderTestEnum::Test]);
  }

}
