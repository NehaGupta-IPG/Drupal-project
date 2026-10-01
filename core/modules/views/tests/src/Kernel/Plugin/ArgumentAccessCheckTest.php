<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\Core\Routing\Exception\CacheableResourceNotFoundException;
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
   * Tests route behavior when there is no argument.
   */
  public function testNoArgumentProvided(): void {
    $access_manager = \Drupal::accessManager();
    $test_user = $this->createUser(['access content']);

    // A 403 is achieved via a FALSE return value.
    $route_name = 'view.test_argument_validator_node.page_2';
    $this->assertFalse($access_manager->checkNamedRoute($route_name, [], $test_user));

    // A 404 achieved via an exception.
    $this->expectException(CacheableResourceNotFoundException::class);
    $route_name = 'view.test_argument_validator_node.page_1';
    $access_manager->checkNamedRoute($route_name, [], $test_user);
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

    // A 404 achieved via an exception.
    $this->expectException(CacheableResourceNotFoundException::class);
    $route_name = 'view.test_argument_validator_node.page_3';
    $access_manager->checkNamedRoute($route_name, ['node' => $node_page->id()], $test_user);
  }

}
