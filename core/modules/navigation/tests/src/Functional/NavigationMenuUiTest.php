<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\Functional;

use Drupal\Core\Database\Connection;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the navigation menu in the menu administration UI.
 */
#[Group('navigation')]
#[RunTestsInSeparateProcesses]
class NavigationMenuUiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'menu_ui',
    'navigation',
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
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'access navigation',
      'administer menu',
    ]));

    // Simulate a stale menu_tree record whose route name was lost.
    $this->container->get(Connection::class)
      ->update('menu_tree')
      ->fields(['route_name' => ''])
      ->condition('id', 'navigation.create')
      ->execute();
    $this->container->get('cache.menu')->deleteAll();

    $this->drupalGet('admin/structure/menu/manage/content');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Content');
  }

}
