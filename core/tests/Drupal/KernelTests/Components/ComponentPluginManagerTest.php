<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Components;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Plugin\Discovery\DirectoryWithMetadataPluginDiscovery;
use Drupal\Core\Render\Component\Exception\ComponentNotFoundException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the component plugin manager.
 */
#[Group('sdc')]
#[RunTestsInSeparateProcesses]
class ComponentPluginManagerTest extends ComponentKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'sdc_test',
    'sdc_test_replacements',
    'sdc_test_plugin_manager',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $themes = ['sdc_theme_test'];

  /**
   * Test that components render correctly.
   */
  public function testFindEmptyMetadataFile(): void {
    // Test that empty component metadata files are valid, since there is no
    // required property.
    $this->assertNotEmpty(
      $this->manager->find('sdc_theme_test:bar'),
    );
    // Test that if the folder name does not match the machine name, the
    // component is still available.
    $this->assertNotEmpty(
      $this->manager->find('sdc_theme_test:foo'),
    );
  }

  /**
   * Test that the machine name is grabbed from the *.component.yml.
   *
   * And not from the enclosing directory.
   */
  public function testMismatchingFolderName(): void {
    $this->expectException(ComponentNotFoundException::class);
    $this->manager->find('sdc_theme_test:mismatching-folder-name');
  }

  /**
   * Test component definitions caching depending on twig debug/cache settings.
   *
   * @param bool $twigDebug
   *   Whether twig debug is enabled.
   * @param bool $cacheEnabled
   *   Whether cache is enabled.
   * @param bool $expectCached
   *   Whether we expect the definitions are cached or not.
   *
   * @dataProvider providerTestComponentCachingDependingOnDevelopmentSettings
   */
  public function testComponentCachingDependingOnDevelopmentSettings(bool $twigDebug, bool $cacheEnabled, bool $expectCached): void {
    // Set the manager to a local variable, so we can type hint it.
    /** @var \Drupal\sdc_test_plugin_manager\Theme\TestComponentPluginManager $manager */
    $manager = $this->manager;

    $firstObtainedDefinitions = $manager->getDefinitions();

    // Set a mock discovery in the manager.
    $discovery = $this->createMock(DirectoryWithMetadataPluginDiscovery::class);
    $discovery->method('getDefinitions')
      ->willReturn(array_slice($firstObtainedDefinitions, 0, -1, TRUE));
    $manager->setDiscovery($discovery);

    // Set a mock container in the manager.
    $container = $this->createMock(ContainerBuilder::class);
    $container->method('getParameter')
      ->with('twig.config')
      ->willReturn([
        'debug' => $twigDebug,
        'cache' => $cacheEnabled,
      ]);
    $manager->setContainer($container);

    // Assert over definition keys, since it's the cleanest
    // way to check if the definitions are the same after we
    // removed one of them.
    $firstObtainedDefinitionKeys = array_keys($firstObtainedDefinitions);
    $secondObtainedDefinitionKeys = array_keys($manager->getDefinitions());
    if ($expectCached) {
      $this->assertEquals($firstObtainedDefinitionKeys, $secondObtainedDefinitionKeys);
    }
    else {
      $this->assertNotEquals($firstObtainedDefinitionKeys, $secondObtainedDefinitionKeys);
    }
  }

  /**
   * Data provider for testComponentCachingDependingOnDevelopmentSettings().
   */
  public static function providerTestComponentCachingDependingOnDevelopmentSettings(): array {
    return [
      'Debug enabled, cache enabled' => [TRUE, TRUE, FALSE],
      'Debug enabled, cache disabled' => [TRUE, FALSE, FALSE],
      'Debug disabled, cache enabled' => [FALSE, TRUE, TRUE],
      'Debug disabled, cache disabled' => [FALSE, FALSE, FALSE],
    ];
  }

}
