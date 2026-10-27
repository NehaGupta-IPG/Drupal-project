<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ArgumentValidatorAccessCheck route access checker.
 */
#[Group("views")]
#[RunTestsInSeparateProcesses]
class ArgumentValidatorAccessCheckTest extends ViewsKernelTestBase {

  use UserCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'user', 'filter', 'link', 'menu_link_content'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_argument_validator_node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installConfig('filter');
  }

  /**
   * Tests route behavior when there is no argument.
   */
  public function testNoArgumentProvided(): void {
    $access_manager = \Drupal::accessManager();
    $test_user = $this->createUser(['access content']);

    $route_names = [
      // A 404 achieved via an exception, but converted by AccessManager.
      'view.test_argument_validator_node.page_1',
      // A 403 is achieved via a FALSE return value.
      'view.test_argument_validator_node.page_2',
    ];

    foreach ($route_names as $route_name) {
      $access = $access_manager->checkNamedRoute($route_name, [], $test_user, TRUE);
      $this->assertTrue($access->isForbidden());
      $this->assertInstanceOf(CacheableDependencyInterface::class, $access);
      $this->assertSame(['config:views.view.test_argument_validator_node'], $access->getCacheTags());
    }
  }

  /**
   * Tests route behavior when the argument is invalid.
   */
  public function testFailedArgumentValidation(): void {
    // Create a page node, the view expects an article.
    $node_page = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
    ]);

    $access_manager = \Drupal::accessManager();
    $test_user = $this->createUser(['access content']);

    $route_names = [
      // A 404 achieved via an exception, but converted by AccessManager.
      'view.test_argument_validator_node.page_3',
      // A 403 is achieved via a FALSE return value.
      'view.test_argument_validator_node.page_4',
    ];

    foreach ($route_names as $route_name) {
      $expected_tags = [
        'config:views.view.test_argument_validator_node',
        // Now that we actually validate the node, its tag is also added.
        'node:' . $node_page->id(),
      ];

      $access = $access_manager->checkNamedRoute($route_name, ['node' => $node_page->id()], $test_user, TRUE);
      $this->assertTrue($access->isForbidden());
      $this->assertInstanceOf(CacheableDependencyInterface::class, $access);
      $this->assertSame($expected_tags, $access->getCacheTags());
    }
  }

  /**
   * Tests that menu links to an argument failing validation are hidden.
   */
  public function testMenuLinkToFailedArgumentValidation(): void {
    $this->installEntitySchema('menu_link_content');
    $this->setUpCurrentUser([], ['access content']);

    $node_page = $this->createNode(['type' => 'page']);
    $node_article = $this->createNode(['type' => 'article']);
    foreach ([$node_page, $node_article] as $node) {
      MenuLinkContent::create([
        'title' => $node->bundle() . ' link 404',
        'link' => ['uri' => 'route:view.test_argument_validator_node.page_3;node=' . $node->id()],
        'menu_name' => 'main',
      ])->save();
      MenuLinkContent::create([
        'title' => $node->bundle() . ' link 403',
        'link' => ['uri' => 'route:view.test_argument_validator_node.page_4;node=' . $node->id()],
        'menu_name' => 'main',
      ])->save();
    }

    // Same steps as \Drupal\system\Plugin\Block\SystemMenuBlock::build().
    $menu_tree = \Drupal::menuTree();
    $tree = $menu_tree->load('main', new MenuTreeParameters());
    $tree = $menu_tree->transform($tree, [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
    ]);
    $build = $menu_tree->build($tree);
    $output = (string) $this->render($build);

    $this->assertStringContainsString('article link 404', $output);
    $this->assertStringContainsString('article link 403', $output);
    $this->assertStringNotContainsString('page link 404', $output);
    $this->assertStringNotContainsString('page link 403', $output);
  }

}
