<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\DrupalKernel;

use Composer\Autoload\ClassLoader;
use Drupal\Core\Cache\DatabaseBackend;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorage\NullContainerStorage;
use Drupal\Core\DependencyInjection\PhpContainer;
use Drupal\Core\DrupalKernel;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;

// cspell:ignore äöüßαβγδεζηθικλμνξοσὠ
/**
 * Tests DIC compilation to disk.
 */
#[CoversClass(DrupalKernel::class)]
#[Group('DrupalKernel')]
#[RunTestsInSeparateProcesses]
class DrupalKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (get_error_handler() === '_drupal_error_handler') {
      restore_error_handler();
    }
    parent::tearDown();
  }

  /**
   * {@inheritdoc}
   */
  protected function bootKernel(): void {
    // Do not boot the kernel, because we are testing aspects of this process.
  }

  /**
   * Build a kernel for testings.
   *
   * Because the bootstrap is in DrupalKernel::boot and that involved loading
   * settings from the filesystem we need to go to extra lengths to build a
   * kernel for testing.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object to use in booting the kernel.
   * @param array $modules_enabled
   *   A list of modules to install on the kernel.
   *
   * @return \Drupal\Core\DrupalKernel
   *   New kernel for testing.
   */
  protected function getTestKernel(Request $request, ?array $modules_enabled = NULL) {
    // Manually create kernel to avoid replacing settings.
    $class_loader = require $this->root . '/autoload.php';
    $kernel = DrupalKernel::createFromRequest($request, $class_loader, 'testing');
    $this->setSetting('container_yamls', []);
    $this->setSetting('hash_salt', $this->databasePrefix);
    $this->setSetting('container_base_class', PhpContainer::class);
    if (isset($modules_enabled)) {
      $kernel->updateModules($modules_enabled);
    }
    $kernel->boot();

    return $kernel;
  }

  /**
   * Tests DIC compilation.
   */
  public function testCompileDIC(): void {
    // @todo Write a memory based storage backend for testing.
    $modules_enabled = [
      'system' => 'system',
      'user' => 'user',
    ];

    $request = Request::createFromGlobals();
    $this->getTestKernel($request, $modules_enabled);

    // Instantiate it a second time and we should get the compiled Container
    // class.
    $kernel = $this->getTestKernel($request);
    $container = $kernel->getContainer();
    $refClass = new \ReflectionClass($container);
    $is_compiled_container = !$refClass->isSubclassOf('Symfony\Component\DependencyInjection\ContainerBuilder');
    $this->assertTrue($is_compiled_container);
    // Verify that the list of modules is the same for the initial and the
    // compiled container.
    $module_list = array_keys($container->get('module_handler')->getModuleList());
    $this->assertEquals(array_values($modules_enabled), $module_list);

    // Get the container another time, simulating a "production" environment.
    $container = $this->getTestKernel($request)
      ->getContainer();

    $refClass = new \ReflectionClass($container);
    $is_compiled_container = !$refClass->isSubclassOf('Symfony\Component\DependencyInjection\ContainerBuilder');
    $this->assertTrue($is_compiled_container);

    // Verify that the list of modules is the same for the initial and the
    // compiled container.
    $module_list = array_keys($container->get('module_handler')->getModuleList());
    $this->assertEquals(array_values($modules_enabled), $module_list);

    // Test that our synthetic services are there.
    $class_loader = $container->get('class_loader');
    $refClass = new \ReflectionClass($class_loader);
    $this->assertTrue($refClass->hasMethod('loadClass'), 'Container has a class loader');

    // We make this assertion here purely to show that the new container below
    // is functioning correctly, i.e. we get a brand new ContainerBuilder
    // which has the required new services, after changing the list of enabled
    // modules.
    $this->assertFalse($container->has('service_provider_test_class'));

    // Add another module so that we can test that the new module's bundle is
    // registered to the new container.
    $modules_enabled['service_provider_test'] = 'service_provider_test';
    $this->getTestKernel($request, $modules_enabled);

    // Instantiate it a second time and we should not get a ContainerBuilder
    // class because we are loading the container definition from cache.
    $kernel = $this->getTestKernel($request, $modules_enabled);
    $container = $kernel->getContainer();

    $refClass = new \ReflectionClass($container);
    $is_container_builder = $refClass->isSubclassOf('Symfony\Component\DependencyInjection\ContainerBuilder');
    $this->assertFalse($is_container_builder, 'Container is not a builder');

    // Assert that the new module's bundle was registered to the new container.
    $this->assertTrue($container->has('service_provider_test_class'), 'Container has test service');

    // Test that our synthetic services are there.
    $class_loader = $container->get('class_loader');
    $refClass = new \ReflectionClass($class_loader);
    $this->assertTrue($refClass->hasMethod('loadClass'), 'Container has a class loader');

    // Check that the location of the new module is registered.
    $modules = $container->getParameter('container.modules');
    $module_extension_list = $container->get('extension.list.module');
    $this->assertEquals(
      [
        'type' => 'module',
        'pathname' => $module_extension_list->getPathname('service_provider_test'),
        'filename' => NULL,
      ],
      $modules['service_provider_test']
    );

    // Check that the container itself is not among the persist IDs because it
    // does not make sense to persist the container itself.
    $persist_ids = $container->getParameter('persist_ids');
    $this->assertNotContains('service_container', $persist_ids);
  }

  /**
   * Tests that dot-prefixed build parameters are removed from the container.
   *
   * Build parameters (e.g. '.hook_data') are used to pass data between
   * compiler passes. RemoveBuildParametersPass strips them so they don't
   * bloat the cached container definition with data that is only needed at
   * build time.
   */
  public function testBuildParametersRemoved(): void {
    $request = Request::createFromGlobals();
    $kernel = $this->getTestKernel($request);
    $container = $kernel->getContainer();

    $build_only = array_filter(
      array_keys($container->getParameterBag()->all()),
      fn(string $name): bool => str_starts_with($name, '.'),
    );
    $this->assertEmpty($build_only, sprintf(
      'Dot-prefixed build parameters should not be in the compiled container, but found: %s',
      implode(', ', $build_only),
    ));
  }

  /**
   * Tests repeated loading of compiled DIC with different environment.
   */
  public function testRepeatedBootWithDifferentEnvironment(): void {
    $request = Request::createFromGlobals();
    $class_loader = require $this->root . '/autoload.php';

    $environments = [
      'testing1',
      'testing1',
      'testing2',
      'testing2',
    ];

    foreach ($environments as $environment) {
      $kernel = DrupalKernel::createFromRequest($request, $class_loader, $environment);
      $this->setSetting('container_yamls', []);
      $this->setSetting('hash_salt', $this->databasePrefix);
      $this->assertInstanceOf(DrupalKernelInterface::class, $kernel->boot(), "Environment $environment should boot.");
    }
  }

  /**
   * Tests setting of site path after kernel boot.
   */
  public function testPreventChangeOfSitePath(): void {
    // @todo Write a memory based storage backend for testing.
    $modules_enabled = [
      'system' => 'system',
      'user' => 'user',
    ];

    $request = Request::createFromGlobals();
    $kernel = $this->getTestKernel($request, $modules_enabled);
    $pass = FALSE;
    try {
      $kernel->setSitePath('/dev/null');
    }
    catch (\LogicException) {
      $pass = TRUE;
    }
    $this->assertTrue($pass, 'Throws LogicException if DrupalKernel::setSitePath() is called after boot');

    // Ensure no LogicException if DrupalKernel::setSitePath() is called with
    // identical path after boot.
    $path = $kernel->getSitePath();
    $kernel->setSitePath($path);
  }

  /**
   * Data provider for self::testClassLoaderAutoDetect.
   *
   * @return array
   *   An array of test cases. Each test case is an array containing a single boolean value
   *   that represents the class_loader_auto_detect setting to be tested.
   */
  public static function providerClassLoaderAutoDetect(): array {
    return [
      'TRUE' => [TRUE],
      'FALSE' => [FALSE],
    ];
  }

  /**
   * Tests class_loader_auto_detect setting.
   *
   * This test runs in a separate process since it registers class loaders and
   * results in statics being set.
   *
   * @param bool $value
   *   The value to set class_loader_auto_detect to.
   *
   * @legacy-covers ::boot
   */
  #[DataProvider('providerClassLoaderAutoDetect')]
  #[PreserveGlobalState(FALSE)]
  #[RunInSeparateProcess]
  public function testClassLoaderAutoDetect(bool $value): void {
    // Create a virtual file system containing items that should be
    // excluded. Exception being modules directory.
    vfsStream::setup('root', NULL, [
      'sites' => [
        'default' => [],
      ],
      'core' => [
        'lib' => [
          'Drupal' => [
            'Core' => [],
            'Component' => [],
          ],
        ],
      ],
    ]);

    $this->setSetting('class_loader_auto_detect', $value);
    $classloader = $this->prophesize(ClassLoader::class);

    // Assert that we call the setApcuPrefix on the classloader if
    // class_loader_auto_detect is set to TRUE.
    if ($value) {
      $classloader->setApcuPrefix(Argument::type('string'))->shouldBeCalled();
    }
    else {
      $classloader->setApcuPrefix(Argument::type('string'))->shouldNotBeCalled();
    }

    // Create a kernel suitable for testing.
    $kernel = new DrupalKernel('test', $classloader->reveal(), FALSE, vfsStream::url('root'));
    $kernel->setSitePath(vfsStream::url('root/sites/default'));
    $kernel->boot();
  }

  /**
   * Tests reset container.
   */
  #[TestWith([TRUE])]
  #[TestWith([FALSE])]
  public function testResetContainer(bool $config_installer_syncing): void {
    $modules_enabled = [
      'system' => 'system',
      'user' => 'user',
    ];

    $request = Request::createFromGlobals();
    $kernel = $this->getTestKernel($request, $modules_enabled);
    $container = $kernel->getContainer();

    // Ensure services are reset when ::resetContainer is called.
    $this->assertFalse($container->initialized('renderer'));
    $renderer = $container->get('renderer');
    $this->assertTrue($container->initialized('renderer'));

    // Ensure the current user is maintained through a container reset.
    $this->assertSame(0, $container->get('current_user')->id());
    $container->get('current_user')->setInitialAccountId(2);

    // Ensure messages are maintained through a container reset.
    $this->assertEmpty($container->get('messenger')->messagesByType('Container reset'));
    $container->get('messenger')->addMessage('Test reset', 'Container reset');
    $this->assertSame(['Test reset'], $container->get('messenger')->messagesByType('Container reset'));

    // Ensure config installer isSyncing status is maintained through a
    // container reset.
    \Drupal::service(ConfigInstallerInterface::class)->setSyncing($config_installer_syncing);

    // Ensure persisted services are persisted.
    $request_stack = $container->get('request_stack');

    $container->get('stream_wrapper_manager')->register();
    $stream_wrappers = array_keys($container->get('stream_wrapper_manager')->getWrappers());
    $this->assertSame([
      'assets',
      'public',
      'temporary',
      'module',
      'theme',
    ], $stream_wrappers);

    $kernel->resetContainer();

    // Ensure services are reset when ::resetContainer is called.
    $this->assertFalse($container->initialized('renderer'));
    $this->assertNotSame($renderer, $container->get('renderer'));
    $this->assertTrue($container->initialized('renderer'));
    $this->assertSame($kernel, $container->get('kernel'));

    // Ensure the current user is maintained through a container reset.
    $this->assertSame(2, $container->get('current_user')->id());

    // Ensure messages are maintained through a container reset.
    $this->assertSame(['Test reset'], $container->get('messenger')->messagesByType('Container reset'));

    // Ensure config installer isSyncing status is maintained through a
    // container reset.
    $this->assertSame($config_installer_syncing, \Drupal::service(ConfigInstallerInterface::class)->isSyncing());

    // Ensure persisted services are persisted.
    $this->assertSame($request_stack, $container->get('request_stack'));

    // Ensure stream wrappers are registered.
    $this->assertSame($stream_wrappers, array_keys($container->get('stream_wrapper_manager')->getWrappers()));
  }

  /**
   * Tests system locale.
   */
  public function testLocale(): void {
    $utf8_string = 'äöüßαβγδεζηθικλμνξοσὠ';
    // Test environment locale should be UTF-8.
    $this->assertSame($utf8_string, escapeshellcmd($utf8_string));
    $request = Request::createFromGlobals();
    $this->getTestKernel($request);
    // Kernel environment locale should be UTF-8.
    $this->assertSame($utf8_string, escapeshellcmd($utf8_string));
  }

  /**
   * Tests that a new container is created when the site path changes.
   */
  public function testNewContainerOnSitePathChange(): void {
    $class_loader = require $this->root . '/autoload.php';
    $request = Request::createFromGlobals();

    // Create a kernel that will dump the container to disk.
    $kernel = DrupalKernel::createFromRequest($request, $class_loader, 'testing');
    $this->setSetting('container_yamls', []);
    $this->setSetting('hash_salt', $this->databasePrefix);
    $this->setSetting('site_path', $this->siteDirectory);
    $kernel->setSitePath($this->siteDirectory);
    // Need at least one module to ensure container can be dumped.
    $kernel->updateModules(['system' => 'system']);
    $kernel->boot();
    $this->assertSame($this->siteDirectory, $kernel->getContainer()->getParameter('site.path'));
    $this->assertSame(['system'], array_keys($kernel->getContainer()->getParameter('container.modules')));

    // Create a new kernel with the same site path but do not call
    // $kernel->updateModules() to prove this one is loaded from the dumped
    // container.
    $kernel = DrupalKernel::createFromRequest($request, $class_loader, 'testing');
    $this->setSetting('container_yamls', []);
    $this->setSetting('hash_salt', $this->databasePrefix);
    $kernel->setSitePath($this->siteDirectory);
    $kernel->boot();
    $this->assertSame($this->siteDirectory, $kernel->getContainer()->getParameter('site.path'));
    $this->assertSame(['system'], array_keys($kernel->getContainer()->getParameter('container.modules')));

    // Create a new kernel with a different site path. Modules are not injected
    // to load the container from the PHP dumped container.
    $kernel = DrupalKernel::createFromRequest($request, $class_loader, 'testing');
    $this->setSetting('container_yamls', []);
    $this->setSetting('hash_salt', $this->databasePrefix);
    $kernel->setSitePath($this->siteDirectory . '/different');
    $kernel->boot();
    $this->assertSame($this->siteDirectory . '/different', $kernel->getContainer()->getParameter('site.path'));
  }

  /**
   * Builds a kernel that uses the legacy PHP array container.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object to use in booting the kernel.
   * @param array|null $modules_enabled
   *   A list of modules to install on the kernel.
   *
   * @return \Drupal\Core\DrupalKernel
   *   New kernel for testing.
   */
  protected function getTestKernelWithArrayContainer(Request $request, ?array $modules_enabled = NULL): DrupalKernel {
    $class_loader = require $this->root . '/autoload.php';
    $kernel = DrupalKernel::createFromRequest($request, $class_loader, 'testing');
    $this->setSetting('container_yamls', []);
    $this->setSetting('hash_salt', $this->databasePrefix);
    $this->setSetting('container_base_class', Container::class);
    if (isset($modules_enabled)) {
      $kernel->updateModules($modules_enabled);
    }
    $kernel->boot();

    return $kernel;
  }

  /**
   * Tests DIC compilation using the legacy PHP array container.
   */
  public function testCompileDICWithArrayContainer(): void {
    $modules_enabled = [
      'system' => 'system',
      'user' => 'user',
    ];

    $request = Request::createFromGlobals();
    $kernel = $this->getTestKernelWithArrayContainer($request, $modules_enabled);
    $container = $kernel->getContainer();

    // The container should be an instance of the legacy Container class.
    $this->assertInstanceOf(Container::class, $container);
    $this->assertNotInstanceOf(PhpContainer::class, $container);

    // The container should not be a ContainerBuilder.
    $refClass = new \ReflectionClass($container);
    $this->assertFalse($refClass->isSubclassOf('Symfony\Component\DependencyInjection\ContainerBuilder'));

    // No PhpStorage files should be created for the array container.
    $this->assertDirectoryDoesNotExist($kernel->getSitePath() . '/files/php/service_container');

    // Boot a second time and verify we load from the cached definition.
    $kernel2 = $this->getTestKernelWithArrayContainer($request);
    $container2 = $kernel2->getContainer();

    $this->assertInstanceOf(Container::class, $container2);
    $this->assertNotInstanceOf(PhpContainer::class, $container2);

    // The container should not be a ContainerBuilder.
    $refClass = new \ReflectionClass($container2);
    $this->assertFalse($refClass->isSubclassOf('Symfony\Component\DependencyInjection\ContainerBuilder'));

    // Verify that the list of modules is the same for the initial and the
    // cached container.
    $module_list = array_keys($container2->get('module_handler')->getModuleList());
    $this->assertEquals(array_values($modules_enabled), $module_list);

    // Test that our synthetic services are there.
    $class_loader = $container2->get('class_loader');
    $refClass = new \ReflectionClass($class_loader);
    $this->assertTrue($refClass->hasMethod('loadClass'), 'Container has a class loader');
  }

  /**
   * Tests that a container is dumped when the container is invalidated.
   */
  public function testInvalidationDoesNotCauseFurtherContainerBuildsToNotDump(): void {
    $request = Request::createFromGlobals();
    $kernel = $this->getTestKernel($request, ['system' => 'system']);
    $container = $kernel->getContainer();
    $container_file = (new \ReflectionClass($container))->getFileName();
    $kernel->invalidateContainer();
    $this->assertFileExists($container_file);
    // This should cause the container to be dumped again.
    $kernel->updateModules(['system' => 'system']);
    // Containers should be different objects.
    $this->assertNotSame($container, $kernel->getContainer());
    $this->assertSame($container::class, $kernel->getContainer()::class);
    // The containers have the same hash, so should use the same file.
    $this->assertSame($container_file, (new \ReflectionClass($kernel->getContainer()))->getFileName());

    $kernel->invalidateContainer();
    // This should cause the container to be dumped again but with new modules
    // resulting in a new hash.
    $kernel->updateModules(['system' => 'system', 'user' => 'user']);
    $this->assertNotSame($container, $kernel->getContainer());
    $this->assertNotSame($container::class, $kernel->getContainer()::class);
    // The containers have different hashes, so should use different files.
    $new_container_file = (new \ReflectionClass($kernel->getContainer()))->getFileName();
    $this->assertNotSame($container_file, $new_container_file);
    $this->assertFileExists($new_container_file);

    $kernel->garbageCollection();
    // Only the old container file should be deleted.
    $this->assertFileDoesNotExist($container_file);
    $this->assertFileExists($new_container_file);
  }

  /**
   * Tests garbage collection cleans up the real storage even if not dumping.
   */
  public function testGarbageCollectionIgnoresAllowDumping(): void {
    $request = Request::createFromGlobals();
    $kernel = $this->getTestKernel($request, ['system' => 'system']);
    $container_file = (new \ReflectionClass($kernel->getContainer()))->getFileName();

    // Switch modules so a second, superseded container file exists on disk.
    $kernel->updateModules(['system' => 'system', 'user' => 'user']);
    $new_container_file = (new \ReflectionClass($kernel->getContainer()))->getFileName();
    $this->assertNotSame($container_file, $new_container_file);
    $this->assertFileExists($container_file);
    $this->assertFileExists($new_container_file);

    // Simulate a kernel that is no longer allowed to dump or load the
    // container, such as \Drupal\Core\Update\UpdateKernel. The bootstrap
    // container's container_storage service already tracks the currently
    // loaded suffix from the updateModules() call above, so garbage
    // collection should still use it to clean up the real, stored
    // containers.
    (new \ReflectionProperty($kernel, 'allowDumping'))->setValue($kernel, FALSE);

    $kernel->garbageCollection();
    // The superseded container file should still be deleted, even though
    // dumping and loading are disallowed.
    $this->assertFileDoesNotExist($container_file);
    $this->assertFileExists($new_container_file);
  }

  /**
   * Tests that a container is reused when the container hash is matched.
   */
  public function testContainerReuse(): void {
    $request = Request::createFromGlobals();
    $kernel = $this->getTestKernel($request, ['system' => 'system']);
    $container = $kernel->getContainer();
    $container_file_with_system = (new \ReflectionClass($container))->getFileName();
    $this->assertFileExists($container_file_with_system);
    $kernel->updateModules(['system' => 'system', 'user' => 'user']);
    $container = $kernel->getContainer();
    $container_file_with_system_and_user = (new \ReflectionClass($container))->getFileName();
    $this->assertFileExists($container_file_with_system_and_user);
    $this->assertFileExists($container_file_with_system);

    // Test that dump() skips both save() and load() when the class and file
    // both already exist. A new kernel instance boots and loads the
    // system+user container (hash B) from cache. Switching back to
    // system-only triggers dump(hash A): class A is already in memory and
    // the file is on disk, so neither save() nor load() is called.
    $kernel_no_save = $this->getTestKernel($request);
    $kernel_no_save->updateModules(['system' => 'system']);
    $container_no_save = $kernel_no_save->getContainer();
    // Same file path as the original system container (no re-save occurred).
    $this->assertSame($container_file_with_system, (new \ReflectionClass($container_no_save))->getFileName());
    $this->assertFileExists($container_file_with_system);
    // Garbage collection removes the superseded system+user file (the current
    // container is system-only) but leaves the system-only file intact.
    $kernel_no_save->garbageCollection();
    $this->assertFileDoesNotExist($container_file_with_system_and_user);
    $this->assertFileExists($container_file_with_system);

    $kernel->updateModules(['system' => 'system']);
    $container = $kernel->getContainer();
    $this->assertSame($container_file_with_system, (new \ReflectionClass($container))->getFileName());
    // The system+user file was already removed by $kernel_no_save's garbage
    // collection.
    $this->assertFileDoesNotExist($container_file_with_system_and_user);
    $this->assertFileExists($container_file_with_system);

    $kernel->garbageCollection();
    // System+user was already deleted; system-only file is kept as it is the
    // active container.
    $this->assertFileDoesNotExist($container_file_with_system_and_user);
    $this->assertFileExists($container_file_with_system);

    // Ensure that a container is regenerated and saved properly if we switch
    // back again. Sleep 1 second so MTimeProtectedFileStorage advances its
    // mtime-based directory name. Without this, re-saving the same container
    // hash would reuse the same directory and include_once would be a no-op.
    // With it, a new directory is created and include_once on the new path
    // would trigger "Cannot redeclare class" if dump() lacked a class_exists()
    // guard — reproducing the CI environment where kernels span different mtime
    // windows.
    $kernel = $this->getTestKernel($request);
    $this->assertSame($container_file_with_system, (new \ReflectionClass($kernel->getContainer()))->getFileName());
    sleep(1);
    $kernel->updateModules(['system' => 'system', 'user' => 'user']);
    $container = $kernel->getContainer();
    // The class was defined at its original path and is reused from memory —
    // load() is skipped to avoid "Cannot redeclare class" on the new mtime
    // path. The class file path reported by reflection is therefore the
    // original one, not the newly saved mtime path.
    $this->assertSame($container_file_with_system_and_user, (new \ReflectionClass($container))->getFileName());
    // The system+user file was previously deleted by $kernel_no_save's garbage
    // collection. dump() re-saved it to a new mtime-based path. Use glob on the
    // container class directory (stable across mtime changes) to confirm a file
    // exists.
    $this->assertCount(1, glob(dirname($container_file_with_system_and_user) . '/*.php'));
    $this->assertFileDoesNotExist($container_file_with_system_and_user);
    $this->assertFileExists($container_file_with_system);

    $kernel->garbageCollection();
    // The system+user container is the active one and must not be deleted.
    $this->assertCount(1, glob(dirname($container_file_with_system_and_user) . '/*.php'));
    $this->assertFileDoesNotExist($container_file_with_system);
  }

  /**
   * Tests bootstrap_container_definition is merged.
   */
  public function testBootstrapContainerDefinitionMissingServices(): void {
    $kernel = $this->getTestKernel(Request::createFromGlobals(), ['system' => 'system']);

    // Create a custom container storage that records dump() calls via a static
    // property, proving the custom storage is used by the kernel.
    $customStorageClass = get_class(new class () extends NullContainerStorage {

      /**
       * Determines whether the container has been dumped.
       */
      public static bool $dumpCalled = FALSE;

      /**
       * {@inheritdoc}
       */
      public function dump(ContainerBuilder $container, string $suffix): void {
        static::$dumpCalled = TRUE;
      }

    });

    // Set a custom bootstrap_container_definition that only has the legacy
    // services (missing all new container storage services) plus our custom
    // container_storage override.
    $settings = Settings::getAll();
    $settings['bootstrap_container_definition'] = [
      'parameters' => [],
      'services' => [
        'database' => [
          'class' => 'Drupal\Core\Database\Connection',
          'factory' => 'Drupal\Core\Database\Database::getConnection',
          'arguments' => ['default'],
        ],
        'cache.container' => [
          'class' => '\Drupal\Core\Cache\DatabaseBackend',
          'arguments' => [
            '@database',
            '@cache_tags_provider.container',
            'container',
            '@serialization.phpserialize',
            '@datetime.time',
            DatabaseBackend::MAXIMUM_NONE,
          ],
        ],
        'cache_tags_provider.container' => [
          'class' => 'Drupal\Core\Cache\DatabaseCacheTagsChecksum',
          'arguments' => ['@database'],
        ],
        'serialization.phpserialize' => [
          'class' => '\Drupal\Component\Serialization\PhpSerialize',
        ],
        'request_stack' => [
          'class' => 'Symfony\Component\HttpFoundation\RequestStack',
        ],
        'datetime.time' => [
          'class' => 'Drupal\Component\Datetime\Time',
          'arguments' => ['@request_stack'],
        ],
        'container_storage' => [
          'class' => $customStorageClass,
        ],
      ],
    ];
    new Settings($settings);

    // Shutdown and reboot the kernel so boot() reads the custom
    // bootstrap_container_definition from settings.
    $kernel->shutdown();
    // Preserve the module list.
    $kernel->updateModules(['system' => 'system']);
    $this->assertFalse($customStorageClass::$dumpCalled, 'Custom container storage dump() should not have been called yet');
    $kernel->boot();

    $this->assertTrue($customStorageClass::$dumpCalled, 'Custom container storage dump() should have been called');
  }

}
