<?php

declare(strict_types=1);

namespace MultiProviderPackage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Service provider contributed by a non-module Composer package.
 *
 * @see \Drupal\KernelTests\Core\DrupalKernel\PhpPackageExtensionsTest
 */
class ProviderTwo implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $container->setParameter('multi_provider_test.service_provider_two_registered', TRUE);
  }

}
