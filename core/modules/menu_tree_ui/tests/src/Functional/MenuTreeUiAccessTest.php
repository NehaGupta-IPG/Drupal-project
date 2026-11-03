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

    $result = $client->request('POST', $url, [
      'cookies' => $this->getSessionCookies(),
      'headers' => ['Content-Type' => 'application/json'],
      'body' => json_encode(['id' => '', 'new_parent' => '', 'before_id' => NULL]),
      'http_errors' => FALSE,
    ]);
    $this->assertNotEquals(200, $result->getStatusCode());
  }

  /**
   * A user without 'administer menu' is denied the break-lock form.
   */
  public function testBreakLockRequiresPermission(): void {
    $this->drupalLogin($this->drupalCreateUser());
    $this->drupalGet('/admin/structure/menu/manage/access-test-a/tree/break-lock');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Confirming the break-lock form clears an existing working copy.
   *
   * A pending move is recorded by one user, locking the working copy; a
   * second user with the same permission then breaks the lock, and the
   * pending move must be gone afterwards.
   */
  public function testBreakLockClearsWorkingCopy(): void {
    $link = MenuLinkContent::create([
      'title' => 'A link',
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'access-test-a',
    ]);
    $link->save();

    $owner = $this->drupalCreateUser(['administer menu']);
    $admin = $this->drupalCreateUser(['administer menu']);

    // Record a pending move as the lock owner via the move route.
    $this->drupalLogin($owner);
    $csrf_token = $this->drupalGet('/session/token');

    $client = $this->getHttpClient();
    $move_url = Url::fromRoute('menu_tree_ui.move', ['menu' => 'access-test-a'])
      ->setAbsolute(TRUE)
      ->toString();
    $result = $client->request('POST', $move_url, [
      'cookies' => $this->getSessionCookies(),
      'headers' => [
        'Content-Type' => 'application/json',
        'X-CSRF-Token' => $csrf_token,
      ],
      'body' => json_encode(['id' => $link->getPluginId(), 'new_parent' => '', 'before_id' => NULL]),
      'http_errors' => FALSE,
    ]);
    $this->assertSame(200, $result->getStatusCode(), (string) $result->getBody());

    // The owner's own working copy is not locked to them, so the second user
    // holds the lock once they have recorded a move; log in as a different
    // user with the same permission and break it.
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/structure/menu/manage/access-test-a/tree');
    $this->assertSession()->elementExists('css', '.menu-tree-ui__lock');
    $this->assertSession()->linkByHrefExists('/admin/structure/menu/manage/access-test-a/tree/break-lock');

    // Breaking the lock returns to the tree, which no longer shows the notice.
    $this->drupalGet('/admin/structure/menu/manage/access-test-a/tree/break-lock');
    $this->submitForm([], 'Break lock');
    $this->assertSession()->addressEquals('/admin/structure/menu/manage/access-test-a/tree');
    $this->assertSession()->elementNotExists('css', '.menu-tree-ui__lock');

    $this->drupalGet('/admin/structure/menu/manage/access-test-a/tree');
    $this->assertSession()->elementNotExists('css', '.menu-tree-ui__lock');
  }

}
