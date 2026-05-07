<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Routing;

use Drupal\Component\Datetime\Time;
use Drupal\Component\PhpStorage\FlockMTimeProtectedFileStorage;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Routing\RouteStorage\PhpDumperRouteStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Round-trips a RouteCollection through PhpDumperRouteStorage on real disk.
 */
#[CoversClass(PhpDumperRouteStorage::class)]
#[Group('Routing')]
#[RunTestsInSeparateProcesses]
class CompiledRouterDataRoundTripTest extends KernelTestBase {

  /**
   * Directory used by the test's PhpStorage.
   */
  protected string $phpStorageDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->phpStorageDir = $this->siteDirectory . '/php_storage';
    mkdir($this->phpStorageDir, 0777, TRUE);
  }

  /**
   * Verifies every Route survives a dump-then-load round trip.
   */
  public function testRoundTripPreservesRoutes(): void {
    $collection = new RouteCollection();
    $collection->add('simple', new Route('/foo'));
    $collection->add('with_host', (new Route('/bar'))->setHost('example.com'));
    $collection->add('with_methods', (new Route('/baz'))
      ->setMethods(['GET', 'POST'])
      ->setSchemes(['https'])
      ->setRequirements(['thing' => '\d+']));
    $collection->add('with_defaults', new Route('/node/{node}', ['_controller' => 'ctl', '_title' => 'T']));
    $collection->addAlias('alias.simple', 'simple');

    $cache = new MemoryBackend(new Time());
    $phpStorage = new FlockMTimeProtectedFileStorage([
      'directory' => $this->phpStorageDir,
      'bin' => 'routes',
      'secret' => 'test-secret',
    ]);
    $storage = new PhpDumperRouteStorage($cache, $phpStorage);

    $dumped = $storage->dump($collection);
    $this->assertSame('/foo', $dumped->getRoute('simple')?->getPath());

    // Re-instantiate to force a load from disk.
    $loaded = (new PhpDumperRouteStorage($cache, $phpStorage))->load();
    $this->assertNotNull($loaded);

    foreach ($collection->all() as $name => $expected) {
      $actual = $loaded->getRoute($name);
      $this->assertNotNull($actual, "Route '$name' survives round trip.");
      $this->assertSame($expected->getPath(), $actual->getPath());
      $this->assertSame($expected->getHost(), $actual->getHost());
      $this->assertSame($expected->getMethods(), $actual->getMethods());
      $this->assertSame($expected->getSchemes(), $actual->getSchemes());
      $this->assertSame($expected->getDefaults(), $actual->getDefaults());
      $this->assertSame($expected->getRequirements(), $actual->getRequirements());
    }

    $this->assertSame(['alias.simple' => 'simple'], $loaded->getAliases());
  }

}
