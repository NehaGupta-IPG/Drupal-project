<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\DependencyInjection;

use Drupal\container_env_test\TestService;
use Drupal\Core\DrupalKernel;
use Drupal\KernelTests\KernelTestBase;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests integration of the container with environment variables.
 */
#[Group('DependencyInjection')]
#[RunTestsInSeparateProcesses]
class EnvironmentVariableTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['container_env_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $_ENV['CONTAINER_ENV_TEST'] = 'test-variable';
    $_ENV['CONTAINER_ENV_TEST_WITH_DEFAULT2'] = 'override-default';

    $_ENV['CONTAINER_ENV_TEST_STRING'] = 'test-string';
    $_ENV['CONTAINER_ENV_TEST_INTEGER'] = 123;
    $_ENV['CONTAINER_ENV_TEST_BOOLEAN'] = TRUE;
    parent::setUp();
  }

  /**
   * Tests the environment variable integration.
   */
  public function testEnvironmentVariable(): void {
    $this->assertSame('test-variable', $this->container->getParameter('container_env_test'));
    $this->assertSame('some-test', $this->container->getParameter('container_env_test_with_default'));
    $this->assertSame('override-default', $this->container->getParameter('container_env_test_with_default2'));

    $this->assertSame('test-string', $this->container->getParameter('container_env_test_string'));

    $this->assertSame(123, $this->container->getParameter('container_env_test_integer'));

    $this->assertTrue($this->container->getParameter('container_env_test_boolean'));

    $this->assertSame('some_secret', $this->container->getParameter('container_env_test_secret'));

    $this->assertSame('http://10.0.0.1/project', $this->container->getParameter('container_env_test_host_path'));

    $this->assertSame(['one', 'two', 'three'], $this->container->getParameter('container_env_test_array'));

    $this->assertSame('https://symfony.com/?foo=%s&amp;bar=%d', $this->container->getParameter('container_env_test_url_pattern'));

    $service = \Drupal::service(TestService::class);
    $this->assertInstanceOf(TestService::class, $service);
    $this->assertSame('test-variable', $service->parameter);
  }

  /**
   * Tests container caching with environment variables.
   */
  public function testDumpedContainer(): void {
    // Create a kernel suitable for testing.
    $kernel = new DrupalKernel('test', $this->classLoader, TRUE, $this->root);
    $kernel->setSitePath(vfsStream::url('root/sites/default'));
    $kernel->boot();

    $container = $kernel->getContainer();

    $this->assertSame('test-variable', $container->getParameter('container_env_test'));

    // Ensure the cached container does not contain the resolved environment
    // variable.
    $cached_definition = $kernel->getCachedContainerDefinition();
    $this->assertSame('%env(CONTAINER_ENV_TEST)%', $cached_definition['parameters']['container_env_test']);

    $_ENV['CONTAINER_ENV_TEST'] = 'another-variable';
    $kernel = new DrupalKernel('test', $this->classLoader, TRUE, $this->root);
    $kernel->setSitePath(vfsStream::url('root/sites/default'));
    $kernel->boot();
    $container = $kernel->getContainer();

    $this->assertSame('another-variable', $container->getParameter('container_env_test'));
  }

}
