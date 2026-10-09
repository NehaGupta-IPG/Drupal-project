<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\System;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the status report warning on the Configuration page.
 */
#[Group('system')]
#[RunTestsInSeparateProcesses]
class StatusReportWarningTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['module_runtime_requirements'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the status report warning can be turned off in settings.php.
   */
  public function testStatusReportWarningSetting(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer site configuration',
    ]));
    $warning = 'One or more problems were detected with your Drupal installation.';

    $this->drupalGet('admin/config');
    $this->assertSession()->pageTextContains($warning);

    $this->writeSettings([
      'settings' => [
        'status_report_warning' => (object) [
          'value' => FALSE,
          'required' => TRUE,
        ],
      ],
    ]);
    $this->drupalGet('admin/config');
    $this->assertSession()->pageTextNotContains($warning);

    // The status report still lists the errors.
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->pageTextContains('Runtime Error.');
  }

}
