<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\DrupalKernel;

use Drupal\PackageExtensionData;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Loader\JsonLoader;
use Composer\Repository\InstalledArrayRepository;
use Excluded\ExcludedServiceProvider;
use MultiProviderPackage\ProviderOne;
use MultiProviderPackage\ProviderTwo;
use DrCommandPackage\Command\PhpPackageTestCommand;
use Drupal\Composer\Plugin\Scaffold\GeneratePackageExtensionData;
use Drupal\Core\DrupalKernel;
use Drupal\KernelTests\DrupalApplicationTesterTrait;
use Drupal\KernelTests\KernelTestBase;
use NotAnExtension\NoService;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * Tests that a plain Composer package can contribute to the container.
 *
 * This test registers a few fixture packages into the installed composer
 * packages and asserts that their services are available in Drupal's container.
 *
 * @see \Drupal\Core\DrupalKernel::getPhpPackageExtensions()
 */
#[Group('DrupalKernel')]
#[CoversMethod(DrupalKernel::class, 'getPhpPackageExtensions')]
#[RunTestsInSeparateProcesses]
class PhpPackageExtensionsTest extends KernelTestBase {

  use DrupalApplicationTesterTrait;

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore phpunit.callParent
   */
  protected function setUp(): void {
    // We do not call parent::setup because we want to do things after the setup
    // but before the kernel is booted.
    chdir($this->root);
    $this->initFileCache();
    $this->bootEnvironment();

    // Find and collect the test packages.
    $packages = [];
    $fixture_path = Path::canonicalize(dirname(__DIR__, 4) . '/fixtures/PhpPackageTestExtensions');
    $composerFiles = Finder::create()->files()->name('composer.json')->in($fixture_path)->depth(1);
    $loader = new JsonLoader(new ArrayLoader());
    foreach ($composerFiles as $file) {
      $package = $loader->load($file->getContents());
      $packages[] = $package;
      foreach ($package->getAutoload()['psr-4'] ?? [] as $prefix => $path) {
        // We also register the package in tha class loader.
        $this->classLoader->addPsr4($prefix, $file->getPath() . '/' . $path);
      }
    }

    $root_package = new ArrayLoader()->load([
      'name' => 'root',
      'version' => '1.0.0',
      'extra' => ['drupal' => ['exclude-package' => ['package-test/excluded-package']]],
    ]);

    $this->vfsRoot->addChild(vfsStream::newDirectory('vendor'));
    $classPath = vfsStream::url('root/vendor/PackageExtensionData.php');
    file_put_contents($classPath, GeneratePackageExtensionData::getCode($root_package, new InstalledArrayRepository($packages)));

    $this->classLoader->addClassMap([
      PackageExtensionData::class => $classPath,
    ]);

    // Now boot the kernel with the class set up.
    $this->bootKernel();
  }

  /**
   * Tests that the packages are loaded into the container.
   */
  public function testPackageDetailsAreDiscovered(): void {
    // The package's PSR-4 namespace was merged into container.namespaces.
    $namespaces = $this->container->getParameter('container.namespaces');
    self::assertArrayHasKey('DrCommandPackage', $namespaces);
    self::assertArrayNotHasKey('NotAnExtension', $namespaces);
    self::assertArrayNotHasKey('Excluded', $namespaces);
    // All classes are autoloaded.
    self::assertTrue(class_exists(PhpPackageTestCommand::class));
    self::assertTrue(class_exists(NoService::class));
    self::assertTrue(class_exists(ProviderOne::class));
    self::assertTrue(class_exists(ProviderTwo::class));
    self::assertTrue(class_exists(ExcludedServiceProvider::class));
    // But not all are registered in the service container.
    self::assertTrue($this->container->has(PhpPackageTestCommand::class));
    self::assertFalse($this->container->has(NoService::class));

    // Packages can register service providers.
    self::assertTrue($this->container->getParameter('multi_provider_test.service_provider_one_registered'));
    self::assertTrue($this->container->getParameter('multi_provider_test.service_provider_two_registered'));
    self::assertFalse($this->container->hasParameter('indirect_dependency_service_provider.unwanted_changes'));

    // The command the package provides under its Command namespace is picked up
    // by Drupal\Core\DependencyInjection\Compiler\ConsoleCompilerPass and can
    // be run via dr.
    $tester = $this->applicationTester();
    $code = $tester->run(['command' => 'php-command:test']);
    self::assertSame(Command::SUCCESS, $code, $tester->getDisplay());
    self::assertStringContainsString('[OK] PHP dr tested.', $tester->getDisplay());
  }

}
