<?php

declare(strict_types=1);

namespace ServicesPackage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Service provider contributed by a non-module Composer package.
 *
 * @see \Drupal\KernelTests\Core\DrupalKernel\PhpPackageExtensionsTest
 */
class Provider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $container->setParameter('services_package_test.service_provider_registered', TRUE);
  }

}
