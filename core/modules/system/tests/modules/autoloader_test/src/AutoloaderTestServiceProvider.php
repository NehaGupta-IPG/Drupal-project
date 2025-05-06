<?php

declare(strict_types=1);

namespace Drupal\autoloader_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Service provider for service provider testing module.
 */
final class AutoloaderTestServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container->setParameter('service_provider_test.class', [new AutoloaderTestClass()]);
    $container->setParameter('service_provider_test.enum', [AutoloaderTestEnum::Test]);
  }

}
