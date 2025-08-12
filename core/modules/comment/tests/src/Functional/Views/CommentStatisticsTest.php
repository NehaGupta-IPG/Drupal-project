<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Functional\Views;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMulChanged;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\user\Entity\Role;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests the comment statistics handlers.
 *
 * @group comment
 */
class CommentStatisticsTest extends ViewTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['comment', 'comment_test_views', 'entity_test'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_ces_entity_test_mul_changed', 'test_ces_entity_test'];

  /**
   * Tests view for entity with "changed" field.
   */
  public function testEntityMulChanged():void {
    ViewTestData::createTestViews(get_class($this), ['comment_test_views']);

    Role::load('anonymous')
      ->grantPermission('view test entity')
      ->grantPermission('access comments')
      ->save();

    $this->addDefaultCommentField('entity_test_mul_changed', 'entity_test_mul_changed');
    $entity = EntityTestMulChanged::create([
      'name' => $this->randomString(),
    ]);
    $entity->save();

    /** @var \Drupal\comment\Entity\Comment $comment */
    $comment = Comment::create([
      'subject' => $this->randomString(),
      'comment_type' => 'comment',
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'field_name' => 'comment',
      'changed' => \Drupal::time()->getRequestTime() + 3600,
    ]);
    $comment->setPublished()->save();

    $entity = EntityTestMulChanged::create([
      'name' => $this->randomString(),
      'changed' => \Drupal::time()->getRequestTime() + 7200,
    ]);
    $entity->save();

    $result = views_get_view_result('test_ces_entity_test_mul_changed');
    $this->assertEquals($entity->getChangedTime(), $result[0]->comment_entity_statistics_last_updated);
    $this->assertEquals($comment->getChangedTime(), $result[1]->comment_entity_statistics_last_updated);
  }

  /**
   * Tests view for entity without "changed" field.
   *
   * Tests that the comment statistics handlers work correctly for entities
   * that don't implement EntityChangedInterface or have a 'changed' field.
   * In this case, the handlers should only use the last comment timestamp.
   */
  public function testEntityWithoutChangedField(): void {
    ViewTestData::createTestViews(get_class($this), ['comment_test_views']);

    Role::load('anonymous')
      ->grantPermission('view test entity')
      ->grantPermission('access comments')
      ->save();

    $this->addDefaultCommentField('entity_test', 'entity_test');

    // Create first entity.
    $entity1 = EntityTest::create([
      'name' => $this->randomString(),
    ]);
    $entity1->save();

    // Create comment with specific timestamp (older).
    $comment1 = Comment::create([
      'subject' => $this->randomString(),
      'comment_type' => 'comment',
      'entity_type' => $entity1->getEntityTypeId(),
      'entity_id' => $entity1->id(),
      'field_name' => 'comment',
      'changed' => \Drupal::time()->getRequestTime() + 1800,
    ]);
    $comment1->setPublished()->save();

    // Create second entity.
    $entity2 = EntityTest::create([
      'name' => $this->randomString(),
    ]);
    $entity2->save();

    // Create comment with different timestamp (newer).
    $comment2 = Comment::create([
      'subject' => $this->randomString(),
      'comment_type' => 'comment',
      'entity_type' => $entity2->getEntityTypeId(),
      'entity_id' => $entity2->id(),
      'field_name' => 'comment',
      'changed' => \Drupal::time()->getRequestTime() + 3600,
    ]);
    $comment2->setPublished()->save();

    // Manually update comment statistics to ensure they exist.
    /** @var \Drupal\comment\CommentStatisticsInterface $comment_statistics */
    $comment_statistics = \Drupal::service('comment.statistics');
    $comment_statistics->update($comment1);
    $comment_statistics->update($comment2);

    $result = views_get_view_result('test_ces_entity_test');

    // For entities without EntityChangedInterface or 'changed' field,
    // the field should return only the last comment timestamp.
    // Results should be sorted by comment timestamp (DESC order).
    $this->assertEquals($comment2->getChangedTime(), $result[0]->comment_entity_statistics_last_updated);
    $this->assertEquals($comment1->getChangedTime(), $result[1]->comment_entity_statistics_last_updated);
  }

}
