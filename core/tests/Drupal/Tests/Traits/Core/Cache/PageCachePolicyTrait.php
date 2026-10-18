<?php

declare(strict_types=1);

namespace Drupal\Tests\Traits\Core\Cache;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\PageCache\RequestPolicy\CommandLineOrUnsafeMethod;

/**
 * Trait for using page caching modules in Kernel tests.
 *
 * To use Drupal's page caching with HTTP requests in a Kernel test, the
 * following are necessary:
 * - Either or both of the 'page_cache' and 'dynamic_page_cache' modules must be
 *   installed, as desired.
 * - The test class must implement
 *   \Drupal\Core\DependencyInjection\ServiceModifierInterface. This trait
 *   provides an implementation of the alter() method which removes the request
 *   policy that denies caching when running from the command line.
 * - The 'user' module must be installed if 'dynamic_page_cache' is installed.
 *
 * @see \Drupal\Tests\HttpKernelUiHelperTrait
 */
trait PageCachePolicyTrait {

  /**
   * Removes the command line request policy from the page cache policies.
   *
   * Tests always run from the command line, so this policy would otherwise
   * deny caching for every request.
   *
   * To use this, the test class must implement
   * \Drupal\Core\DependencyInjection\ServiceModifierInterface.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The DI container.
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->hasDefinition(CommandLineOrUnsafeMethod::class)) {
      $container->getDefinition(CommandLineOrUnsafeMethod::class)
        ->clearTag('page_cache_request_policy')
        ->clearTag('dynamic_page_cache_request_policy');
    }
  }

}
