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
      MenuLinkContent::create([
        'menu_name' => 'main',
        'title' => sprintf('Item %03d', $i),
        'link' => ['uri' => 'route:<nolink>'],
        'parent' => '',
        'weight' => $i,
      ])->save();
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
   * Returns the first page with has_more when the level overflows.
   */
  public function testChildrenFirstPageHasMore(): void {
    $data = $this->decode($this->controller->children($this->menu, Request::create('/children')));

    $this->assertCount(MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE, $data['items']);
    $this->assertSame(0, $data['offset']);
    $this->assertTrue($data['has_more']);
    $this->assertSame(self::SEEDED, $data['total']);
  }

  /**
   * Returns the remainder and clears has_more on the last page.
   */
  public function testChildrenLastPageNoMore(): void {
    $offset = MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE;
    $request = Request::create('/children', 'GET', ['offset' => (string) $offset]);
    $data = $this->decode($this->controller->children($this->menu, $request));

    $this->assertCount(self::SEEDED - $offset, $data['items']);
    $this->assertSame($offset, $data['offset']);
    $this->assertFalse($data['has_more']);
    $this->assertSame(self::SEEDED, $data['total']);
  }

  /**
   * Decodes a JSON response body to an array.
   *
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The response.
   *
   * @return array
   *   The decoded payload.
   */
  private function decode($response): array {
    return json_decode((string) $response->getContent(), TRUE);
  }

}
