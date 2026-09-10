<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Kernel;

use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Component\Plugin\PluginBase;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests cache metadata for BlockContentBlock plugin.
 */
#[Group('block_content')]
#[RunTestsInSeparateProcesses]
class BlockContentCacheTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'block_content', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');
  }

  /**
   * Tests the cache tags for BlockContentBlock.
   */
  public function testBlockContentBlockCacheData(): void {
    BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic',
    ])->save();

    $block_content = BlockContent::create([
      'info' => 'Cache test',
      'type' => 'basic',
    ]);
    $block_content->save();

    $block = Block::create([
      'plugin' => 'block_content' . PluginBase::DERIVATIVE_SEPARATOR . $block_content->uuid(),
      'region' => 'content',
      'id' => 'cache_test',
      'theme' => 'stark',
    ]);

    $plugin = $block->getPlugin();
    $this->assertContains('block_content:' . $block_content->id(), $plugin->getCacheTags());
    foreach ($block_content->getCacheContexts() as $context) {
      $this->assertContains($context, $plugin->getCacheContexts());
    }
    $this->assertSame($block_content->getCacheMaxAge(), $plugin->getCacheMaxAge());
  }

}
