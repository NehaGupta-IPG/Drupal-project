<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Kernel;

use Drupal\block\Entity\Block;
use Drupal\block_content\BlockUsage\BlockContentBlockUsageInterface;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Component\Plugin\PluginBase;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the usage of Block Content usage by Block configs.
 *
 * @coversDefaultClass \Drupal\block_content\BlockUsage\BlockContentBlockUsage
 * @covers \Drupal\block_content\BlockUsage\BlockContentUsageList
 *
 * @group block_content
 */
final class BlockContentBlockUsageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'block_content',
    'field',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');
    $this->installConfig(['block_content']);
    $this->container->get('theme_installer')->install(['stark']);
  }

  /**
   * Test which blocks are dependencies of Block Content with usage service.
   */
  public function testUsage(): void {
    $blockContentType = BlockContentType::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
    ]);
    $blockContent = BlockContent::create([
      'type' => $blockContentType->id(),
      'info' => $this->randomString(),
    ]);
    $blockContent->save();

    $usage = static::blockContentBlockUsage()->getUsage($blockContent);
    $this->assertEquals(0, $usage->count());
    $this->assertEquals([], \iterator_to_array($usage));

    $block = Block::create([
      'id' => $this->randomMachineName(),
      'plugin' => sprintf('block_content%s%s', PluginBase::DERIVATIVE_SEPARATOR, $blockContent->uuid()),
      'region' => 'content',
      'theme' => 'stark',
    ]);
    $block->save();

    // Never re-create $usage, so we test it's querying the database each time,
    // not memoized values.
    $this->assertEquals(1, $usage->count());
    $this->assertArrayHasKey($block->id(), \iterator_to_array($usage));
  }

  /**
   * Block Content Block Usage service.
   */
  private static function blockContentBlockUsage(): ?BlockContentBlockUsageInterface {
    /** @var \Drupal\block_content\BlockUsage\BlockContentBlockUsageInterface */
    return \Drupal::service(BlockContentBlockUsageInterface::class);
  }

}
