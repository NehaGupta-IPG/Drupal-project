<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Comment module update paths.
 */
#[Group('comment')]
#[RunTestsInSeparateProcesses]
class CommentUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Verifies that thread reply depth default value is added to comment fields.
   *
   * @see comment_post_update_thread_reply_depth()
   */
  public function testCommentPostUpdateThreadReplyDepth(): void {
    $config_factory = \Drupal::configFactory();

    $config = $config_factory->get('field.field.node.article.comment');
    $this->assertArrayNotHasKey('thread_reply_depth', $config->get('settings'));

    $this->runUpdates();

    $config = $config_factory->get('field.field.node.article.comment');
    $this->assertSame(-1, $config->get('settings.thread_reply_depth'));
  }

}
