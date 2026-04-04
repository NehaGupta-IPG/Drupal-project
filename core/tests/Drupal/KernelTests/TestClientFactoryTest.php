<?php

declare(strict_types=1);

namespace Drupal\KernelTests;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\Test\Http\TestClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests mocking the Guzzle client in a Kernel test.
 */
#[CoversClass(TestClientFactory::class)]
#[Group('PHPUnit')]
#[Group('Test')]
#[Group('KernelTests')]
#[RunTestsInSeparateProcesses]
class TestClientFactoryTest extends KernelTestBase implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'system_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $service_definition = $container->getDefinition('http_client_factory');
    $service_definition->setClass(TestClientFactory::class);
  }

  /**
   * Tests the mocked Guzzle client.
   */
  public function testGuzzleRequests(): void {
    // Make a request to the route which itself makes an HTTP request.
    $this->drupalGet('system-test/http-client-request', ['query' => ['path' => '/system-test/main-content-handling']]);
    $this->assertSession()->pageTextContains(Response::HTTP_OK);
  }

}
