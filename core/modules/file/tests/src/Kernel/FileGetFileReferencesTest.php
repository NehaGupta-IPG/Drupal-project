<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests file_get_file_references() behaviors.
 *
 * @group file
 */
class FileGetFileReferencesTest extends FileManagedUnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
  }

  /**
   * Tests filtering by field.
   */
  public function testFileReferenceFilterByField(): void {
    NodeType::create(['type' => 'page', 'name' => 'page'])->save();
    $field_storage = FieldStorageConfig::create([
      'type' => 'file',
      'entity_type' => 'node',
      'field_name' => 'field_file',
    ]);
    $field_storage->save();
    $field_config = FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'page',
      'field_name' => 'field_file',
      'label' => 'File',
    ]);
    $field_config->save();

    $node = Node::create(['type' => 'page', 'title' => 'Page']);
    $node
      ->set('field_file', $file = $this->createFile())
      ->save();

    $references = file_get_file_references($file);
    $this->assertArrayHasKey($node->id(), $references['field_file']['node']);

    $references = file_get_file_references($file, $field_config);
    $this->assertArrayHasKey($node->id(), $references['field_file']['node'] ?? []);
  }

}
