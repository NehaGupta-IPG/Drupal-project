<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;

/**
 * Tests access with argument validation.
 *
 * @group views
 */
class ArgumentValidatorAccessTest extends ViewsKernelTestBase {

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
    $this->assertFalse($access_manager->checkNamedRoute($route_name, ['node' => $node_page->id()], $test_user));
    $this->assertTrue($access_manager->checkNamedRoute($route_name, ['node' => $node_article->id()], $test_user));
  }

}
