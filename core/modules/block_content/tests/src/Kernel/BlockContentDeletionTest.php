<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\block\Traits\BlockCreationTrait;

/**
 * Tests that deleting a block clears the cached definitions.
 *
 * @group block_content
 */
class BlockContentDeletionTest extends KernelTestBase {

  use BlockCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block_content', 'field', 'system', 'text', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');
    $this->installConfig(['block_content']);
    $this->container->get('theme_installer')->install(['stark']);
  }

  /**
   * Tests deleting a block_content updates the discovered block plugin.
   *
   * @param bool $installBlockModule
   *   Whether block.module will be installed in this test.
   *
   * @see \Drupal\block_content\Entity\BlockContent::preDelete
   * @dataProvider providerDeletingBlockContentClearsPluginCacheAndDeletesDependentBlocks
   */
  public function testDeletingBlockContentClearsPluginCacheAndDeletesDependentBlocks(bool $installBlockModule): void {
    // Prevent regressions by ensuring Block is not installed.
    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler */
    $moduleHandler = \Drupal::service(ModuleHandlerInterface::class);
    $this->assertFalse($moduleHandler->moduleExists('block'));

    if ($installBlockModule) {
      /** @var \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller */
      $moduleInstaller = \Drupal::service(ModuleInstallerInterface::class);
      $moduleInstaller->install(['block']);
    }

    // Create a block content type.
    $block_content_type = BlockContentType::create([
      'id' => 'spiffy',
      'label' => 'Very spiffy',
      'description' => "Provides a block type that increases your site's spiffy rating by upto 11%",
    ]);
    $block_content_type->save();
    // And a block content entity.
    $block_content = BlockContent::create([
      'info' => 'Spiffy prototype',
      'type' => 'spiffy',
    ]);
    $block_content->save();

    // Make sure the block content provides a derivative block plugin in the
    // block repository.
    /** @var \Drupal\Core\Block\BlockManagerInterface $block_manager */
    $block_manager = $this->container->get('plugin.manager.block');
    $plugin_id = 'block_content' . PluginBase::DERIVATIVE_SEPARATOR . $block_content->uuid();
    $this->assertTrue($block_manager->hasDefinition($plugin_id));

    // Now delete the block content entity.
    $block_content->delete();
    // The plugin should no longer exist.
    $this->assertFalse($block_manager->hasDefinition($plugin_id));

    // Create another block content entity.
    $block_content = BlockContent::create([
      'info' => 'Spiffy prototype',
      'type' => 'spiffy',
    ]);
    $block_content->save();

    $plugin_id = 'block_content' . PluginBase::DERIVATIVE_SEPARATOR . $block_content->uuid();

    if ($installBlockModule) {
      $block = $this->placeBlock($plugin_id, ['region' => 'content', 'theme' => 'stark']);
    }

    // Delete the Block Content.
    $storage = $this->container->get('entity_type.manager')->getStorage('block_content');
    $storage->delete([$block_content]);
    // The plugin should no longer exist.
    $this->assertFalse($block_manager->hasDefinition($plugin_id));

    if ($installBlockModule) {
      // Block config entities using plugins derived from Block Content entities
      // are deleted before the associated Block Content is deleted.
      $this->assertNull($this->container->get('entity_type.manager')->getStorage('block')->loadUnchanged($block->id()));
    }
  }

  /**
   * Data provider.
   *
   * @return \Generator<array{bool}>
   *   Test scenarios.
   */
  public static function providerDeletingBlockContentClearsPluginCacheAndDeletesDependentBlocks(): \Generator {
    yield [TRUE];
    yield [FALSE];
  }

}
