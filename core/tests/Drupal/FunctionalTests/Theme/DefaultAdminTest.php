<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Theme;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Default Admin theme.
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class DefaultAdminTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'default_admin';

  /**
   * Tests Default Admin's region and blocks.
   */
  public function testHelpBlockAndRegion(): void {
    $permissions = [
      'administer modules',
    ];
    $this->drupalLogin($this->drupalCreateUser($permissions));
    $this->drupalGet('admin/modules');
    $this->assertSession()->elementNotExists('css', '#block-default-admin-help');

    // Install the block module to ensure Default Admin's configuration is valid
    // according to schema.
    \Drupal::service('module_installer')->install(['block', 'help']);
    $this->rebuildAll();

    $this->drupalGet('admin/modules');
    $this->assertSession()->elementExists('css', '#block-default-admin-help');
    $element = $this->assertSession()->elementExists('css', '.region-help');
    $this->assertNotSame('', $element->getHtml());

    $this->drupalGet('admin');
    $element = $this->assertSession()->elementExists('css', '.region-help');
    $this->assertSame('', $element->getHtml());
  }

}
