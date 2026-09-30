<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\System;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests PHP container requirements.
 */
#[Group('system')]
#[RunTestsInSeparateProcesses]
class PhpContainerRequirementsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests PHP container requirements.
   */
  #[RequiresPhpExtension('Zend OPcache')]
  public function testPhpContainerRequirements(): void {
    $admin_user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin_user);
    // Need to wait for opcache to be in place.
    sleep(3);
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('PHP OPcode container cached');

    // Break container dumping to disk.
    $container_file = (new \ReflectionClass(\Drupal::getContainer()))->getFileName();
    $storage_directory = dirname($container_file, 2);
    $original_permissions = fileperms($storage_directory);
    unlink($container_file);
    chmod($storage_directory, 0444);

    $this->drupalGet('admin/reports/status');
    $this->assertSession()->pageTextContains('PHP compiled container Not written to disk');
    $this->assertSession()->pageTextContains('The service container PHP file is not written to disk. Check file permissions and php_storage configuration.');

    // Restore the permissions so the test directory can be cleaned up.
    chmod($storage_directory, $original_permissions);
  }

}
