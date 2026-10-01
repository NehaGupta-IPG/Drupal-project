<?php

declare(strict_types=1);

namespace Drupal\Tests\newsroom\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\newsroom\Hook\NewsroomHooks;
use Drupal\newsroom\ReviewPolicy;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the review notice is displayed to editors only.
 */
#[CoversClass(NewsroomHooks::class)]
#[Group('newsroom')]
#[RunTestsInSeparateProcesses]
class NewsroomHooksTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field', 'filter', 'newsroom', 'node', 'system', 'text', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['filter', 'node', 'system', 'user']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $fields = [
      'field_publication_date' => 'timestamp',
      'field_last_review_date' => 'timestamp',
      'field_evergreen' => 'boolean',
    ];
    foreach ($fields as $field_name => $type) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => $type,
      ]);
      $field_storage->save();
      FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => 'article',
      ])->save();
    }

    // Make sure user 1 is not used, as it bypasses access checks.
    $this->createUser();
  }

  /**
   * Tests that only users allowed to update an article see the notice.
   */
  public function testReviewNoticeAccess(): void {
    $article = Node::create([
      'type' => 'article',
      'title' => 'Headline',
      'field_publication_date' => $this->container->get('datetime.time')->getRequestTime() - 2 * ReviewPolicy::REVIEW_INTERVAL,
    ]);
    $article->save();

    $this->setCurrentUser($this->createUser(['access content', 'edit any article content']));
    $this->assertReviewNotice($article, TRUE);

    $this->setCurrentUser($this->createUser(['access content']));
    $this->assertReviewNotice($article, FALSE);
  }

  /**
   * Asserts whether the review notice is displayed for the specified article.
   *
   * @param \Drupal\node\Entity\Node $article
   *   The article node.
   * @param bool $expected
   *   Whether the notice is expected to be displayed.
   */
  protected function assertReviewNotice(Node $article, bool $expected): void {
    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('node')
      ->view($article);
    $this->render($build);
    $expected ? $this->assertText('This article is due for review.') : $this->assertNoText('This article is due for review.');
  }

}
