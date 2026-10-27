<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel;

use Drupal\Core\Config\StorageCacheInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests state change deprecations during real HTTP controller dispatch.
 */
#[Group('system')]
#[RunTestsInSeparateProcesses]
class CsrfStateChangeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'csrf_test', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    // Remove the synthetic request so HTTP dispatch owns the main request.
    $this->container->get('request_stack')->pop();
  }

  /**
   * Tests that safe requests deprecate writes but still complete them.
   */
  #[DataProvider('safeMethods')]
  #[IgnoreDeprecations]
  public function testUnprotectedWrite(string $method, string $operation): void {
    if ($operation !== 'config') {
      $this->installEntitySchema('entity_test');
    }
    // Configuration writes before controller dispatch must remain possible.
    $this->config('system.site')->set('name', 'Before dispatch')->save();
    if ($operation === 'entity-delete') {
      $this->container->get('entity_type.manager')->getStorage('entity_test')->create(['name' => 'Before dispatch'])->save();
    }
    $this->expectUserDeprecationMessage('Performing configuration and entity writes during an unprotected safe request is deprecated in drupal:12.0.0 and will throw a LogicException in drupal:13.0.0. Add a _csrf_token route requirement. See https://www.drupal.org/project/drupal/issues/3508087');
    $request = Request::create('/csrf/unprotected-' . ($operation === 'entity-delete' ? $operation : $operation . '-write'), $method);
    $response = $this->container->get('http_kernel')->handle($request);
    $this->assertSame(200, $response->getStatusCode());
    if ($operation === 'config') {
      $this->assertSame('Updated by GET request', $this->container->get('config.storage')->read('system.site')['name']);
    }
    else {
      $storage = $this->container->get('entity_type.manager')->getStorage('entity_test');
      $entities = $storage->loadMultiple();
      $this->assertCount($operation === 'entity-delete' ? 0 : 1, $entities);
      // Outside HTTP dispatch deletion is allowed without a deprecation.
      $storage->delete($entities);
      $this->assertCount(0, $storage->loadMultiple());
    }
  }

  /**
   * Provides safe methods and write operations.
   */
  public static function safeMethods(): array {
    return [['GET', 'config'], ['HEAD', 'config'], ['GET', 'entity'], ['GET', 'entity-delete']];
  }

  /**
   * Tests explicit opt-out and unsafe requests without deprecations.
   */
  public function testAllowedWrites(): void {
    // Guarding writes must preserve the public configuration cache contract.
    $this->assertInstanceOf(StorageCacheInterface::class, $this->container->get(StorageCacheInterface::class));
    foreach ([['GET', 'considered'], ['POST', 'unprotected']] as [$method, $route]) {
      $response = $this->container->get('http_kernel')->handle(Request::create('/csrf/' . $route . '-config-write', $method));
      $this->assertSame(200, $response->getStatusCode());
      $this->assertStringContainsString('Configuration updated.', $response->getContent());
    }
  }

}
