<?php

declare(strict_types=1);

namespace DrCommandPackage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Service provider contributed by a non-module Composer package.
 *
 * @see \Drupal\KernelTests\Core\DrupalKernel\PhpPackageExtensionsDetailsTest
 */
class PhpPackageTestServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $container->setParameter('dr_command_package_test.service_provider_registered', TRUE);
  }

}
