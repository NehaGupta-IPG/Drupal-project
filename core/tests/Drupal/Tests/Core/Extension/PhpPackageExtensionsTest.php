<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Extension;

use Drupal\Core\Extension\PhpPackageExtensions;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the pure transform of composer package metadata into extension data.
 */
#[CoversClass(PhpPackageExtensions::class)]
#[Group('Extension')]
class PhpPackageExtensionsTest extends UnitTestCase {

  /**
   * Tests that parsing the packages works.
   */
  public function testMultiplePackages(): void {
    $packages[] = [
      'name' => 'package-test/services-package',
      'autoload' => ['psr-4' => ['ServicesPackage\\' => 'src']],
      'extra' => ['drupal' => ['container_yamls' => 'package-services.yml']],
      'install-path' => '../package-test/services-package',
    ];
    $packages[] = [
      'name' => 'package-test/provider-package',
      'autoload' => ['psr-4' => ['ProviderPackage\\' => 'src']],
      'extra' => ['drupal' => ['providers' => 'ProviderPackage\\ServiceProvider']],
      'install-path' => '../package-test/provider-package',
    ];
    $packages[] = [
      'name' => 'package-test/multi-services',
      'autoload' => ['psr-4' => ['MultiServicesPackage\\' => 'src']],
      'extra' => ['drupal' => ['container_yamls' => ['package-services.yml', 'second-services.yml']]],
      'install-path' => '../package-test/multi-services',
    ];
    $packages[] = [
      'name' => 'package-test/multi-provider',
      'autoload' => ['psr-4' => ['MultiProviderPackage\\' => 'src']],
      'extra' => [
        'drupal' => [
          'providers' => [
            'MultiProviderPackage\\ProviderOne',
            'MultiProviderPackage\\ProviderTwo',
          ],
        ],
      ],
      'install-path' => '../package-test/multi-package',
    ];
    $packages[] = [
      'name' => 'package-test/not-an-extension',
      'autoload' => ['psr-4' => ['NotAnExtension\\' => 'src']],
      'extra' => ['drupal' => ['note' => 'This is not an extension']],
      'install-path' => '../package-test/not-an-extension',
    ];
    $packages[] = [
      'name' => 'package-test/excluded-package',
      'autoload' => ['psr-4' => ['Excluded\\' => 'src']],
      'extra' => ['drupal' => ['container_yamls' => 'services.yml']],
      'install-path' => '../package-test/excluded-package',
    ];
    $packages[] = [
      'name' => 'package-test/multi-dir-package',
      'autoload' => ['psr-4' => ['MultiDirPackage\\' => ['src', 'lib']]],
      'extra' => ['drupal' => ['container_yamls' => 'services.yml']],
      'install-path' => '../package-test/multi-dir-package',
    ];
    $packages[] = [
      'name' => 'package-test/just-namespace-registered',
      'autoload' => ['psr-4' => ['Just\\Namespace\\Registered\\' => ['src']]],
      'extra' => ['drupal' => ['providers' => []]],
      'install-path' => '../package-test/just-namespace-registered',
    ];
    $packages[] = [
      'name' => 'drupal/hybrid',
      'autoload' => ['psr-4' => ['Drupal\\hybrid\\' => 'src']],
      'extra' => ['drupal' => ['container_yamls' => 'hybrid.services.yml']],
      'install-path' => '../../web/modules/contrib/hybrid',
    ];
    $extensions = new PhpPackageExtensions(
      installedPackages: $packages,
      excludedPackages:  ['package-test/excluded-package', 'excluded-package/not-in-list'],
      vendorComposerDir: '/vendor/composer',
      appRoot: '/web',
    );

    self::assertSame(
      [
        'package-test/services-package' => '/vendor/package-test/services-package/package-services.yml',
        'package-test/multi-services:0' => '/vendor/package-test/multi-services/package-services.yml',
        'package-test/multi-services:1' => '/vendor/package-test/multi-services/second-services.yml',
        'package-test/multi-dir-package' => '/vendor/package-test/multi-dir-package/services.yml',
        'drupal/hybrid' => '/web/modules/contrib/hybrid/hybrid.services.yml',
      ],
      $extensions->getContainerYamls(),
    );

    self::assertSame(
      [
        'package-test/provider-package' => 'ProviderPackage\\ServiceProvider',
        'package-test/multi-provider:0' => 'MultiProviderPackage\\ProviderOne',
        'package-test/multi-provider:1' => 'MultiProviderPackage\\ProviderTwo',
      ],
      $extensions->getProviders(),
    );

    self::assertSame(
      [
        'ServicesPackage' => '../vendor/package-test/services-package/src',
        'ProviderPackage' => '../vendor/package-test/provider-package/src',
        'MultiServicesPackage' => '../vendor/package-test/multi-services/src',
        'MultiProviderPackage' => '../vendor/package-test/multi-package/src',
        'MultiDirPackage' => [
          '../vendor/package-test/multi-dir-package/src',
          '../vendor/package-test/multi-dir-package/lib',
        ],
        'Just\\Namespace\\Registered' => '../vendor/package-test/just-namespace-registered/src',
        'Drupal\\hybrid' => 'modules/contrib/hybrid/src',
      ],
      $extensions->getNamespaces(),
    );
  }

  /**
   * Tests that fromFilePaths() reads and merges the files at the given paths.
   */
  public function testFromFilePathsReadsInstalledAndRootComposerJson(): void {
    $vfs = vfsStream::setup('root', NULL, [
      'vendor' => [
        'composer' => [
          'installed.json' => json_encode([
            'packages' => [
              [
                'name' => 'package-test/services-package',
                'install-path' => '../../package-test/services-package',
                'autoload' => ['psr-4' => ['ServicesPackage\\' => 'src']],
                'extra' => ['drupal' => ['container_yamls' => 'package-services.yml']],
              ],
              [
                'name' => 'package-test/excluded-package',
                'install-path' => '../../package-test/excluded-package',
                'autoload' => ['psr-4' => ['Excluded\\' => 'src']],
                'extra' => ['drupal' => ['container_yamls' => 'services.yml']],
              ],
            ],
          ], JSON_THROW_ON_ERROR),
        ],
      ],
      'composer.json' => json_encode([
        'extra' => ['drupal' => ['exclude-package' => ['package-test/excluded-package']]],
      ], JSON_THROW_ON_ERROR),
    ]);

    $extensions = PhpPackageExtensions::fromFilePaths(
      vendorComposerDir: $vfs->url() . '/vendor/composer',
      projectRoot: $vfs->url(),
      appRoot: $vfs->url(),
    );

    self::assertSame(
      ['package-test/services-package' => $vfs->url() . '/package-test/services-package/package-services.yml'],
      $extensions->getContainerYamls(),
    );
    self::assertArrayNotHasKey('Excluded', $extensions->getNamespaces());
  }

}
