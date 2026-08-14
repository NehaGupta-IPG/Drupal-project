<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_ui\Functional;

use Drupal\Core\Database\Connection;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests menu administration with invalid routes.
 */
#[Group('menu_ui')]
#[RunTestsInSeparateProcesses]
class MenuUiInvalidRouteTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'menu_ui',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests an invalid menu route does not prevent menu administration.
   */
  public function testMenuAdministrationWithEmptyRouteName(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer menu',
    ]));

    // Simulate a stale menu_tree record whose route name was lost.
    $this->container->get(Connection::class)
      ->update('menu_tree')
      ->fields(['route_name' => ''])
      ->condition('id', 'system.admin_structure')
      ->execute();
    $this->container->get('cache.menu')->deleteAll();

    $this->drupalGet('admin/structure/menu/manage/admin');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Structure');
  }

}
