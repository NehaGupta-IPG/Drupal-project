<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_tree_ui\Controller\MenuTreeController;
use Drupal\menu_tree_ui\MenuTreeUiConstants;
use Drupal\system\MenuInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that the top level is capped on the server and paged on the endpoint.
 *
 * Uses direct controller instantiation to stay in the kernel layer. See
 * \Drupal\menu_tree_ui\MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeControllerPagingTest extends KernelTestBase {

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
   * The menu under test.
   *
   * @var \Drupal\system\MenuInterface
   */
  private MenuInterface $menu;

  /**
   * The controller under test.
   *
   * @var \Drupal\menu_tree_ui\Controller\MenuTreeController
   */
  private MenuTreeController $controller;

  /**
   * The number of top-level links seeded (one page plus a remainder).
   */
  private const SEEDED = MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE + 5;

  /**
   * The plugin IDs of the seeded links, in order.
   *
   * @var list<string>
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

    $this->menu = $this->container->get('entity_type.manager')
      ->getStorage('menu')
      ->load('main');

    for ($i = 1; $i <= self::SEEDED; $i++) {
      $link = MenuLinkContent::create([
        'menu_name' => 'main',
        'title' => sprintf('Item %03d', $i),
        'link' => ['uri' => 'route:<nolink>'],
        'parent' => '',
        'weight' => $i,
      ]);
      $link->save();
      $this->ids[] = $link->getPluginId();
    }

    $this->controller = MenuTreeController::create($this->container);
  }

  /**
   * Caps the server render to one page and reports the true total.
   */
  public function testPageCapsTopLevelRender(): void {
    $build = $this->controller->page($this->menu);

    $this->assertCount(MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE, $build['#top_level']);
    $this->assertSame(self::SEEDED, $build['#total']);
    $this->assertTrue($build['#truncated']);
    $this->assertSame(MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE, $build['#page_size']);
  }

  /**
   * The first page holds a page of rows and points at the next one.
   */
  public function testChildrenFirstPageHasNext(): void {
    $build = $this->controller->children($this->menu, Request::create('/children'));

    $this->assertCount(MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE, $build['#rows']);
    $this->assertSame((string) self::SEEDED, $this->header($build, 'X-Treegrid-Total'));
    $this->assertSame(rawurlencode($this->ids[MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE]), $this->header($build, 'X-Treegrid-Next-Id'));
    $this->assertSame('1', $this->header($build, 'X-Treegrid-Rows'));
  }

  /**
   * The last page holds the remainder and no next id.
   */
  public function testChildrenLastPageHasNoNext(): void {
    $offset = MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE;
    $request = Request::create('/children', 'GET', ['offset' => (string) $offset]);
    $build = $this->controller->children($this->menu, $request);

    $this->assertCount(self::SEEDED - $offset, $build['#rows']);
    $this->assertNull($this->header($build, 'X-Treegrid-Next-Id'));
    $this->assertSame((string) self::SEEDED, $this->header($build, 'X-Treegrid-Total'));
  }

  /**
   * Pending moves apply to the pages, with the total unchanged.
   */
  public function testChildrenPagesApplyPendingMoves(): void {
    $this->container->get('menu_tree_ui.working_copy')->addOp('main', [
      'type' => 'move',
      'id' => $this->ids[0],
      'new_parent' => '',
      'before_id' => NULL,
    ]);

    $first = $this->controller->children($this->menu, Request::create('/children'));
    $this->assertSame($this->ids[1], $first['#rows'][0]['#element']->link->getPluginId());
    $this->assertSame((string) self::SEEDED, $this->header($first, 'X-Treegrid-Total'));

    $request = Request::create('/children', 'GET', ['offset' => (string) MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE]);
    $last = $this->controller->children($this->menu, $request);
    $this->assertSame($this->ids[0], end($last['#rows'])['#element']->link->getPluginId());
    $this->assertNull($this->header($last, 'X-Treegrid-Next-Id'));
  }

  /**
   * Returns a header value set through the build's attachments.
   */
  private function header(array $build, string $name): ?string {
    foreach ($build['#attached']['http_header'] as [$header, $value]) {
      if ($header === $name) {
        return $value;
      }
    }
    return NULL;
  }

}
