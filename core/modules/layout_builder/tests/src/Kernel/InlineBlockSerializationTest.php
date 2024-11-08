<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

/**
 * @coversDefaultClass \Drupal\layout_builder\Plugin\Block\InlineBlock
 *
 * @group layout_builder
 */
class InlineBlockSerializationTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'layout_discovery',
    'node',
    'block',
    'block_content',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create necessary bundles.
    $this->installConfig(['block_content']);
    $this->installEntitySchema('block_content');
    $bundle = $this->entityTypeManager->getStorage('block_content_type')->create([
      'id' => 'basic',
      'label' => 'Basic block',
      'revision' => 1,
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());
    $this->entityTypeManager->getStorage('node_type')->create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();
    // Create view display and enable layout builder overrides.
    $this->entityTypeManager->getStorage('entity_view_display')->create([
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'default',
      'status' => TRUE,
    ])->save();
    LayoutBuilderEntityViewDisplay::load('node.article.default')
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();
  }

  /**
   * Tests saving layout with inline block, which has serialized data.
   *
   * @covers ::getUnserializedBlock
   */
  public function testCreationBlockWithSerializedData(): void {
    // Create inline block with serialized data not representing block entity.
    $component = new SectionComponent($this->container->get(UuidInterface::class)->generate(), 'content', [
      "id" => "inline_block:basic",
      "label" => "Test title",
      "label_display" => "visible",
      "provider" => "layout_builder",
      "view_mode" => "full",
      "block_revision_id" => "1",
      "block_serialized" => 'a:1:{s:2:"id";i:1;}',
      "context_mapping" => [],
    ]);
    $section = new Section('layout_onecol', ['label' => 'test'], [$component]);
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'article',
      'title' => 'Test article',
      'layout_builder__layout' => [
        [
          'section' => $section,
        ],
      ],
    ]);
    // Check, that save() function results in error.
    try {
      $node->save();
    }
    catch (\Throwable $t) {
      $this->assertEquals('Call to a member function setNewRevision() on array', $t->getMessage());
    }
    // Create inline block with incorrect serialized data.
    $component = new SectionComponent($this->container->get(UuidInterface::class)->generate(), 'content', [
      "id" => "inline_block:basic",
      "label" => "Test title",
      "label_display" => "visible",
      "provider" => "layout_builder",
      "view_mode" => "full",
      "block_revision_id" => "1",
      "block_serialized" => 'O:40:"Drupal\block_content\Entity\BlockContent":31:{',
      "context_mapping" => [],
    ]);
    $section = new Section('layout_onecol', ['label' => 'test'], [$component]);
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'article',
      'title' => 'Test article',
      'layout_builder__layout' => [
        [
          'section' => $section,
        ],
      ],
    ]);
    // Check, that unserialize() function results in error.
    try {
      $node->save();
    }
    catch (EntityStorageException $e) {
      $this->fail($e->getMessage());
    }
  }

}
