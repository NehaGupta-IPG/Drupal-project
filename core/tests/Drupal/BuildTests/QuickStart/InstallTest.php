<?php

namespace Drupal\BuildTests\QuickStart;

use Drupal\sqlite\Driver\Database\sqlite\Install\Tasks;

/**
 * Test whether we can install a Drupal site using the quickstart CLI.
 *
 * @group Build
 * @group Command
 * @group QuickStart
 */
class InstallTest extends QuickStartTestBase {

  /**
   * Provides data to testInstall.
   */
  public function providerProfile() {
    return [
      'standard' => ['standard'],
      'minimal' => ['minimal'],
      'demo_umami' => ['demo_umami'],
    ];
  }

  /**
   * @dataProvider providerProfile
   */
  public function testInstall($profile) {
    $sqlite = (new \PDO('sqlite::memory:'))->query('select sqlite_version()')->fetch()[0];
    if (version_compare($sqlite, Tasks::SQLITE_MINIMUM_VERSION) < 0) {
      $this->markTestSkipped();
    }

    // Get the codebase.
    $this->copyCodebase();

    // Composer tells you stuff in error output.
    $this->executeCommand('COMPOSER_DISCARD_CHANGES=true composer install --no-dev --no-interaction');
    $this->assertErrorOutputContains('Generating autoload files');
    $this->installQuickStart($profile);

    // Visit paths with expectations.
    $this->visit();
    $this->assertDrupalVisit();

    $this->visit('/does-not-exist');
    $assert = $this->getMink()->assertSession();
    $assert->statusCodeEquals(404);

    $this->visit('/admin');
    $assert->statusCodeEquals(403);
    $this->formLogin($this->adminUsername, $this->adminPassword);
    $this->visit('/admin');
    $assert->statusCodeEquals(200);

    $this->visit('/user/logout');
    $assert->statusCodeEquals(200);
    $this->visit('/admin');
    $assert->statusCodeEquals(403);
  }

}
