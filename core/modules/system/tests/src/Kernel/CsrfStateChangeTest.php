<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel;

use Drupal\Core\Config\StorageCacheInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
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
   * Tests that safe requests deprecate configuration writes but complete them.
   */
  #[TestWith(['GET'])]
  #[TestWith(['HEAD'])]
  #[IgnoreDeprecations]
  public function testUnprotectedConfigWrite(string $method): void {
    // Configuration writes before controller dispatch must remain possible.
    $this->config('system.site')->set('name', 'Before dispatch')->save();
    $this->expectUserDeprecationMessage('Performing configuration and entity writes during an unprotected safe request is deprecated in drupal:12.0.0 and will throw a LogicException in drupal:13.0.0. Add a _csrf_token route requirement. See https://www.drupal.org/project/drupal/issues/3508087');
    $request = Request::create('/csrf/unprotected-config-write', $method);
    $response = $this->container->get('http_kernel')->handle($request);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('Updated by GET request', $this->container->get('config.storage')->read('system.site')['name']);
  }

  /**
   * Tests that safe requests deprecate entity writes but complete them.
   */
  #[IgnoreDeprecations]
  public function testUnprotectedEntityWrite(): void {
    $this->installEntitySchema('entity_test');
    $this->expectUserDeprecationMessage('Performing configuration and entity writes during an unprotected safe request is deprecated in drupal:12.0.0 and will throw a LogicException in drupal:13.0.0. Add a _csrf_token route requirement. See https://www.drupal.org/project/drupal/issues/3508087');
    $request = Request::create('/csrf/unprotected-entity-write');
    $response = $this->container->get('http_kernel')->handle($request);
    $this->assertSame(200, $response->getStatusCode());
    $storage = $this->container->get('entity_type.manager')->getStorage('entity_test');
    $entities = $storage->loadMultiple();
    $this->assertCount(1, $entities);
    // Outside HTTP dispatch deletion is allowed without a deprecation.
    $storage->delete($entities);
    $this->assertCount(0, $storage->loadMultiple());
  }

  /**
   * Tests that safe requests deprecate entity deletion but complete it.
   */
  #[IgnoreDeprecations]
  public function testUnprotectedEntityDelete(): void {
    $this->installEntitySchema('entity_test');
    $storage = $this->container->get('entity_type.manager')->getStorage('entity_test');
    $storage->create(['name' => 'Before dispatch'])->save();
    $this->expectUserDeprecationMessage('Performing configuration and entity writes during an unprotected safe request is deprecated in drupal:12.0.0 and will throw a LogicException in drupal:13.0.0. Add a _csrf_token route requirement. See https://www.drupal.org/project/drupal/issues/3508087');
    $request = Request::create('/csrf/unprotected-entity-delete');
    $response = $this->container->get('http_kernel')->handle($request);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertCount(0, $storage->loadMultiple());
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
