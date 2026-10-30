<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Installer;

use Drupal\Component\DependencyInjection\ReverseContainer;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests that the installer kernel honours explicit container rebuilds.
 */
#[CoversClass(InstallerKernel::class)]
#[Group('Installer')]
class InstallerKernelRebuildTest extends UnitTestCase {

  /**
   * Tests that an unchanged extension list does not trigger a recompile.
   */
  public function testUnchangedExtensionsSkipRecompile(): void {
    $kernel = $this->createKernel();
    $container = $kernel->getContainer();

    $this->assertSame($container, $kernel->callInitializeContainer());
    $this->assertSame(0, $kernel->compileCount, 'The container was not recompiled.');
  }

  /**
   * Tests that a changed extension list triggers a recompile.
   */
  public function testChangedExtensionsTriggerRecompile(): void {
    $kernel = $this->createKernel();
    $kernel->extensions['module']['node'] = 0;

    $this->expectException(RecompileSentinel::class);
    $kernel->callInitializeContainer();
  }

  /**
   * Tests that swapping an extension of the same count triggers a recompile.
   */
  public function testSwappedExtensionsTriggerRecompile(): void {
    $kernel = $this->createKernel();
    // Replace the module list with one of the same size but a different key,
    // so that the change is only detectable by comparing the keys.
    $kernel->extensions['module'] = ['node' => 0];

    $this->expectException(RecompileSentinel::class);
    $kernel->callInitializeContainer();
  }

  /**
   * Tests that a changed theme list triggers a recompile.
   */
  public function testChangedThemesTriggerRecompile(): void {
    $kernel = $this->createKernel();
    $kernel->extensions['theme']['olivero'] = 0;

    $this->expectException(RecompileSentinel::class);
    $kernel->callInitializeContainer();
  }

  /**
   * Tests that an explicit rebuild forces a recompile despite unchanged extensions.
   *
   * ::initializeContainer() memoizes on the module and theme lists, but an
   * explicit ::rebuildContainer() means something the memo cannot observe has
   * changed - such as the installer switching to database-backed services - so
   * the memo must not swallow the request.
   */
  public function testExplicitRebuildBypassesMemo(): void {
    $kernel = $this->createKernel();

    $this->expectException(RecompileSentinel::class);
    $kernel->rebuildContainer();
  }

  /**
   * Tests that the force flag applies to one rebuild only.
   */
  public function testForceFlagIsNotSticky(): void {
    $kernel = $this->createKernel();

    try {
      $kernel->rebuildContainer();
      $this->fail('The explicit rebuild did not recompile.');
    }
    catch (RecompileSentinel) {
      // The sentinel is thrown in place of the parent's recompile.
    }

    // The next automatic call must be memoized again.
    $this->assertSame($kernel->getContainer(), $kernel->callInitializeContainer());
    $this->assertSame(1, $kernel->compileCount, 'Only the explicit rebuild recompiled.');
  }

  /**
   * Returns a kernel with a compiled container and a matching extension memo.
   */
  protected function createKernel(): TestInstallerKernel {
    $kernel = new TestInstallerKernel('test', $this->createStub('Composer\Autoload\ClassLoader'), FALSE, '/');

    $container = new ContainerBuilder();
    $container->set('messenger', $this->createStub('Drupal\Core\Messenger\MessengerInterface'));
    $container->set(ReverseContainer::class, new ReverseContainer($container));
    $container->set('config.installer', $this->createStub('Drupal\Core\Config\ConfigInstallerInterface'));
    // Only reached when the memo wrongly swallows an explicit rebuild: the
    // parent then returns the existing container and ::rebuildContainer()
    // calls loadAll() on it. Stubbed so that regression fails on the missing
    // recompile rather than on a service-not-found error.
    $container->set('module_handler', $this->createStub('Drupal\Core\Extension\ModuleHandlerInterface'));
    $kernel->setContainer($container);
    $kernel->primeMemo();

    return $kernel;
  }

}

/**
 * Thrown in place of the parent kernel's container recompilation.
 */
class RecompileSentinel extends \RuntimeException {
}

/**
 * An installer kernel that records each recompile instead of performing it.
 */
class TestInstallerKernel extends InstallerKernel {

  /**
   * The extensions this kernel reports.
   */
  public array $extensions = [
    'module' => ['system' => 0],
    'theme' => ['claro' => 0],
  ];

  /**
   * The number of times a recompile was attempted.
   */
  public int $compileCount = 0;

  /**
   * Sets the current container and primes the extension memo.
   */
  public function setContainer(?ContainerInterface $container = NULL): void {
    $this->container = $container;
  }

  /**
   * Primes the extension memo so an automatic call would be skipped.
   */
  public function primeMemo(): void {
    $this->compiledExtensions = $this->extensions;
  }

  /**
   * Calls the protected ::initializeContainer().
   */
  public function callInitializeContainer(): ContainerInterface {
    return $this->initializeContainer();
  }

  /**
   * {@inheritdoc}
   */
  protected function getExtensions(): array {
    return $this->extensions;
  }

  /**
   * {@inheritdoc}
   */
  protected function compileContainer() {
    $this->compileCount++;
    throw new RecompileSentinel();
  }

}
