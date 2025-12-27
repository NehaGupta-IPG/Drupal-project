<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the addition of the default user.flood `use_username` setting.
 */
#[Group('Update')]
#[CoversFunction('user_post_update_user_flood_default_use_username')]
#[RunTestsInSeparateProcesses]
class UserFloodDefaultUseUsernameUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests updating user.flood configuration.
   */
  public function testConfigUpdate(): void {
    $config = \Drupal::configFactory()->get('user.flood');
    $this->assertNull($config->get('use_username'));

    $this->runUpdates();

    $config = \Drupal::configFactory()->get('user.flood');
    $this->assertFalse($config->get('use_username'));
  }

}
