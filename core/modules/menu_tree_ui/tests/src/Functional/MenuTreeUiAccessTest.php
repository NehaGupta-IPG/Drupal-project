<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests access and CSRF protection on the menu tree UI routes.
 *
 * Covers the routing/access/CSRF layer that the kernel MenuTreeMover tests
 * bypass: permission checks on the tree page and children routes, cross-menu
 * scoping of the children endpoint, and CSRF enforcement on the move route.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'menu_link_content',
    'menu_tree_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    Menu::create(['id' => 'access-test-a', 'label' => 'Access test A'])->save();
    Menu::create(['id' => 'access-test-b', 'label' => 'Access test B'])->save();
  }

  /**
   * A user without 'administer menu' is denied the tree page.
   */
  public function testTreePageRequiresPermission(): void {
    $this->drupalLogin($this->drupalCreateUser());
    $this->drupalGet('/admin/structure/menu/manage/access-test-a/tree');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * A user without 'administer menu' is denied the children route.
   */
  public function testChildrenRouteRequiresPermission(): void {
    $this->drupalLogin($this->drupalCreateUser());
    $this->drupalGet('/admin/structure/menu/manage/access-test-a/tree/children');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * The children route rejects a parent id belonging to another menu.
   *
   * Access is checked against the route's menu only; without the parent's
   * own menu being verified, this would silently return menu B's children
   * while access was granted for menu A.
   */
  public function testChildrenRouteRejectsCrossMenuParent(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer menu']));

    $other_menu_link = MenuLinkContent::create([
      'title' => 'B link',
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'access-test-b',
    ]);
    $other_menu_link->save();

    $this->drupalGet('/admin/structure/menu/manage/access-test-a/tree/children', [
      'query' => ['parent' => $other_menu_link->getPluginId()],
    ]);
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * A POST to the move route without the CSRF header is rejected.
   */
  public function testMoveRouteRequiresCsrfHeader(): void {
    $user = $this->drupalCreateUser(['administer menu']);
    $this->drupalLogin($user);

    $client = $this->getHttpClient();
    $url = Url::fromRoute('menu_tree_ui.move', ['menu' => 'access-test-a'])
      ->setAbsolute(TRUE)
      ->toString();

    $result = $client->post($url, [
      'cookies' => $this->getSessionCookies(),
      'headers' => ['Content-Type' => 'application/json'],
      'body' => json_encode(['id' => '', 'new_parent' => '', 'before_id' => NULL]),
      'http_errors' => FALSE,
    ]);
    $this->assertNotEquals(200, $result->getStatusCode());
  }

}
