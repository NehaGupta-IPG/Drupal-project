<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\block_content\Plugin\Block\BlockContentBlock;
use Drupal\block_content_test\Hook\BlockContentTestHooks;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\Plugin\Block\Broken;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\TestTools\Random;

/**
 * Tests the broken message.
 *
 * @coversDefaultClass \Drupal\Core\Block\Plugin\Block\Broken
 *
 * @group block_content
 */
final class BlockContentBrokenTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'administer_blocks_permission_test',
    'block_content',
    'block_content_test',
    'field',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');
  }

  /**
   * Tests broken block plugin message.
   *
   * @param bool $undefineBlockPlugin
   *   Whether the block plugin should be artificially undefined.
   * @param bool $hasAdministerBlocksPermission
   *   Whether the current user has the 'administer blocks' permission.
   * @param class-string<\Drupal\Core\Block\BlockPluginInterface> $expectedPluginClass
   *   The expected plugin class.
   * @param 'rendered-block'|'empty'|'broken-message' $expectedBuild
   *   The expected plugin build.
   *
   * @dataProvider providerBrokenMessage
   */
  public function testBrokenMessage(bool $undefineBlockPlugin, bool $hasAdministerBlocksPermission, string $expectedPluginClass, string $expectedBuild): void {
    [$blockContentType, $fieldConfig] = static::setupEntityType();

    $text = $this->randomMachineName();
    $blockContent = BlockContent::create([
      'type' => $blockContentType->id(),
      'info' => $undefineBlockPlugin ? BlockContentTestHooks::UNDEFINE_BLOCK : '',
      $fieldConfig->getName() => $text,
    ]);
    $blockContent->save();

    if ($hasAdministerBlocksPermission) {
      $this->setCurrentUser($this->createUser(['administer blocks']));
    }

    $plugin = static::blockManager()->createInstance('block_content:' . $blockContent->uuid());
    $this->assertInstanceOf($expectedPluginClass, $plugin);

    $build = $plugin->build();
    if ($expectedBuild === 'rendered-block') {
      $this->assertEquals($text, $build[$fieldConfig->getName()][0]['#context']['value']);
    }
    elseif ($expectedBuild === 'empty') {
      $this->assertEquals([], $build);
    }
    elseif ($expectedBuild === 'broken-message') {
      $this->assertEquals('This block is broken or missing. You may be missing content or you might need to install the original module.', (string) $build['message']['#markup']);
    }
  }

  /**
   * Data provider.
   *
   * @return \Generator<array{bool, bool, class-string<\Drupal\Core\Block\BlockPluginInterface>, 'rendered-block'|'empty'|'broken-message'}>
   *   The test data.
   */
  public static function providerBrokenMessage(): \Generator {
    yield [FALSE, FALSE, BlockContentBlock::class, 'rendered-block'];
    yield [FALSE, TRUE, BlockContentBlock::class, 'rendered-block'];
    yield [TRUE, FALSE, Broken::class, 'empty'];
    yield [TRUE, TRUE, Broken::class, 'broken-message'];
  }

  /**
   * Setup bundle, field config, entity display.
   *
   * @return array{\Drupal\block_content\BlockContentTypeInterface, \Drupal\field\FieldConfigInterface}
   *   The block content type and field config.
   */
  private static function setupEntityType(): array {
    $blockContentType = BlockContentType::create([
      'id' => Random::machineName(),
      'label' => Random::string(),
    ]);
    $blockContentType->save();
    $fieldStorage = FieldStorageConfig::create([
      'entity_type' => 'block_content',
      'field_name' => Random::machineName(),
      'type' => 'string',
    ]);
    $fieldStorage->save();
    $fieldConfig = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => $blockContentType->id(),
    ]);
    $fieldConfig->save();

    EntityViewDisplay::create([
      'targetEntityType' => 'block_content',
      'bundle' => $blockContentType->id(),
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent($fieldStorage->getName(), [
      'type' => 'string',
      'settings' => [],
    ])->save();

    return [$blockContentType, $fieldConfig];
  }

  /**
   * Block plugin manager.
   */
  private static function blockManager(): BlockManagerInterface {
    /** @var \Drupal\Core\Block\BlockManagerInterface */
    return \Drupal::service(BlockManagerInterface::class);
  }

}
