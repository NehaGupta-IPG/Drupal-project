<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Extension;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the LegacyHook attribute.
 */
#[Group('Hook')]
#[RunTestsInSeparateProcesses]
#[IgnoreDeprecations]
class LegacyInstallRequirementsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['requirements1_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests installing modules when legacy requirements hooks are present.
   */
  public function testInstall(): void {
    $admin = $this->createUser([
      'administer modules',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin);

    // Ensure that a module can be installed when aa currently installed
    // module has a requirements hook.
    $this->drupalGet('admin/modules');
    $this->submitForm(['modules[common_test][enable]' => TRUE], 'Install');
    $this->assertSession()->statusCodeEquals(200);

    // Ensure the legacy requirements hook shows up on the status page.
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Requirements 1 Test - Changed ');
    $this->assertSession()->pageTextContains('A requirement that will be altered.');
  }

}
