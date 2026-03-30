<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel;

use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests batch loading of file usage data.
 */
#[Group('file')]
#[RunTestsInSeparateProcesses]
class FileUsageBatchLoadingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['file', 'user', 'node', 'system', 'field'];

  /**
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['file']);

    $this->fileUsage = $this->container->get('file.usage');

    // Create a node type for testing.
    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();
  }

  /**
   * Tests that listUsageMultiple returns correct data for multiple files.
   */
  public function testListUsageMultiple(): void {
    // Create test files.
    $files = [];
    for ($i = 0; $i < 5; $i++) {
      $file = File::create([
        'filename' => "test$i.txt",
        'uri' => "public://test$i.txt",
        'status' => 1,
      ]);
      $file->save();
      $files[$i] = $file;
    }

    // Create nodes to reference files.
    $nodes = [];
    for ($i = 0; $i < 3; $i++) {
      $node = Node::create([
        'type' => 'page',
        'title' => "Node $i",
      ]);
      $node->save();
      $nodes[$i] = $node;
    }

    // Add file usage records.
    // File 0: used by node 0 (count 1).
    $this->fileUsage->add($files[0], 'file', 'node', $nodes[0]->id(), 1);

    // File 1: used by node 0 (count 2) and node 1 (count 1).
    $this->fileUsage->add($files[1], 'file', 'node', $nodes[0]->id(), 2);
    $this->fileUsage->add($files[1], 'file', 'node', $nodes[1]->id(), 1);

    // File 2: used by all three nodes.
    $this->fileUsage->add($files[2], 'file', 'node', $nodes[0]->id(), 1);
    $this->fileUsage->add($files[2], 'file', 'node', $nodes[1]->id(), 1);
    $this->fileUsage->add($files[2], 'file', 'node', $nodes[2]->id(), 1);

    // File 3: no usage.
    // File 4: used by node 2 with different module.
    $this->fileUsage->add($files[4], 'editor', 'node', $nodes[2]->id(), 5);

    // Test listUsageMultiple with all file IDs.
    $file_ids = array_map(fn($file) => $file->id(), $files);
    $usage = $this->fileUsage->listUsageMultiple($file_ids);

    // File 0 should have 1 usage.
    $this->assertArrayHasKey($files[0]->id(), $usage);
    $this->assertEquals(1, $usage[$files[0]->id()]['file']['node'][$nodes[0]->id()]);

    // File 1 should have 2 usages (2 + 1 = 3 total count).
    $this->assertArrayHasKey($files[1]->id(), $usage);
    $this->assertEquals(2, $usage[$files[1]->id()]['file']['node'][$nodes[0]->id()]);
    $this->assertEquals(1, $usage[$files[1]->id()]['file']['node'][$nodes[1]->id()]);

    // File 2 should have 3 usages.
    $this->assertArrayHasKey($files[2]->id(), $usage);
    $this->assertCount(3, $usage[$files[2]->id()]['file']['node']);

    // File 3 should not be in the results (no usage).
    $this->assertArrayNotHasKey($files[3]->id(), $usage);

    // File 4 should have 1 usage under 'editor' module.
    $this->assertArrayHasKey($files[4]->id(), $usage);
    $this->assertEquals(5, $usage[$files[4]->id()]['editor']['node'][$nodes[2]->id()]);
  }

}
