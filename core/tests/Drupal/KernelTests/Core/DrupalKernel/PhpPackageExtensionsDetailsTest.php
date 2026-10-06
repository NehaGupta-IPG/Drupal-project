<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\DrupalKernel;

use Composer\InstalledVersions;
use DrCommandPackage\Command\PhpPackageTestCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DrupalKernel;
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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * Tests that a plain Composer package can contribute to the container.
 *
 * This test registers a few fixture packages into the installed composer
 * packages and asserts that their services are available in Drupal's container.
 *
 * @see \Drupal\Core\DrupalKernel::getPhpPackageExtensionsDetails()
 */
#[Group('DrupalKernel')]
#[CoversMethod(DrupalKernel::class, 'getPhpPackageExtensionsDetails')]
#[RunTestsInSeparateProcesses]
class PhpPackageExtensionsDetailsTest extends KernelTestBase {

  use DrupalApplicationTesterTrait;

  /**
   * Path to the real vendor/composer/installed.json file.
   */
  private string $installedJsonPath;

  /**
   * The untouched contents of installed.json, to restore after the test.
   */
  private string $originalInstalledJson;

  /**
   * Path to the real composer.json file.
   */
  private string $composerJsonPath;

  /**
   * The untouched contents of composer.json, to restore after the test.
   */
  private string $originalComposerJson;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Back up the file composer wrote since we modify it in this test.
    $vendor_composer_dir = dirname((new \ReflectionClass(InstalledVersions::class))->getFileName());
    $this->installedJsonPath = $vendor_composer_dir . '/installed.json';
    $this->originalInstalledJson = file_get_contents($this->installedJsonPath);

    $project_root = dirname($vendor_composer_dir, 2);
    $this->composerJsonPath = $project_root . '/composer.json';
    $this->originalComposerJson = (string) file_get_contents($this->composerJsonPath);

    // Make sure the real file is restored even if this process dies before
    // tearDown() runs.
    $installed_json_path = $this->installedJsonPath;
    $original_installed_json = $this->originalInstalledJson;
    $composer_json_path = $this->composerJsonPath;
    $original_composer_json = $this->originalComposerJson;
    register_shutdown_function(static function () use ($installed_json_path, $original_installed_json, $composer_json_path, $original_composer_json): void {
      file_put_contents($installed_json_path, $original_installed_json);
      file_put_contents($composer_json_path, $original_composer_json);
    });

    // Find all php packages in the fixture directory and pretend they were
    // installed by composer.
    $class_loader = require $project_root . '/autoload.php';
    $installed = json_decode($this->originalInstalledJson, TRUE, 512, JSON_THROW_ON_ERROR);
    $fixture_path = Path::canonicalize(dirname(__DIR__, 4) . '/fixtures/PhpPackageTestExtensions');
    $packages = Finder::create()->files()->name('composer.json')->in($fixture_path)->depth(1);
    foreach ($packages as $composer) {
      $package = json_decode($composer->getContents(), TRUE, flags: JSON_THROW_ON_ERROR);
      // Composer adds info about where the package was installed to.
      $package['install-path'] = Path::makeRelative($composer->getPath(), $vendor_composer_dir);
      $installed['packages'][] = $package;
      foreach ($package['autoload']['psr-4'] ?? [] as $prefix => $path) {
        // We also register the package in tha class loader.
        $class_loader->addPsr4($prefix, $composer->getPath() . '/' . $path);
      }

    }
    file_put_contents($this->installedJsonPath, json_encode($installed, JSON_THROW_ON_ERROR));

    // Exclude a package.
    $data = json_decode($this->originalComposerJson, TRUE, flags: JSON_THROW_ON_ERROR);
    $data['extra']['drupal']['exclude-package'] = ['package-test/excluded-package'];
    file_put_contents($this->composerJsonPath, json_encode($data, JSON_THROW_ON_ERROR));

    // Now that composer is set up we can continue to boot the test kernel.
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    file_put_contents($this->installedJsonPath, $this->originalInstalledJson);
    file_put_contents($this->composerJsonPath, $this->originalComposerJson);
    parent::tearDown();
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
    self::assertTrue(class_exists(NoService::class));
    self::assertTrue(class_exists(OtherClass::class));
    self::assertTrue(class_exists(ExcludedService::class));
    // But not all are registered in the service container.
    self::assertTrue($this->container->has(PhpPackageTestCommand::class));
    self::assertTrue($this->container->has(TestService::class));
    self::assertFalse($this->container->has(NoService::class));
    self::assertFalse($this->container->has(OtherClass::class));
    self::assertFalse($this->container->has(ExcludedService::class));

    // The DrCommandPackage registered a service provider.
    self::assertTrue($this->container->getParameter('dr_command_package_test.service_provider_registered'));

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

    // When the module is installed the service is set up with it.
    $this->enableModules(['serialization']);
    $dependencies = $this->container->get(TestService::class)->getConfiguredDependencies();
    self::assertInstanceOf(ConfigFactoryInterface::class, $dependencies['config.factory']);
    self::assertInstanceOf(Serializer::class, $dependencies['serializer']);
  }

}
