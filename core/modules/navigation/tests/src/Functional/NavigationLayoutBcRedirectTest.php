<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Navigation layout BC redirect.
 */
#[Group('navigation')]
#[RunTestsInSeparateProcesses]
#[IgnoreDeprecations]
class NavigationLayoutBcRedirectTest extends BrowserTestBase {

  use BlockCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'navigation',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permissions to access navigation admin pages.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'configure navigation layout',
      'access administration pages',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests that the old layout path redirects to the new one.
   */
  public function testNavigationLayoutBcRedirect(): void {
    $this->drupalGet('/admin/config/user-interface/navigation-block');
    // Should redirect to the new URL.
    $this->assertSession()->addressEquals('/admin/config/user-interface/navigation/layout');
    // Should show a deprecation warning message to the user.
    $this->assertSession()->pageTextContains('You have been redirected from');
    $this->assertSession()->pageTextContains('admin/config/user-interface/navigation-block');
    $this->assertSession()->pageTextContains('admin/config/user-interface/navigation/layout');
  }

}
