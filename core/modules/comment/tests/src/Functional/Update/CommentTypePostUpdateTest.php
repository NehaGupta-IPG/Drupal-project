<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the post update path for post_update_hook for comment_type updates.
 *
 * @see comment_post_update_provider_dependency()
 */
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
#[Group('Update')]
class CommentTypePostUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests the comment type dependency updates.
   */
  public function testCommentTypeDependencyUpdate(): void {
    $default_comment_type = 'comment.type.comment';

    // Check that default comment type does not depend on the node module.
    $dependencies = $this->config($default_comment_type)->get('dependencies.module') ?: [];
    $this->assertNotContains('node', $dependencies);

    $this->runUpdates();

    // Check that default comment type depends on the node module.
    $dependencies = $this->config($default_comment_type)->get('dependencies.module') ?: [];
    $this->assertTrue(in_array('node', $dependencies));
  }

}
