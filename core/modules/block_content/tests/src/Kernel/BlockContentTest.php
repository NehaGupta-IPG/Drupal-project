<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Kernel;

use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\block_content\Hook\BlockContentBlockHooks;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the block content.
 *
 * @group block_content
 */
class BlockContentTest extends KernelTestBase {

  use UserCreationTrait;
  use StringTranslationTrait;

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
   * Tests entity operations from Block config to Block Content.
   */
  public function testOperationLinks(): void {
    // Create a block content type.
    BlockContentType::create([
      'id' => 'spiffy',
      'label' => 'Very spiffy',
      'description' => "Provides a block type that increases your site's spiffy rating by up to 11%",
    ])->save();
    // And a block content entity.
    $block_content = BlockContent::create([
      'info' => 'Spiffy prototype',
      'type' => 'spiffy',
    ]);
    $block_content->save();
    $block = Block::create([
      'plugin' => 'block_content' . PluginBase::DERIVATIVE_SEPARATOR . $block_content->uuid(),
      'region' => 'content',
      'id' => 'machine_name',
      'theme' => 'stark',
    ]);

    $operation = 'block-edit';

    /** @var \Drupal\block_content\Hook\BlockContentBlockHooks $blockHooks */
    $blockHooks = \Drupal::service(BlockContentBlockHooks::class);
    $this->assertArrayNotHasKey($operation, $blockHooks->entityOperation($block));

    $this->setUpCurrentUser(['uid' => 2], ['edit any spiffy block content']);
    $this->assertEquals([
      'title' => 'Edit block',
      'url' => $block_content->toUrl('edit-form')->setOptions([]),
      'weight' => 50,
    ], $blockHooks->entityOperation($block)[$operation]);
  }

}
