<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\workspaces\Kernel\WorkspaceTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests that the tree UI is refused inside a workspace.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiWorkspaceTest extends KernelTestBase {

  use UserCreationTrait;
  use WorkspaceTestTrait;

  private const array REFUSED_ROUTES = [
    'menu_tree_ui.edit',
    'menu_tree_ui.move',
    'menu_tree_ui.column_update',
    'menu_tree_ui.save',
    'menu_tree_ui.cancel',
    'menu_tree_ui.break_lock',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
    'menu_tree_ui',
    'menu_ui',
    'treegrid',
    'treegrid_reorder',
    'system',
    'user',
  ];

  /**
   * The entity type manager, used by the workspace test trait.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Plugin IDs of the two links in the test menu.
   *
   * @var string[]
   */
  private array $ids = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    $this->initializeWorkspacesModule();
    $this->setCurrentUser($this->createUser(['administer menu']));

    Menu::create(['id' => 'test', 'label' => 'Test'])->save();
    $this->container->get('plugin.manager.menu.link')->rebuild();

    foreach (['Lapwing', 'Redshank'] as $weight => $title) {
      $entity = MenuLinkContent::create([
        'title' => $title,
        'link' => ['uri' => 'internal:/'],
        'menu_name' => 'test',
        'weight' => $weight,
      ]);
      $entity->save();
      $this->ids[] = $entity->getPluginId();
    }
  }

  /**
   * The write routes and the page need the default workspace.
   */
  public function testRouteAccess(): void {
    $access_manager = $this->container->get('access_manager');
    $admin = $this->container->get('current_user')->getAccount();
    $other_user = $this->createUser();

    $this->switchToLive();
    foreach (self::REFUSED_ROUTES as $route) {
      $parameters = ['menu' => 'test'];
      $this->assertTrue($access_manager->checkNamedRoute($route, $parameters, $admin), $route);
      $this->assertFalse($access_manager->checkNamedRoute($route, $parameters, $other_user), $route);
    }
    $this->assertTrue($access_manager->checkNamedRoute('menu_tree_ui.children', ['menu' => 'test'], $admin));

    $this->switchToWorkspace('stage');
    foreach (self::REFUSED_ROUTES as $route) {
      $this->assertFalse($access_manager->checkNamedRoute($route, ['menu' => 'test'], $admin), $route);
    }
    $this->assertTrue($access_manager->checkNamedRoute('menu_tree_ui.children', ['menu' => 'test'], $admin));
  }

  /**
   * A move request is recorded in Live and refused inside a workspace.
   */
  public function testMoveRefusedInWorkspace(): void {
    [$a, $b] = $this->ids;
    $working_copy = $this->container->get('menu_tree_ui.working_copy');

    $this->switchToLive();
    $response = $this->sendMove($a, $b);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertCount(1, $working_copy->getOps('test'));

    $this->switchToWorkspace('stage');
    $response = $this->sendMove($b, $a);
    $this->assertSame(403, $response->getStatusCode());
    $this->assertCount(1, $working_copy->getOps('test'));
  }

  /**
   * Sends a move request with the session cookie and CSRF header token.
   */
  private function sendMove(string $id, string $new_parent): Response {
    $request = Request::create('/admin/structure/menu/manage/test/tree/move', 'POST', [], [], [], [], json_encode([
      'id' => $id,
      'new_parent' => $new_parent,
      'before_id' => NULL,
    ]));
    $request->setSession(new Session(new MockArraySessionStorage()));
    $session_name = $this->container->get('session_configuration')->getOptions($request)['name'];
    $request->cookies->set($session_name, 'session-id');
    $request->headers->set('X-CSRF-Token', $this->container->get('csrf_token')->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY));
    $request->headers->set('Content-Type', 'application/json');
    return $this->container->get('http_kernel')->handle($request, HttpKernelInterface::SUB_REQUEST);
  }

  /**
   * Edit links points at the tree only in the default workspace.
   */
  public function testEditLinksOperation(): void {
    $menu = Menu::load('test');
    $list_builder = $this->container->get('entity_type.manager')->getListBuilder('menu');

    $this->switchToLive();
    $cacheability = new CacheableMetadata();
    $operations = $list_builder->getOperations($menu, $cacheability);
    $this->assertSame('menu_tree_ui.edit', $operations['edit-links']['url']->getRouteName());
    $this->assertContains('workspace', $cacheability->getCacheContexts());

    $this->switchToWorkspace('stage');
    $cacheability = new CacheableMetadata();
    $operations = $list_builder->getOperations($menu, $cacheability);
    $this->assertSame('entity.menu.links', $operations['edit-links']['url']->getRouteName());
    $this->assertArrayNotHasKey('edit-links-no-js', $operations);
    $this->assertContains('workspace', $cacheability->getCacheContexts());
  }

}
