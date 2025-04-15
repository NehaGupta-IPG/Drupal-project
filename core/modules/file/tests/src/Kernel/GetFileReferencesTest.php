<?php

namespace Drupal\Tests\file\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests file usage functions.
 *
 * @group file
 */
class GetFileReferencesTest extends FileManagedUnitTestBase {

  /**
   * Tests file usage with translated entities.
   */
  public function testFileReferenceFilterByField() {
    $this->enableModules(['node']);
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'page'])->save();
    // Create a file field attached to 'page' node-type.
    $field_stroage = FieldStorageConfig::create([
      'type' => 'file',
      'entity_type' => 'node',
      'field_name' => 'field_file',
    ]);
    $field_stroage->save();
    $field_config = FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'page',
      'field_name' => 'field_file',
      'label' => 'File',
    ]);
    $field_config->save();

    // Create a node, attach a file.
    $node = Node::create(['type' => 'page', 'title' => 'Page']);
    $node
      ->set('field_file', $file = $this->createFile())
      ->save();


    $references = file_get_file_references($file);
    $this->assertArrayHasKey($node->id(), $references['field_file']['node']);

    $references = file_get_file_references($file, $field_config);
    $this->assertArrayHasKey($node->id(), $references['field_file']['node']);

  }

}
