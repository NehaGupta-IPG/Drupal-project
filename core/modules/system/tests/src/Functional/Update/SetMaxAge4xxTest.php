<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the cache.page.4xx_max_age key is added to system.performance.
 *
 * @see system_post_update_set_max_age_4xx()
 */
#[Group('update')]
#[RunTestsInSeparateProcesses]
class SetMaxAge4xxTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-10.3.0.bare.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/uninstall-history.php',
      __DIR__ . '/../../../fixtures/update/uninstall-contact.php',
    ];
  }

  /**
   * Ensures that the cache.page.4xx_max_age is added.
   */
  public function testUpdate(): void {
    $config = $this->config('system.performance')->getRawData();
    $this->assertArrayNotHasKey('4xx_max_age', $config['cache']['page']);

    $this->runUpdates();

    $config = $this->config('system.performance')->getRawData();
    $this->assertArrayHasKey('4xx_max_age', $config['cache']['page']);
  }

}
