<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ArgumentAccessCheck route access checker.
 */
#[Group("views")]
#[RunTestsInSeparateProcesses]
class ArgumentAccessCheckTest extends ViewsKernelTestBase {

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

    // A 403 is achieved via a FALSE return value.
    $route_name = 'view.test_argument_validator_node.page_2';
    $this->assertFalse($access_manager->checkNamedRoute($route_name, [], $test_user));

    // A 404 is returned by the controller, not the access check.
    $route_name = 'view.test_argument_validator_node.page_1';
    $this->assertTrue($access_manager->checkNamedRoute($route_name, [], $test_user));
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

    // A 403 is achieved via a FALSE return value.
    $route_name = 'view.test_argument_validator_node.page_4';
    $this->assertFalse($access_manager->checkNamedRoute($route_name, ['node' => $node_page->id()], $test_user));

    // A 404 is returned by the controller, not the access check.
    $route_name = 'view.test_argument_validator_node.page_3';
    $this->assertTrue($access_manager->checkNamedRoute($route_name, ['node' => $node_page->id()], $test_user));
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

    // Menu links are properly hidden when the view is configured to 403.
    $this->assertStringContainsString('article link 403', $output);
    $this->assertStringNotContainsString('page link 403', $output);

    // Menu links show when the view is configured to 404.
    $this->assertStringContainsString('article link 404', $output);
    $this->assertStringContainsString('page link 404', $output);
  }

}
