<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\Core\Routing\Exception\CacheableResourceNotFoundException;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

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
  protected static $modules = ['node', 'user', 'filter'];

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
   * Tests that argument validation impacts access to a view's route.
   */
  public function testArgumentValidateAccess(): void {
    // Create a page node.
    $node_page = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
    ]);

    // Create an article node.
    $node_article = $this->createNode([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => 1,
    ]);

    $access_manager = \Drupal::accessManager();
    $route_name = 'view.test_argument_validator_node.page_1';

    $test_user = $this->createUser([
      'access content',
    ]);

    // test_argument_validator_node.page_1 has argument validation on node that
    // the content type must be article.
    $this->expectException(CacheableResourceNotFoundException::class);
    $this->assertFalse($access_manager->checkNamedRoute($route_name, ['node' => $node_page->id()], $test_user));
    $this->assertTrue($access_manager->checkNamedRoute($route_name, ['node' => $node_article->id()], $test_user));

    // The incoming request is left to the view, which responds with a 404.
    $route = \Drupal::service('router.route_provider')->getRouteByName($route_name);
    $route_match = new RouteMatch($route_name, $route, ['node' => $node_page], ['node' => $node_page->id()]);
    $this->assertTrue($access_manager->check($route_match, $test_user, Request::create('/')));

    // Unnamed arguments, including the exception value.
    $route_name = 'view.test_argument_validator_node.page_2';
    $this->assertFalse($access_manager->checkNamedRoute($route_name, ['arg_0' => $node_page->id()], $test_user));
    $this->assertTrue($access_manager->checkNamedRoute($route_name, ['arg_0' => $node_article->id()], $test_user));
    $this->assertTrue($access_manager->checkNamedRoute($route_name, ['arg_0' => 'all'], $test_user));

    // page_3 overrides the node canonical route and its parameter names.
    $route_name = 'entity.node.canonical';
    $this->assertSame('test_argument_validator_node', \Drupal::service('router.route_provider')->getRouteByName($route_name)->getDefault('view_id'));
    $this->assertFalse($access_manager->checkNamedRoute($route_name, ['node' => $node_page->id()], $test_user));
    $this->assertTrue($access_manager->checkNamedRoute($route_name, ['node' => $node_article->id()], $test_user));
  }

  /**
   * Tests the cacheability of the argument validation access result.
   */
  public function testArgumentValidateAccessCacheability(): void {
    $view = View::load('test_argument_validator_node');
    $display = &$view->getDisplay('default');
    $display['display_options']['arguments']['nid']['validate_options']['access'] = TRUE;
    $view->save();
    \Drupal::service('router.builder')->rebuildIfNeeded();

    $test_user = $this->createUser(['access content']);
    $this->setCurrentUser($test_user);
    $node = $this->createNode(['type' => 'article', 'status' => 1]);

    $access_manager = \Drupal::accessManager();
    $route_name = 'view.test_argument_validator_node.page_2';

    $result = $access_manager->checkNamedRoute($route_name, ['arg_0' => $node->id()], $test_user, TRUE);
    $this->assertTrue($result->isAllowed());
    $this->assertContains('node:' . $node->id(), $result->getCacheTags());
    $this->assertContains('config:views.view.test_argument_validator_node', $result->getCacheTags());

    $node->setUnpublished()->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('node')->resetCache();
    $this->expectException(CacheableResourceNotFoundException::class);
    $this->assertFalse($access_manager->checkNamedRoute($route_name, ['arg_0' => $node->id()], $test_user));

    // Missing entities can be created later.
    $result = $access_manager->checkNamedRoute($route_name, ['arg_0' => 999], $test_user, TRUE);
    $this->assertFalse($result->isAllowed());
    $this->assertContains('node_list', $result->getCacheTags());
  }

  /**
   * Tests that "Access denied" validation is enforced on the incoming request.
   */
  public function testArgumentValidateAccessDenied(): void {
    $view = View::load('test_argument_validator_node');
    $display = &$view->getDisplay('default');
    $display['display_options']['arguments']['nid']['validate']['fail'] = 'access denied';
    $view->save();
    \Drupal::service('router.builder')->rebuildIfNeeded();

    $node_page = $this->createNode(['type' => 'page', 'status' => 1]);
    $node_article = $this->createNode(['type' => 'article', 'status' => 1]);
    $test_user = $this->createUser(['access content']);

    $access_manager = \Drupal::accessManager();
    $route_name = 'view.test_argument_validator_node.page_2';
    $route = \Drupal::service('router.route_provider')->getRouteByName($route_name);
    $request = Request::create('/');

    $route_match = new RouteMatch($route_name, $route, ['arg_0' => $node_page->id()], ['arg_0' => $node_page->id()]);
    $this->assertFalse($access_manager->check($route_match, $test_user, $request));
    $route_match = new RouteMatch($route_name, $route, ['arg_0' => $node_article->id()], ['arg_0' => $node_article->id()]);
    $this->assertTrue($access_manager->check($route_match, $test_user, $request));
  }

}
