<?php

namespace Drupal\BuildTests\Update;

use Drupal\BuildTests\QuickStart\QuickStartTestBase;
use Drupal\sqlite\Driver\Database\sqlite\Install\Tasks;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Test updates to core using update.php.
 *
 * @requires externalCommand git
 *
 * @group Build
 * @group Update
 */
class UpdateTest extends QuickStartTestBase {

  /**
   * Data provider testUpdateGit.
   */
  public function provideVersions() {
    return [
      ['10.0.1'],
      ['10.0.x'],
      ['10.1.x'],
    ];
  }

  /**
   * Test updates from the provided version to HEAD.
   *
   * @dataProvider provideVersions
   */
  public function testUpdateGit($version_branch) {
    $sqlite = (new \PDO('sqlite::memory:'))->query('select sqlite_version()')->fetch()[0];
    if (version_compare($sqlite, Tasks::SQLITE_MINIMUM_VERSION) < 0) {
      $this->markTestSkipped();
    }
    $this->copyCodebase();
    // Save everything, including any applied patches.
    $this->executeCommand('git config user.email "drupalci@drupalci.org"');
    $this->assertCommandSuccessful();
    $this->executeCommand('git config user.name "The Testbot"');
    $this->assertCommandSuccessful();
    $this->executeCommand('git stash');
    $this->assertCommandSuccessful();
    $this->executeCommand("git config checkout.defaultRemote origin");
    $this->assertCommandSuccessful();
    $this->executeCommand("git fetch origin $version_branch");
    $this->assertCommandSuccessful();
    $this->executeCommand("git checkout $version_branch");
    $this->assertCommandSuccessful();
    $fs = new Filesystem();
    $fs->chmod($this->getWorkspaceDirectory() . '/sites/default', 0700, 0000);

    // Install Drupal using quick start.
    $this->executeCommand('COMPOSER_DISCARD_CHANGES=true composer install --no-dev --no-interaction');
    $this->assertErrorOutputContains('Generating autoload files');
    $this->installQuickStart('minimal');

    // Log in so that we can update.
    $this->formLogin($this->adminUsername, $this->adminPassword);
    $this->assertDrupalVisit();

    // Return the codebase to HEAD and re-apply patches.
    $fs = new Filesystem();
    $fs->chmod($this->getWorkspaceDirectory() . '/sites/default', 0700, 0000);
    $fs->chmod($this->getWorkspaceDirectory() . '/sites/default/settings.php', 0600, 0000);
    $this->executeCommand('git checkout - -f');
    $this->assertCommandSuccessful();
    $this->executeCommand('git reset HEAD --hard');
    $this->assertCommandSuccessful();
    // Re-apply any patches.
    $process = $this->executeCommand('git stash pop');
    if ($process->getExitCode() != 0) {
      $this->assertErrorOutputContains('No stash entries found');
    }
    // Composer.
    $this->executeCommand('COMPOSER_DISCARD_CHANGES=true composer install --no-dev --no-interaction');
    $this->assertErrorOutputContains('Generating autoload files');

    // Currently, this test has to use update_free_access because the PHP HTTP
    // server caches old class information. We'll need to restart the server
    // process as well.
    file_put_contents($this->getWorkspaceDirectory() . '/sites/default/settings.php', "\$settings['update_free_access'] = TRUE;", FILE_APPEND);
    $this->stopServer();
    $this->standUpServer();

    // Perform the update steps.
    $this->visit('/update.php');
    // Since we restarted the server, we're in a different session, but only
    // after we've made a request.
    $session = $this->getMink()->getSession();
    $assert = $this->getMink()->assertSession();
    $assert->pageTextContains('Drupal database update');
    $session->getPage()->clickLink('Continue');

    // Allow for 'no pending updates' result.
    if ($session->getPage()->hasLink('Apply pending updates')) {
      $session->getPage()->clickLink('Apply pending updates');
      $assert->pageTextContains('Updating');
    }

    // Request the front page again and make sure it works.
    $this->visit();
    $this->assertDrupalVisit();
  }

}
