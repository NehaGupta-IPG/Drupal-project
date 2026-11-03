<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the upgrade path for adding file extensions to fast 404 paths.
 *
 * @see system_post_update_fast_404_paths()
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class Fast404PathsUpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests the upgrade path for the fast 404 paths.
   */
  public function testRunUpdates(): void {
    $this->assertSame('/\.(?:txt|png|gif|jpe?g|css|js|ico|swf|flv|cgi|bat|pl|dll|exe|asp)$/i', $this->config('system.performance')->get('fast_404.paths'));

    $this->runUpdates();

    $this->assertSame('/\.(?:txt|png|gif|jpe?g|css|js|ico|swf|flv|cgi|bat|pl|dll|exe|asp|php|json|xml|zip|sql|gz|tar|bak)$/i', $this->config('system.performance')->get('fast_404.paths'));
  }

}
