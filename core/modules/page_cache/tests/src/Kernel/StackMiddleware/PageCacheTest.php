<?php

declare(strict_types=1);

namespace Drupal\Tests\page_cache\Kernel\StackMiddleware;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests page caching with multiple requests.
 */
#[Group('page_cache')]
#[RunTestsInSeparateProcesses]
final class PageCacheTest extends KernelTestBase implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['page_cache', 'test_page_test'];

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $page_cache_request_policy = $this->createStub(RequestPolicyInterface::class);
    $page_cache_request_policy
      ->method('check')
      ->willReturn(RequestPolicyInterface::ALLOW);
    $container->set('page_cache_request_policy', $page_cache_request_policy);
  }

  /**
   * Tests multiple requests have different cache IDs.
   */
  public function testMultipleRequestsCacheIds(): void {
    $http_kernel = $this->container->get('http_kernel');

    $route_names = [
      'test_page_test.test_page',
      'test_page_test.render_title',
    ];
    foreach ($route_names as $route_name) {
      $request = Request::create(Url::fromRoute($route_name)->toString());
      $response = $http_kernel->handle($request);
      self::assertTrue($response->headers->has('X-Drupal-Cache'));
      self::assertEquals('MISS', $response->headers->get('X-Drupal-Cache'));
      $response = $http_kernel->handle($request);
      self::assertTrue($response->headers->has('X-Drupal-Cache'));
      self::assertEquals('HIT', $response->headers->get('X-Drupal-Cache'));
    }
  }

}
