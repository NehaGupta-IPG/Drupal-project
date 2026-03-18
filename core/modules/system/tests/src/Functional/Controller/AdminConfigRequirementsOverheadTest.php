<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Controller;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that /admin/config does not invoke checkRequirements() on every load.
 *
 * @see https://www.drupal.org/project/drupal/issues/3336621
 * @see \Drupal\system\Controller\SystemController::overview()
 */
#[Group('system')]
#[RunTestsInSeparateProcesses]
class AdminConfigRequirementsOverheadTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'requirements_overview_test',
    'requirements_overview_error_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer site configuration',
    ]));
  }

  /**
   * Tests /admin/config uses cached requirements instead of live checks.
   */
  public function testAdminConfigDoesNotRunCheckRequirements(): void {
    $this->drupalGet('admin/config');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('admin/config');
    $this->assertSession()->statusCodeEquals(200);

    // The counter tracks hook_runtime_requirements() calls. It must be 0.
    $this->assertSame(0, \Drupal::state()->get('requirements_overview_test.count', 0));

    // The status report caches the error from requirements_overview_error_test.
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->statusCodeEquals(200);
    \Drupal::state()->set('requirements_overview_test.count', 0);

    // The warning must appear from cached state, not a live check.
    $this->drupalGet('admin/config');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('One or more problems were detected with your Drupal installation.');
    $this->assertSame(0, \Drupal::state()->get('requirements_overview_test.count', 0));
  }

}
