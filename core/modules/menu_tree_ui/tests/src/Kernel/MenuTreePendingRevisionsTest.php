<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_tree_ui\Controller\MenuTreeController;
use Drupal\system\Entity\Menu;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\workspaces\Kernel\WorkspaceTestTrait;
use Drupal\treegrid_reorder\TreegridWorkingCopy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests that a menu with a pending workspace revision cannot be reordered.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreePendingRevisionsTest extends KernelTestBase {

  use UserCreationTrait;
  use WorkspaceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
    'menu_tree_ui',
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
   * The working copy of the test menu.
   */
  private TreegridWorkingCopy $workingCopy;

  /**
   * The controller under test.
   */
  private MenuTreeController $controller;

  /**
   * The links in the test menu, in order.
   *
   * @var \Drupal\menu_link_content\Entity\MenuLinkContent[]
   */
  private array $links = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    $this->initializeWorkspacesModule();
    $request = Request::create('/');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
    $this->setCurrentUser($this->createUser(['administer menu']));

    Menu::create(['id' => 'test', 'label' => 'Test'])->save();
    $this->container->get('plugin.manager.menu.link')->rebuild();

    foreach (['Lapwing', 'Redshank'] as $weight => $title) {
      $link = MenuLinkContent::create([
        'title' => $title,
        'link' => ['uri' => 'internal:/'],
        'menu_name' => 'test',
        'weight' => $weight,
      ]);
      $link->save();
      $this->links[] = $link;
    }

    $this->workingCopy = $this->container->get('menu_tree_ui.working_copy');
    $this->controller = MenuTreeController::create($this->container);
  }

  /**
   * Gives the first link a pending revision in a workspace.
   */
  private function createPendingRevision(): void {
    $this->switchToWorkspace('stage');
    $link = MenuLinkContent::load($this->links[0]->id());
    $link->set('title', 'Lapwing in stage');
    $link->setNewRevision();
    $link->save();
    $this->switchToLive();
  }

  /**
   * Builds a move request.
   */
  private function moveRequest(string $id, string $new_parent): Request {
    return Request::create('/', 'POST', [], [], [], [], json_encode([
      'id' => $id,
      'new_parent' => $new_parent,
      'before_id' => NULL,
    ]));
  }

  /**
   * Without pending revisions a move is recorded and Save writes it.
   */
  public function testReorderWorksWithoutPendingRevisions(): void {
    [$a, $b] = array_map(fn(MenuLinkContent $link): string => $link->getPluginId(), $this->links);
    $menu = Menu::load('test');

    $response = $this->controller->move($menu, $this->moveRequest($a, $b));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertCount(1, $this->workingCopy->getOps('test'));

    $response = $this->controller->save($menu);
    $this->assertSame(200, $response->getStatusCode());
    $definition = $this->container->get('plugin.manager.menu.link')->getDefinition($a);
    $this->assertSame($b, $definition['parent']);
  }

  /**
   * Move is refused and records nothing.
   */
  public function testMoveRefusedWithPendingRevision(): void {
    [$a, $b] = array_map(fn(MenuLinkContent $link): string => $link->getPluginId(), $this->links);
    $this->createPendingRevision();

    $response = $this->controller->move(Menu::load('test'), $this->moveRequest($b, $a));
    $this->assertSame(409, $response->getStatusCode());
    $result = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame('pending_revisions', $result['code']);
    $this->assertSame([], $this->workingCopy->getOps('test'));
  }

  /**
   * Save refuses a working copy recorded before the pending revision existed.
   */
  public function testSaveRefusedWithPendingRevision(): void {
    [$a, $b] = array_map(fn(MenuLinkContent $link): string => $link->getPluginId(), $this->links);
    $menu = Menu::load('test');
    $this->assertSame(200, $this->controller->move($menu, $this->moveRequest($b, $a))->getStatusCode());
    $this->createPendingRevision();

    $response = $this->controller->save($menu);
    $this->assertSame(409, $response->getStatusCode());
    $result = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame('pending_revisions', $result['code']);

    $definition = $this->container->get('plugin.manager.menu.link')->getDefinition($b);
    $this->assertSame('', $definition['parent']);
    $this->assertSame(1, (int) $definition['weight']);
    $this->assertCount(1, $this->workingCopy->getOps('test'));

    $response = $this->controller->cancel($menu);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame([], $this->workingCopy->getOps('test'));
  }

  /**
   * Every row of the page renders as not movable.
   */
  public function testPageRowsNotMovableWithPendingRevision(): void {
    $this->createPendingRevision();

    $build = $this->controller->page(Menu::load('test'));
    $this->assertCount(2, $build['#top_level']);
    $renderer = $this->container->get('renderer');
    foreach ($build['#top_level'] as $row) {
      $html = (string) $renderer->renderInIsolation($row);
      $this->assertStringContainsString('data-movable="false"', $html);
    }
  }

  /**
   * Without pending revisions the page rows are movable.
   */
  public function testPageRowsMovableWithoutPendingRevision(): void {
    $build = $this->controller->page(Menu::load('test'));
    $renderer = $this->container->get('renderer');
    foreach ($build['#top_level'] as $row) {
      $html = (string) $renderer->renderInIsolation($row);
      $this->assertStringContainsString('data-movable="true"', $html);
    }
  }

}
