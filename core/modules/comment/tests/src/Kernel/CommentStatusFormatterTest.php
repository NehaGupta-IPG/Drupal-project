<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Kernel;

use Drupal\comment\CommentingStatus;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the comment status field formatter.
 */
#[Group('comment')]
#[RunTestsInSeparateProcesses]
class CommentStatusFormatterTest extends KernelTestBase {

  use CommentTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'field',
    'filter',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['filter', 'node']);

    $this->setUpCurrentUser(permissions: ['access comments']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    FieldStorageConfig::create([
      'type' => 'text_long',
      'entity_type' => 'comment',
      'field_name' => 'comment_body',
    ])->save();
    $this->addDefaultCommentField('node', 'page', 'comment');
  }

  /**
   * Tests the rendered output of the formatter for each status.
   */
  #[DataProvider('providerStatus')]
  public function testStatusLabel(int $status, string $expected): void {
    $node = Node::create(['type' => 'page', 'title' => 'Test']);
    $node->get('comment')->status = $status;
    $build = $node->get('comment')->view(['type' => 'comment_status']);
    $this->assertSame($expected, trim((string) $this->container->get('renderer')->renderRoot($build[0])));
  }

  /**
   * Tests an entity with no stored value renders the field default.
   */
  public function testDefaultForEntityWithoutValue(): void {
    $node = Node::create(['type' => 'page', 'title' => 'Test', 'comment' => []]);
    $this->assertTrue($node->get('comment')->isEmpty());
    $build = $node->get('comment')->view(['type' => 'comment_status']);
    $this->assertSame('Open', trim((string) $this->container->get('renderer')->renderRoot($build[0])));
  }

  /**
   * Tests a NULL stored status renders the fallback rather than a label.
   */
  public function testNullStatus(): void {
    $node = Node::create(['type' => 'page', 'title' => 'Test']);
    $node->get('comment')->status = NULL;
    $build = $node->get('comment')->view(['type' => 'comment_status']);
    $this->assertSame('N/A', trim((string) $this->container->get('renderer')->renderRoot($build[0])));
  }

  /**
   * Tests that the formatter is offered for comment fields.
   */
  public function testFormatterAvailable(): void {
    $options = $this->container->get('plugin.manager.field.formatter')->getOptions('comment');
    $this->assertArrayHasKey('comment_status', $options);
    $this->assertSame('Comment status', (string) $options['comment_status']);
  }

  /**
   * Data provider for ::testStatusLabel().
   */
  public static function providerStatus(): array {
    return [
      'open' => [CommentingStatus::Open->value, 'Open'],
      'closed' => [CommentingStatus::Closed->value, 'Closed'],
      'hidden' => [CommentingStatus::Hidden->value, 'Hidden'],
      'unknown' => [99, 'N/A'],
    ];
  }

}
