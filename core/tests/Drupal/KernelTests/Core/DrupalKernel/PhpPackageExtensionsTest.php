<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\DrupalKernel;

use Composer\InstalledVersions;
use DrCommandPackage\Command\PhpPackageTestCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Extension\PhpPackageExtensions;
use Drupal\KernelTests\DrupalApplicationTesterTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\serialization\Serializer\Serializer;
use Excluded\ExcludedService;
use NotAnExtension\NoService;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ServicesPackage\OtherClass;
use ServicesPackage\TestService;
use ServicesSecond\SecondTestService;
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
   */
  protected function setUp(): void {
    parent::setUp();

    // Find and collect the test packages.
    $packages = [];
    $vendor_composer_dir = dirname((new \ReflectionClass(InstalledVersions::class))->getFileName());
    $fixture_path = Path::canonicalize(dirname(__DIR__, 4) . '/fixtures/PhpPackageTestExtensions');
    $composerFiles = Finder::create()->files()->name('composer.json')->in($fixture_path)->depth(1);
    foreach ($composerFiles as $file) {
      $package = json_decode($file->getContents(), TRUE, flags: JSON_THROW_ON_ERROR);
      // Composer adds info about where the package was installed to.
      $package['install-path'] = Path::makeRelative($file->getPath(), $vendor_composer_dir);
      $packages[] = $package;
      foreach ($package['autoload']['psr-4'] ?? [] as $prefix => $path) {
        // We also register the package in tha class loader.
        $this->classLoader->addPsr4($prefix, $file->getPath() . '/' . $path);
      }
    }

    // Swap out the php package extensions in the kernel with the extra
    // packages and rebuild the container. This affects all kernels in the same
    // process. In particular this also means that the kernel in the dr command
    // tester gets built with the same php package extensions.
    (function () use ($packages) {
      // @phpstan-ignore staticProperty.notFound
      static::$phpPackageExtensions = (function () use ($packages) {
        return new PhpPackageExtensions(
          array_merge($this->installedPackages, $packages),
          array_merge($this->excludedPackages, ['package-test/excluded-package']),
          $this->vendorComposerDir,
          $this->appRoot,
        );
        // @phpstan-ignore staticProperty.notFound
      })->call(static::$phpPackageExtensions);
    })->call(\Drupal::service('kernel'));

    $this->rebuildContainer();
  }

  /**
   * Tests that the packages are loaded into the container.
   */
  public function testPackageDetailsAreDiscovered(): void {
    // The package's PSR-4 namespace was merged into container.namespaces.
    $namespaces = $this->container->getParameter('container.namespaces');
    self::assertArrayHasKey('DrCommandPackage', $namespaces);
    self::assertArrayHasKey('ServicesPackage', $namespaces);
    self::assertArrayNotHasKey('NotAnExtension', $namespaces);
    self::assertArrayNotHasKey('Excluded', $namespaces);
    // All classes are autoloaded.
    self::assertTrue(class_exists(PhpPackageTestCommand::class));
    self::assertTrue(class_exists(TestService::class));
    self::assertTrue(class_exists(SecondTestService::class));
    self::assertTrue(class_exists(NoService::class));
    self::assertTrue(class_exists(OtherClass::class));
    self::assertTrue(class_exists(ExcludedService::class));
    // But not all are registered in the service container.
    self::assertTrue($this->container->has(PhpPackageTestCommand::class));
    self::assertTrue($this->container->has(TestService::class));
    self::assertTrue($this->container->has(SecondTestService::class));
    self::assertFalse($this->container->has(NoService::class));
    self::assertFalse($this->container->has(OtherClass::class));
    self::assertFalse($this->container->has(ExcludedService::class));

    // The DrCommandPackage registered a service provider.
    self::assertTrue($this->container->getParameter('services_package_test.service_provider_registered'));

    // The command the package provides under its Command namespace is picked up
    // by Drupal\Core\DependencyInjection\Compiler\ConsoleCompilerPass and can
    // be run via dr.
    $tester = $this->applicationTester();
    $code = $tester->run(['command' => 'php-command:test']);
    self::assertSame(Command::SUCCESS, $code, $tester->getDisplay());
    self::assertStringContainsString('[OK] PHP dr tested.', $tester->getDisplay());

    // The service can be retrieved from the container.
    $service = $this->container->get(TestService::class);
    $dependencies = $service->getConfiguredDependencies();
    self::assertInstanceOf(ConfigFactoryInterface::class, $dependencies['config.factory']);
    self::assertNull($dependencies['serializer']);
    self::assertCount(1, $this->container->get(SecondTestService::class)->getConfiguredDependencies());

    // When the module is installed the service is set up with it.
    $this->enableModules(['serialization']);
    $dependencies = $this->container->get(TestService::class)->getConfiguredDependencies();
    self::assertInstanceOf(ConfigFactoryInterface::class, $dependencies['config.factory']);
    self::assertInstanceOf(Serializer::class, $dependencies['serializer']);
    self::assertCount(2, $this->container->get(SecondTestService::class)->getConfiguredDependencies());
  }

}
