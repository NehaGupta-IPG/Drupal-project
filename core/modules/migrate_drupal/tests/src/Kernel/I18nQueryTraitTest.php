<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_drupal\Kernel;

use Drupal\KernelTests\AutoloaderTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Tests instantiating migrate source plugins using I18nQueryTrait.
 *
 * @group migrate_drupal
 */
class I18nQueryTraitTest extends KernelTestBase {

  use AutoloaderTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'menu_link_content',
    'migrate',
    'migrate_drupal',
    'taxonomy',
  ];

  /**
   * Tests instantiating migrate source plugins using I18nQueryTrait.
   *
   * I18nQueryTrait was originally in the content_translation module, which
   * could lead to fatal errors instantiating the source plugins that use it
   * when the content_translation module was not installed.
   *
   * @param string $plugin_id
   *   The ID of a Migrate source plugin that uses I18nQueryTrait.
   *
   * @dataProvider providerI18nQueryTraitPlugins
   */
  public function testMigrateSourcePluginUsingI18nQueryTraitDiscovery(string $plugin_id): void {
    // Namespace for uninstalled module content_translation needs to be removed
    // for this test.
    static::disablePsr4ForUninstalledModules($this->classLoader, ['content_translation']);

    $migration = $this->createMock(MigrationInterface::class);
    $this->assertInstanceOf(SourcePluginBase::class, \Drupal::service('plugin.manager.migrate.source')->createInstance($plugin_id, [], $migration));
  }

  /**
   * Provides data for testMigrateSourcePluginUsingI18nQueryTraitDiscovery().
   */
  public static function providerI18nQueryTraitPlugins(): array {
    return [
      ['d6_box_translation'],
      ['d7_block_custom_translation'],
      ['d6_menu_link_translation'],
      ['d7_menu_link_translation'],
      ['d7_term_localized_translation'],
    ];
  }

}
