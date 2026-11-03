<?php

declare(strict_types=1);

namespace DrCommandPackage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Service provider contributed by a non-module Composer package.
 *
 * @see \Drupal\KernelTests\Core\DrupalKernel\PhpPackageExtensionsTest
 */
class ServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // Add the namespace to the container namespaces so the dr command is found.
    $namespaces = $container->getParameter('container.namespaces');
    $namespaces['DrCommandPackage'] = __DIR__;
    $container->setParameter('container.namespaces', $namespaces);
  }

}
