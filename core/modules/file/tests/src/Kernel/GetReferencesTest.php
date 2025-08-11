<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests getting references to managed files.
 *
 * @group file
 */
class GetReferencesTest extends FileManagedUnitTestBase {

  /**
   * Tests get references.
   */
  public function testGetReferences(): void {
    $this->enableModules(['node']);
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);

    // Create a node type and file field.
    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();
    FieldStorageConfig::create([
      'type' => 'file',
      'entity_type' => 'node',
      'field_name' => 'field_file',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'page',
      'field_name' => 'field_file',
      'label' => 'File',
    ])->save();

    $file = $this->createFile();

    // File has no references initially.
    $this->assertEmpty(file_get_file_references($file));

    // Create a node using the file.
    $node = Node::create(['type' => 'page', 'title' => 'Page']);
    $node
      ->set('field_file', $file)
      ->save();

    // References should include the node.
    $references = file_get_file_references($file);
    $this->assertArrayHasKey($node->id(), $references['field_file']['node']);

    // Add another reference.
    $node2 = Node::create(['type' => 'page', 'title' => 'Page']);
    $node2
      ->set('field_file', $file)
      ->save();

    // References should include both nodes.
    $references = file_get_file_references($file);
    $this->assertArrayHasKey($node->id(), $references['field_file']['node']);
    $this->assertArrayHasKey($node2->id(), $references['field_file']['node']);

    // Delete the nodes.
    $node->delete();
    $node2->delete();

    // File no longer has any references.
    $references = file_get_file_references($file);
    $this->assertEmpty($references);
  }

}
