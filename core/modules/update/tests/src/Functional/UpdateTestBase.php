<?php

declare(strict_types=1);

namespace Drupal\Tests\update\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\update\Traits\UpdateTestAssertionsTrait;

/**
 * Defines some shared functions used by all update tests.
 *
 * The overarching methodology of these tests is we need to compare a given
 * state of installed modules and themes (e.g., version, project grouping,
 * timestamps, etc) against a current state of what the release history XML
 * files we fetch say is available. We have dummy XML files (in the
 * core/modules/update/tests directory) that describe various scenarios of
 * what's available for different test projects, and we have dummy .info file
 * data (specified via hook_system_info_alter() in the update_test helper
 * module) describing what's currently installed. Each test case defines a set
 * of projects to install, their current state (via the
 * 'update_test_system_info' variable) and the desired available update data
 * (via the 'update_test_xml_map' variable), and then performs a series of
 * assertions that the report matches our expectations given the specific
 * initial state and availability scenario.
 */
abstract class UpdateTestBase extends BrowserTestBase {
  use UpdateTestTrait;
  use UpdateTestAssertionsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['update', 'update_test'];

  /**
   * Denotes a security update will be required in the test case.
   */
  const SECURITY_UPDATE_REQUIRED = 'SECURITY_UPDATE_REQUIRED';

  /**
   * Denotes an update will be available in the test case.
   */
  const UPDATE_AVAILABLE = 'UPDATE_AVAILABLE';

  /**
   * Denotes no update will be available in the test case.
   */
  const UPDATE_NONE = 'UPDATE_NONE';

  /**
   * The CSS locator for the update table run asserts on.
   *
   * @var string
   */
  protected $updateTableLocator;

  /**
   * The project that is being tested.
   *
   * @var string
   */
  protected $updateProject;

  /**
   * Refreshes the update status based on the desired available update scenario.
   *
   * @param array $xml_map
   *   Array that maps project names to availability scenarios to fetch. The key
   *   '#all' is used if a project-specific mapping is not defined.
   * @param string $url
   *   (optional) A string containing the URL to fetch update data from.
   *   Defaults to 'update-test'.
   *
   * @see \Drupal\update_test\Controller\UpdateTestController::updateTest()
   */
  protected function refreshUpdateStatus(array $xml_map, string $url = 'update-test') {
    // Tell the Update Status module to fetch from the URL provided by
    // update_test module.
    $this->config('update.settings')->set('fetch.url', Url::fromUri('base:' . $url, ['absolute' => TRUE])->toString())->save();
    // Save the map for UpdateTestController::updateTest() to use.
    $this->mockReleaseHistory($xml_map);
    // Manually check the update status.
    $this->drupalGet('admin/reports/updates');
    $this->clickLink('Check manually');
    $this->checkForMetaRefresh();
  }

  /**
   * Runs a series of assertions that are applicable to all update statuses.
   */
  protected function standardTests() {
    $this->assertSession()->responseContains('<h3>Drupal core</h3>');
    // Verify that the link to the Drupal project appears.
    $this->assertSession()->linkExists('Drupal');
    $this->assertSession()->linkByHrefExists('https://example.com/project/drupal');
    $this->assertSession()->pageTextNotContains('No available releases found');
    $this->assertSession()->pageTextContains('Last checked:');
    // No download URLs should be present.
    $this->assertSession()->responseNotContains('.tar.gz');
  }

  /**
   * Confirms messages are correct when a release has been unpublished/revoked.
   *
   * @param string $revoked_version
   *   The revoked version that is currently installed.
   * @param string $newer_version
   *   The expected newer version to recommend.
   * @param string $new_version_label
   *   The expected label for the newer version (for example 'Recommended
   *   version:' or 'Also available:').
   */
  protected function confirmRevokedStatus($revoked_version, $newer_version, $new_version_label) {
    $this->drupalGet('admin/reports/updates');
    $this->clickLink('Check manually');
    $this->checkForMetaRefresh();
    $this->assertUpdateTableTextContains('Revoked!');
    $this->assertUpdateTableTextContains($revoked_version);
    $this->assertUpdateTableElementContains('error.svg');
    $this->assertUpdateTableTextContains('Release revoked: Your currently installed release has been revoked, and is no longer available for download. Uninstalling everything included in this release or upgrading is strongly recommended!');
    $this->assertVersionUpdateLinks($new_version_label, $newer_version);
  }

  /**
   * Confirms messages are correct when a release has been marked unsupported.
   *
   * @param string $unsupported_version
   *   The unsupported version that is currently installed.
   * @param string|null $newer_version
   *   (optional) The expected newer version to recommend.
   * @param string|null $new_version_label
   *   (optional) The expected label for the newer version. For example
   *   'Recommended version:' or 'Also available:'.
   */
  protected function confirmUnsupportedStatus(string $unsupported_version, ?string $newer_version = NULL, ?string $new_version_label = NULL) {
    $this->drupalGet('admin/reports/updates');
    $this->clickLink('Check manually');
    $this->checkForMetaRefresh();
    $this->assertUpdateTableTextContains('Not supported!');
    $this->assertUpdateTableTextContains($unsupported_version);
    $this->assertUpdateTableElementContains('error.svg');
    if ($newer_version === NULL) {
      $this->assertUpdateTableTextContains('Release not supported: Your currently installed release is now unsupported, is no longer available for download and no update is available. Uninstalling everything included in this release is strongly recommended!');
      $this->assertUpdateTableTextNotContains('Recommended version');
    }
    else {
      $this->assertNotEmpty($newer_version);
      $this->assertUpdateTableTextContains('Release not supported: Your currently installed release is now unsupported, and is no longer available for download. Uninstalling everything included in this release or upgrading is strongly recommended!');
      $this->assertVersionUpdateLinks($new_version_label, $newer_version);
    }
  }

  /**
   * Asserts that the update table element HTML contains the specified text.
   *
   * @param string $text
   *   The expected text.
   *
   * @see \Behat\Mink\WebAssert::elementNotContains()
   */
  protected function assertUpdateTableElementNotContains(string $text) {
    $this->assertSession()
      ->elementNotContains('css', $this->updateTableLocator, $text);
  }

}
