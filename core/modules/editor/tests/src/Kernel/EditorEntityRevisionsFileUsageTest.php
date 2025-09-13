<?php

declare(strict_types=1);

namespace Drupal\Tests\editor\Kernel {

  use Drupal\editor\Entity\Editor;
  use Drupal\editor\EntityReferenceHelper;
  use Drupal\file\FileInterface;
  use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
  use Drupal\node\Entity\Node;
  use Drupal\node\Entity\NodeType;
  use Drupal\file\Entity\File;
  use Drupal\field\Entity\FieldConfig;
  use Drupal\field\Entity\FieldStorageConfig;
  use Drupal\Core\Field\FieldStorageDefinitionInterface;
  use Drupal\filter\Entity\FilterFormat;
  use Drupal\node\NodeInterface;

  /**
   * Tests tracking of file usage by the Text Editor module.
   *
   * @group editor
   */
  class EditorEntityRevisionsFileUsageTest extends EntityKernelTestBase {

    /**
     * {@inheritdoc}
     */
    protected static $modules = ['editor', 'editor_test', 'node', 'file', 'language'];

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void {
      parent::setUp();

      // Mock the editor entity reference helper service.
      // This MUST be done just after the parent::setUp() call, so the
      // autowire process is not made yet for the EditorHooks class.
      $entity_reference_helper = $this->createMock(EntityReferenceHelper::class);
      $entity_reference_helper->method('getEntityReferenceRevisions')->willReturnCallback(function ($entity) {
        // Only return entity reference revision entities for the test entity, and not for others.
        $node_test_entity = $this->getTestNodeEntity();
        $is_the_test_entity = ($entity->getEntityTypeId() === $node_test_entity->getEntityTypeId() && $entity->id() === $node_test_entity->id());
        if (!$is_the_test_entity) {
          return [];
        }
        return [$this->getTestReferencedNodeEntity()];
      });
      $this->container->set('Drupal\editor\EntityReferenceHelper', $entity_reference_helper);

      $this->installEntitySchema('file');
      $this->installSchema('node', ['node_access']);
      $this->installSchema('file', ['file_usage']);
      $this->installConfig(['node']);

      // Add text formats.
      $filtered_html_format = FilterFormat::create([
        'format' => 'filtered_html',
        'name' => 'Filtered HTML',
        'weight' => 0,
        'filters' => [],
      ]);
      $filtered_html_format->save();

      // Set cardinality for body field.
      FieldStorageConfig::loadByName('node', 'body')
        ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
        ->save();

      // Set up text editor.
      $editor = Editor::create([
        'format' => 'filtered_html',
        'editor' => 'unicorn',
        'image_upload' => [
          'status' => FALSE,
        ],
      ]);
      $editor->save();

      // Create a node type for testing.
      $type = NodeType::create(['type' => 'page', 'name' => 'page']);
      $type->save();
      node_add_body_field($type);
      FieldStorageConfig::create([
        'field_name' => 'description',
        'entity_type' => 'node',
        'type' => 'editor_test_text_long',
        'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      ])->save();
      FieldConfig::create([
        'field_name' => 'description',
        'entity_type' => 'node',
        'bundle' => 'page',
        'label' => 'Description',
      ])->save();

    }

    /**
     * Tests the usages are updated when an entity reference revision has the usages.
     */
    public function testUsagesInEntityReferenceRevisionsOnInsertAndDelete(): void {

      /** @var \Drupal\file\FileUsage\FileUsageInterface $file_usage */
      $file_usage = $this->container->get('file.usage');

      $image_entity = self::getTestImageEntity();
      $test_node_entity = self::getTestNodeEntity();

      // Test editor_entity_insert(): increment with usages in entity reference revisions.
      // Simply retrieve the referenced node in order to create it and register one usage.
      self::getTestReferencedNodeEntity();
      $this->assertSame(['editor' => ['node' => [2 => '1']]], $file_usage->listUsage($image_entity), 'The image ' . $image_entity->getFileUri() . ' has 1 usage.');

      // Test editor_entity_delete(): decrement after deleting parent entity with usages in its entity reference revisions.
      // The method _editor_get_entity_reference_revisions() has been mocked at the bottom of this file,
      // so the test node entity will always pretend to have an entity reference revisions referenced entity.
      $test_node_entity->delete();
      $this->assertSame([], $file_usage->listUsage($image_entity), 'The image ' . $image_entity->getFileUri() . ' has zero usages again.');
    }

    /**
     * Tests the usages are updated when an entity reference revision has the usages.
     */
    public function testUsagesInEntityReferenceRevisionsOnUpdate(): void {

      /** @var \Drupal\file\FileUsage\FileUsageInterface $file_usage */
      $file_usage = $this->container->get('file.usage');

      $image_entity = self::getTestImageEntity();

      // Simply retrieve the test node entity in order to create it and reserve the nid 1.
      self::getTestNodeEntity();

      // Test editor_entity_insert(): increment with usages in entity reference revisions.
      // Retrieve the referenced node entity in order to create it and register one usage.
      $test_referenced_node_entity = self::getTestReferencedNodeEntity();
      $this->assertSame(['editor' => ['node' => [2 => '1']]], $file_usage->listUsage($image_entity), 'The image ' . $image_entity->getFileUri() . ' has 1 usage.');

      // Test editor_entity_update(): decrement after removing usages in its entity reference revisions.
      $test_referenced_node_entity->set('body', '')->save();
      $this->assertSame([], $file_usage->listUsage($image_entity), 'The image ' . $image_entity->getFileUri() . ' has zero usages again.');
    }

    /**
     * Creates and returns a singleton instance of a test node.
     *
     * @return \Drupal\node\NodeInterface
     *   The test node entity.
     */
    public static function getTestNodeEntity(): NodeInterface {
      static $node_entity = NULL;

      if (NULL === $node_entity) {
        $node_entity = Node::create([
          'type' => 'page',
          'title' => 'test',
          'body' => '',
          'uid' => 1,
        ]);
        $node_entity->save();
      }

      return $node_entity;
    }

    /**
     * Creates and returns a singleton instance of a referenced node.
     *
     * @return \Drupal\node\NodeInterface
     *   The referenced test node entity.
     */
    public static function getTestReferencedNodeEntity(): NodeInterface {
      static $referenced_node_entity = NULL;

      if (NULL === $referenced_node_entity) {
        $image_entity = EditorEntityRevisionsFileUsageTest::getTestImageEntity();

        $body_value = '<p>Hello, world!</p>';
        $body_value .= '<img src="awesome-llama-0.jpg" data-entity-type="file" data-entity-uuid="' . $image_entity->uuid() . '" />';

        $body = [
          'value' => $body_value,
          'format' => 'filtered_html',
        ];

        $referenced_node_entity = Node::create([
          'type' => 'page',
          'title' => 'entity reference revisions entity',
          'body' => $body,
          'uid' => 1,
        ]);
        $referenced_node_entity->save();
      }

      return $referenced_node_entity;
    }

    /**
     * Creates and returns a singleton instance of a test image entity.
     *
     * @return \Drupal\file\FileInterface
     *   The test image entity.
     */
    public static function getTestImageEntity(): FileInterface {
      static $image_entity = NULL;

      if (NULL === $image_entity) {
        $image_path = 'core/misc/druplicon.png';

        $image_entity = File::create();
        $image_entity->setFileUri($image_path);
        $image_entity->setFilename(\Drupal::service('file_system')
          ->basename($image_entity->getFileUri()));
        $image_entity->save();
      }

      return $image_entity;
    }

  }

}
