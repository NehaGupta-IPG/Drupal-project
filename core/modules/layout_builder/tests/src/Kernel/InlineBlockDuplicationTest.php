<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests inline block duplication decisions when a parent entity is saved.
 *
 * @see \Drupal\layout_builder\Plugin\Block\InlineBlock::saveBlockContent()
 * @see \Drupal\layout_builder\InlineBlockEntityOperations::handlePreSave()
 */
#[Group('layout_builder')]
#[RunTestsInSeparateProcesses]
class InlineBlockDuplicationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'layout_discovery',
    'entity_test',
    'block_content',
    'field',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('layout_builder', ['inline_block_usage']);
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('block_content');

    BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic block',
      'revision' => 1,
    ])->save();

    $display = LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->enableLayoutBuilder();
    $display->setOverridable();
    $display->save();
  }

  /**
   * Tests that a block still referenced by block_revision_id is duplicated.
   *
   * A regular entity duplicate still references the original block
   * revision, so Layout Builder must fork the block and register usage
   * against the new parent.
   */
  public function testDuplicateBlockWhenRevisionIdStillPresent(): void {
    $usage = $this->container->get('inline_block.usage');
    $block_storage = $this->container->get('entity_type.manager')->getStorage('block_content');

    // First save: the block is new, so nothing is duplicated yet.
    $entity1 = EntityTest::create(['name' => 'Test entity']);
    $block = BlockContent::create([
      'type' => 'basic',
      'reusable' => FALSE,
      'info' => 'Original block',
    ]);
    $section = new Section('layout_onecol', [], [
      'the-component' => new SectionComponent('the-component', 'content', [
        'id' => 'inline_block:basic',
        'block_serialized' => serialize($block),
      ]),
    ]);
    $entity1->{OverridesSectionStorage::FIELD_NAME} = $section;
    $entity1->save();

    $original_config = $entity1->{OverridesSectionStorage::FIELD_NAME}->getSections()[0]
      ->getComponent('the-component')->getPlugin()->getConfiguration();
    $this->assertNotEmpty($original_config['block_revision_id'], 'The established block is referenced by a persisted revision.');
    $original_block_id = $original_config['block_id'];

    // A regular entity duplicate still references the original block revision.
    $entity2 = $entity1->createDuplicate();
    $this->assertTrue($entity2->isNew());
    $entity2->save();

    $cloned_config = $entity2->{OverridesSectionStorage::FIELD_NAME}->getSections()[0]
      ->getComponent('the-component')->getPlugin()->getConfiguration();
    $cloned_block_id = $cloned_config['block_id'];

    $this->assertNotEquals($original_block_id, $cloned_block_id, 'The cloned parent entity receives a distinct inline block, not the original.');
    $this->assertNotEmpty($block_storage->load($original_block_id), 'The original block still exists.');
    $this->assertNotEmpty($block_storage->load($cloned_block_id), 'A new, distinct block was created for the clone.');

    $cloned_usage = $usage->getUsage($cloned_block_id);
    $this->assertEquals($entity2->id(), $cloned_usage->layout_entity_id);
    $this->assertEquals($entity2->getEntityTypeId(), $cloned_usage->layout_entity_type);

    // The original entity's own usage record is untouched by the clone.
    $original_usage = $usage->getUsage($original_block_id);
    $this->assertEquals($entity1->id(), $original_usage->layout_entity_id);
  }

  /**
   * Tests that an already-independent block is not duplicated again.
   *
   * A new parent entity may already reference an independent block via
   * block_serialized with block_revision_id cleared. Saving it must adopt
   * that block instead of duplicating it again.
   */
  public function testNoDuplicateWhenBlockAlreadyIndependent(): void {
    $usage = $this->container->get('inline_block.usage');
    $block_storage = $this->container->get('entity_type.manager')->getStorage('block_content');

    $before_count = (int) $block_storage->getQuery()->accessCheck(FALSE)->count()->execute();

    // A block that already exists independently of any parent entity.
    $independent_block = BlockContent::create([
      'type' => 'basic',
      'reusable' => FALSE,
      'info' => 'Independently supplied block',
    ]);
    $independent_block->save();
    $independent_block_id = $independent_block->id();

    // A new entity whose component already references the independent
    // block, with block_revision_id cleared.
    $entity = EntityTest::create(['name' => 'Test entity']);
    $section = new Section('layout_onecol', [], [
      'the-component' => new SectionComponent('the-component', 'content', [
        'id' => 'inline_block:basic',
        'block_serialized' => serialize($independent_block),
        'block_revision_id' => NULL,
      ]),
    ]);
    $entity->{OverridesSectionStorage::FIELD_NAME} = $section;
    $this->assertTrue($entity->isNew());
    $entity->save();

    $after_count = (int) $block_storage->getQuery()->accessCheck(FALSE)->count()->execute();
    $this->assertSame($before_count + 1, $after_count, 'Saving the new parent entity does not create a second top-level block_content entity.');

    $final_config = $entity->{OverridesSectionStorage::FIELD_NAME}->getSections()[0]
      ->getComponent('the-component')->getPlugin()->getConfiguration();
    $this->assertEquals($independent_block_id, $final_config['block_id'], 'The supplied independent block becomes the final block used by the component.');

    $usage_info = $usage->getUsage($independent_block_id);
    $this->assertEquals($entity->id(), $usage_info->layout_entity_id);
    $this->assertEquals($entity->getEntityTypeId(), $usage_info->layout_entity_type);
  }

}
