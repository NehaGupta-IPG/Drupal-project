<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\System;

use Drupal\Tests\BrowserTestBase;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests cron runs.
 */
#[Group('system')]
#[RunTestsInSeparateProcesses]
class PhpContainerRequirementsTest extends BrowserTestBase {
  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Ensure that the manual cron run is working.
   */
  public function testPhpContainerRequirements(): void {
    $admin_user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin_user);
    // Need to wait for opcache to be in place.
    sleep(1);
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('PHP OPcode container cached');

    // Break container dumping to disk.
    $container_file = (new \ReflectionClass(\Drupal::getContainer()))->getFileName();
    unlink($container_file);
    chmod(dirname(dirname($container_file)), 0444);

    $this->drupalGet('admin/reports/status');
    $this->assertSession()->pageTextContains('PHP compiled container Not written to disk');
    $this->assertSession()->pageTextContains('The service container PHP file is not written to disk. Check file permissions and php_storage configuration.');
  }

}
