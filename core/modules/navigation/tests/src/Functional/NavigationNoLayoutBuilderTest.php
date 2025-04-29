<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Generic module test for navigation.
 */
#[Group('navigation')]
#[RunTestsInSeparateProcesses]
class NavigationNoLayoutBuilderTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['navigation'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the layout page works and shows a message.
   */
  public function testBlockUiWithoutLayoutBuilder(): void {
    // Create an administrative user.
    $user = $this->drupalCreateUser([
      'configure navigation layout',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/config/user-interface/navigation-block');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('To customize the navigation blocks, layout builder needs to be enabled.');
  }

}
