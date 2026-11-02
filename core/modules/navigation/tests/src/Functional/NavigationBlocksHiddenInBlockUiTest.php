<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that navigation blocks are not available in the Block UI.
 */
#[Group('navigation')]
#[RunTestsInSeparateProcesses]
class NavigationBlocksHiddenInBlockUiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['navigation', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests logic to exclude navigation blocks in the Block Layout UI.
   */
  public function testNavigationBlocksHiddenInBlockLayout(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer blocks']));

    $this->drupalGet('/admin/structure/block');
    $this->clickLink('Place block');
    $this->assertSession()->linkByHrefNotExists('/admin/structure/block/add/navigation_menu%3Aadmin/stark');
    $this->assertSession()->linkByHrefNotExists('/admin/structure/block/add/navigation_menu%3Acontent/stark');
    $this->assertSession()->linkByHrefNotExists('/admin/structure/block/add/navigation_user/stark');
    $this->assertSession()->linkByHrefNotExists('/admin/structure/block/add/navigation_link/stark');
  }

}
