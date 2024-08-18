<?php

declare(strict_types=1);

namespace Drupal\Tests\olivero\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the update path for Olivero.
 *
 * @group Update
 * @group #slow
 */
class OliveroPostUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../../modules/system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests update hook setting comment form position.
   */
  public function testOliveroCommentFormPositionUpdate(): void {
    $config = $this->config('olivero.settings');
    $this->assertEmpty($config->get('comment_form_position'));

    // Run updates.
    $this->runUpdates();

    $config = $this->config('olivero.settings');
    $this->assertSame('before', $config->get('comment_form_position'));
  }

}
